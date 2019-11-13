<?php

namespace app\fund\model;

use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Db;
use think\exception\DbException;
use think\Log;
use app\common\exception\AppException;

class IndexModel extends CommonModel
{
    /**
     * 开启团队奖励
     *
     * @param $user_info
     * @return int
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function doOpenGroup($user_info)
    {
        $user_id = $user_info['user_id'];
        $users = $this->getUserInfoByUserId($user_id);
        $user_code_invite = $users['user_code_invite'];
        // 判断是否合格
        $one_info = Db::name('user')->field('user_id, mobile, user_code_invite, auth_level, create_time')->where('code_invite', $user_code_invite)->select();
        if (count($one_info) < 10) {
            Log::write("用户$user_id,不符合开通团队的条件", 'error');
            throw new AppException(USER_NOT_AUTH, lang('USER_NOT_AUTH'));
        }

        $one_user_id = [];
        foreach ($one_info as &$item) {
            $one_user_id[] = $item['user_id'];
        }

        // 一级好友持币曾经超过5w(DG)
        $one_amount = Db::name('user_account')->whereIn('user_id', $one_user_id)->where('enough_money', 1)->where('asset_code', DG)->count('user_id');
        if ($one_amount < 10) {
            Log::write("用户$user_id,不符合开通团队的条件", 'error');
            throw new AppException(USER_NOT_AUTH, lang('USER_NOT_AUTH'));
        }
        // 更新用户状态
        $update_data = [
            'auth_level' => 1,
            'update_time' => msectime()
        ];
        Db::name('user')->where('user_id', $user_id)->update($update_data);
        return 1;
    }

    /**
     * 使用 dgv兑换dg币
     *
     * @param $param
     * @param $user_info
     * @return int
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doDgvToDg($param, $user_info)
    {
        $user_id = $user_info['user_id'];
        $this->check_params($param, ['amount', 'safety_password']);
        // 判断金额,
        $amount = $this->do_amount($param['amount']);
        if ($amount < 1) {
            Log::write("金额小于1:[$amount]", 'error');
            throw new AppException(AMOUNT_FORMAT_ERROR, lang('AMOUNT_FORMAT_ERROR'));
        }

        // 判断用户安全码是否正确
        $this->safety_password_right($user_id, $param['safety_password']);
        // 获取自己账户的余额是否充足
        $this->getUserAssetCodeAmount($user_id, DGV, $amount);
        $dgv_to_dg_rate = Db::name('profile')->where('profile_key', DGV_TO_DG_RATE)->value('profile_value');
        Log::write("DGV兑换DG币,兑换汇率[$dgv_to_dg_rate]", 'error');
        $dg_amount = $amount * $dgv_to_dg_rate;
        Log::write("DGV兑换DG币,dg得到[$dg_amount]", 'error');
        // 开启事务
        Db::startTrans();
        try {
            // 减少可用资产
            $this->reduce_money($user_id, DGV, $amount);
            // 兑换dg币,
            $this->add_money($user_id, DG, $dg_amount, 'amount_frozen');
            // 添加转账记录
            $this->create_transfer_record($user_id, 0, DGV, $amount, 9, "DGV兑换DG币(消耗dgv)-汇率[$dgv_to_dg_rate]"); // 9=DGV兑换DG币(消耗dgv),10=DGV兑换DG币(获取dg)

            // 添加转账记录
            $this->create_transfer_record(0, $user_id, DG, $dg_amount, 10, "DGV兑换DG币(获取dg)-汇率[$dgv_to_dg_rate]");
            Db::commit();
            return 1;
        } catch (\Exception $e) {
            Db::rollback();
            Log::write("用户id:[$user_id], 兑换dg币失败, 失败信息[" . $e->getMessage() . "]", 'error');
            throw new AppException(DGV_TO_DG_ERROR, lang('DGV_TO_DG_ERROR'));
        }
    }

    /**
     * dg币勾兑dgv
     *
     * 输入金额在1-dg币可用区间, 并且在1-dgv锁仓区间
     * @param $param
     * @param $user_info
     * @return int
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doDgToDgv($param, $user_info)
    {
        $user_id = $user_info['user_id'];
        $this->check_params($param, ['amount', 'safety_password']);
        // 判断金额,
        $amount = $this->do_amount($param['amount']);
        $where_dg = [
            'user_id' => $user_id,
            'asset_code' => DG
        ];

        $where_dgv = [
            'user_id' => $user_id,
            'asset_code' => DGV
        ];
        // 查询用户的币种金额
        $dg_amount_available = Db::name('user_account')->where($where_dg)->value('amount_available');
        $dgv_amount_frozen = Db::name('user_account')->where($where_dgv)->value('amount_frozen');
        if ($amount < 1 || $amount > $dg_amount_available || $amount > $dgv_amount_frozen) {
            Log::write("金额小于1或者不合法:[$amount]", 'error');
            throw new AppException(AMOUNT_FORMAT_ERROR, lang('AMOUNT_FORMAT_ERROR'));
        }

        // 判断用户安全码是否正确
        $this->safety_password_right($user_id, $param['safety_password']);
        $dg_to_dgv_rate = Db::name('profile')->where('profile_key', DG_TO_DGV_RATE)->value('profile_value');
        // 开启事务
        Db::startTrans();
        try {
            // 开始勾兑
            $dg_to_dgv_amount = $amount * $dg_to_dgv_rate; // 勾兑成的可用dgv
            // 减少可用资产
            $this->reduce_money($user_id, DG, $amount);
            // 兑换dg币,
            $this->add_money($user_id, DGV, $dg_to_dgv_amount);


            // 添加转账记录
            $this->create_transfer_record($user_id, 0, DG, $amount, 12, "DG兑利DGV币(消耗dg)-兑利比例[$dg_to_dgv_rate]"); // 12=DG兑换DGV币(消耗dg),13=DG兑换DGV币(获取dgv)

            // 添加转账记录
            $this->create_transfer_record(0, $user_id, DGV, $dg_to_dgv_amount, 13, "DG兑利DGV币(获取dgv)-兑利比例[$dg_to_dgv_rate]");
            Db::commit();
            return 1;
        } catch (\Exception $e) {
            Db::rollback();
            Log::write("用户id:[$user_id], 兑换dg币失败, 失败信息[" . $e->getMessage() . "]", 'error');
            throw new AppException(DGV_TO_DG_ERROR, lang('DGV_TO_DG_ERROR'));
        }
    }

    /**
     * 获取汇率
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doGetRate()
    {
        $profile = Db::name('profile')->select();
        $result = [];
        foreach ($profile as $item) {
            $result[$item['profile_key']] = $item['profile_value'];
        }
        return $result;
    }
}