<?php

namespace app\admin\model;

use app\common\model\BaseCommonModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Db;
use app\common\exception\AppException;
use think\exception\DbException;
use think\exception\PDOException;
use think\Log;

class WalletModel extends BaseCommonModel
{

    /**
     * 充值列表
     *
     * @param $param
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    public function doDeposit($param)
    {
        $num = isset($param['num']) ? $param['num'] : 20;
        $keyword = isset($param['keyword']) ? $param['keyword'] : '';

        // 所有用户
        $app_user_count = Db::name('deposit_request')->alias('d')->join('user u', 'd.user_id = u.user_id', 'left')->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->count('d.user_id');

        $result['deposit_count'] = $app_user_count;
        $result['keyword'] = $keyword;
        $result['num'] = $num;
        if ($app_user_count == 0) {
            $result['deposit_list'] = [];
            $result['pages'] = 0;
            return $result;
        }

        $app_user = Db::name('deposit_request')->alias('d')->join('user u', 'd.user_id = u.user_id', 'left')->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->field(['d.*', 'u.mobile,u.nickname'])->order('create_time desc')->paginate($num);
        $pages = $app_user->render();

        $result['deposit_list'] = $app_user;
        $result['pages'] = $pages;
        return $result;
    }

    /**
     * 提现列表
     *
     * @param $param
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    public function doWithdraw($param)
    {
        $num = isset($param['num']) ? $param['num'] : 20;
        $keyword = isset($param['keyword']) ? $param['keyword'] : '';

        // 所有用户
        $app_user_count = Db::name('withdraw_request')->alias('d')->join('user u', 'd.user_id = u.user_id', 'left')->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->count('d.user_id');

        $result['withdraw_count'] = $app_user_count;
        $result['keyword'] = $keyword;
        $result['num'] = $num;
        if ($app_user_count == 0) {
            $result['withdraw_list'] = [];
            $result['pages'] = 0;
            return $result;
        }

        $app_user = Db::name('withdraw_request')->alias('d')->join('user u', 'd.user_id = u.user_id', 'left')->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->field(['d.*', 'u.mobile,u.nickname'])->order('create_time desc')->paginate($num);

        $pages = $app_user->render();

        $result['withdraw_list'] = $app_user;
        $result['pages'] = $pages;
        return $result;
    }

    /**
     * 提现记录
     *
     * @param $param
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    public function doWithdrawRecord($param)
    {
        $num = isset($param['num']) ? $param['num'] : 20;
        $keyword = isset($param['keyword']) ? $param['keyword'] : '';

        // 所有用户
        $app_user_count = Db::name('withdraw_record')->alias('d')->join('user u', 'd.user_id = u.user_id', 'left')->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->count('d.user_id');

        $result['withdraw_count'] = $app_user_count;
        $result['keyword'] = $keyword;
        $result['num'] = $num;
        if ($app_user_count == 0) {
            $result['withdraw_list'] = [];
            $result['pages'] = 0;
            return $result;
        }

        $app_user = Db::name('withdraw_record')->alias('d')->join('user u', 'd.user_id = u.user_id', 'left')->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->field(['d.*', 'u.mobile,u.nickname'])->order('create_time desc')->paginate($num);
        $pages = $app_user->render();

        $result['withdraw_list'] = $app_user;
        $result['pages'] = $pages;
        return $result;
    }

    /**
     * 更新用户冻结金额
     *
     * @param $param
     * @return int
     * @throws Exception
     */
    public function doUpdateDg($param)
    {
        $user_account = Db::name('user_account')->where('id', $param['id'])->find();
        if ($param['asset_code'] == DG) {
            $table = "change_frozen_dg";
            $update_data['amount_frozen'] = $param['amount'];
        } else {
            $table = "change_dgv_amount";
            $update_data['amount_available'] = $param['amount'];
        }

        $update_data['update_time'] = msectime();
        // 开启事务
        Db::startTrans();
        try {
            Db::name('user_account')->where('id', $param["id"])->update($update_data);
            $insert_data = [
                'user_id' => $user_account['user_id'],
                'data_before' => json_encode($user_account),
                'amount' => $param['amount'],
                'create_time' => msectime(),
            ];
            Db::name($table)->insert($insert_data);
            Db::commit();
            return 1;
        } catch (\Exception $e) {
            Db::rollback();
            Log::write("后台更新冻结额度,失败信息[" . $e->getMessage() . "]", 'error');
            throw new AppException(UPDATE_AMOUNT_ERROR, lang('UPDATE_AMOUNT_ERROR'));
        }
    }
}