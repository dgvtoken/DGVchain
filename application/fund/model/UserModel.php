<?php

namespace app\fund\model;

use app\common\exception\AppException;
use PDOStatement;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Db;
use think\exception\DbException;
use think\Log;

class UserModel extends CommonModel
{
    /**
     * 获取用户资产账户详情
     *
     * @param $param
     * @param $user_info
     * @return false|PDOStatement|string|Collection
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function doAccounts($param, $user_info)
    {
//        $user_info['user_id'] = 1;
        $asset_code = isset($param['asset_code']) ? $param['asset_code'] : '';
        $where['user_id'] = $user_info['user_id'];

        if ($asset_code != '') {
            $where['asset_code'] = $asset_code;
        }
        $user_accounts = Db::name('user_account')->where($where)->select();

        return $user_accounts;
    }

    /**
     * 转账
     * @param $param
     * @param $user_info
     * @return mixed
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doTransfer($param, $user_info)
    {
        $user_id = $user_info['user_id'];
        $this->check_params($param, ['to_mobile', 'amount', 'safety_password', 'asset_code']);
        $to_mobile = $param['to_mobile'];
        $this->is_mobile($to_mobile);
        $to_user_info = $this->getUserInfoByMobile($to_mobile);
        if ($param['asset_code'] != DGV) {
            Log::write("不支持转账,手机号是:[$to_mobile], 币种是[" . $param['asset_code'] . "]", 'error');
            throw new AppException(ASSET_CODE_NOT_SUPPORT_TRANSFER, lang('ASSET_CODE_NOT_SUPPORT_TRANSFER'));
        }

        if (empty($to_user_info)) {
            Log::write("该手机号还没注册,手机号是:[$to_mobile]", 'error');
            throw new AppException(MOBILE_NOT_REGISTER, lang('MOBILE_NOT_REGISTER'));
        }
        $users = $this->getUserInfoByUserId($user_id);
        if ($to_mobile == $users['mobile']) {
            Log::write("自己不能给自己转账,手机号是:[$to_mobile]", 'error');
            throw new AppException(USER_NOT_TRANSFER_MYSELF, lang('USER_NOT_TRANSFER_MYSELF'));
        }

        // 判断金额,
        $amount = $this->do_amount($param['amount']);
        // 判断用户安全码是否正确
        $this->safety_password_right($user_id, $param['safety_password']);
        // 获取自己账户的余额是否充足
        $this->getUserAssetCodeAmount($user_id, $param['asset_code'], $amount);
        // 开始转账 (开启事务)
        $this->user_transfer($user_id, $to_user_info['user_id'], $param['asset_code'], $amount);
        $result['amount'] = $param['amount'];
        return $result;
    }

    /**
     * 提现请求
     *
     * @param $param
     * @param $user_info
     * @return int
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doWithdraw($param, $user_info)
    {
        $user_id = $user_info['user_id'];
        $this->check_params($param, ['amount', 'safety_password', 'asset_code', 'to_account']);
        $memo = isset($param['memo']) ? $param['memo'] : '';
        // 判断eos账户是否合法
        $this->check_eos_account($param['to_account']);
        // 判断金额,
        $amount = $this->do_amount($param['amount']);
        // 判断用户安全码是否正确
        $this->safety_password_right($user_id, $param['safety_password']);
        // 获取自己账户的余额是否充足
        $this->getUserAssetCodeAmount($user_id, $param['asset_code'], $amount);
        // 开启事务
        Db::startTrans();
        try {
            // 减少可用资产
            $this->reduce_money($user_id, $param['asset_code'], $param['amount']);
            // 未成功之前转移到冻结冻结资产
            $this->add_money($user_id, $param['asset_code'], $param['amount'], 'amount_frozen');
            // 添加转账记录
//            $record_id = $this->create_transfer_record($user_id, 0, $param['asset_code'], $param['amount'], 4, "提现");// 4是提现
            // 添加到提现记录表
            $withdraw_request_id = $this->create_withdraw_request(create_uuid(), $user_id, $param['to_account'], $param['amount'], $param['asset_code'], $memo);
//            $this->update_transfer_record($record_id, $withdraw_request_id);
            Db::commit();
            return 1;
        } catch (\Exception $e) {
            Db::rollback();
            Log::write("用户id:[$user_id], 提现失败, 失败信息[" . $e->getMessage() . "]", 'error');
            throw new AppException(WITHDRAW_ERROR, lang('WITHDRAW_ERROR'));
        }
    }

    /**
     * 插入提现请求
     *
     * @param $create_uuid
     * @param $user_id
     * @param $to_account
     * @param $amount
     * @param $asset_code
     * @param $memo
     * @return int|string
     */
    public function create_withdraw_request($create_uuid, $user_id, $to_account, $amount, $asset_code, $memo)
    {
        $need_audit = 'NO';
        $audit_status = 'PASS';
        if ($amount >= WITHDRAW_AMOUNT_LIMIT) {
            $need_audit = 'YES';
            $audit_status = 'INIT';
        }
        $fee = $amount * WITHDRAW_FEE_RATE;
        if ($fee >= WITHDRAW_MAX_FEE) {
            $fee = WITHDRAW_MAX_FEE;
        } else if ($fee <= WITHDRAW_MIN_FEE) {
            $fee = WITHDRAW_MIN_FEE;
        }

        $insert_data = [
            'uni_uuid' => $create_uuid,
            'user_id' => $user_id,
            'to_account' => $to_account,
            'amount' => $amount - $fee,
            'fee_amount' => $fee,
            'asset_code' => $asset_code,
            'memo' => $memo,
            'need_audit' => $need_audit,
            'audit_status' => $audit_status,
            'withdraw_status' => 'INIT',
            'create_time' => msectime(),
            'update_time' => 0,
        ];
        $withdraw_request_id = Db::name('withdraw_request')->insertGetId($insert_data);
        return $withdraw_request_id;
    }

    /**
     *  更新提现记录
     *
     * @param $record_id
     * @param $withdraw_request_id
     * @return bool
     * @throws Exception
     */
    public function update_transfer_record($record_id, $withdraw_request_id)
    {
        $update['deposit_withdraw_id'] = $withdraw_request_id;
        $update['update_time'] = msectime();
        $save = Db::name('account_record')->where('id', $record_id)->update($update);
        Log::write("提现更新记录失败." . $record_id . 'adfasdf' . $withdraw_request_id, 'error');
        if ($save !== false) {
            return true;
        }
        Log::write("提现更新记录失败", 'error');
        throw new AppException(WITHDRAW_ERROR, lang('WITHDRAW_ERROR'));
    }

    /**
     * 获取币种交易记录
     *
     * @param $param
     * @param $user_info
     * @return array
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doAssetDetail($param, $user_info)
    {
//        $user_info['user_id'] = 31;

        $page = $this->get_page($param);
        $this->check_params($param, ['asset_code']);
        $user_id = $user_info['user_id'];
        $users = $this->getUserInfoByUserId($user_id);
//        $where_out = [
//            'from_user_id' => $user_id,
//            'asset_code' => $param['asset_code'],
//        ];
//
//        $where_in = [
//            'to_user_id' => $user_id,
//            'asset_code' => $param['asset_code'],
//        ];
//
//        $out_sql = Db::name('account_record')->where($where_out)->buildSql();
//        // 如果是dg就不分页了,直接查询所有
//        if ($param['asset_code'] == DG) {
////            $start = strtotime(date('Y-m-d', time()));
////            $end = strtotime(date('Y-m-d', strtotime('+1 day'))) - 1;
////            $where1['create_time'] = ['between time', [$start, $end]];
//            $in_out_info = Db::name('account_record')->where($where_in)->union([$out_sql])->order('create_time desc')->select();
//        } else {
//            $in_out_info = Db::name('account_record')->where($where_in)->union([$out_sql])->order('create_time desc')->limit($page['m'], $page['n'])->select();
//        }

//        $where_out = [
//            'from_user_id' => $user_id,
//            'asset_code' => $param['asset_code'],
//        ];
//
//        $where_in = [
//            'to_user_id' => $user_id,
//            'asset_code' => $param['asset_code'],
//        ];

        $where_str = '(`from_user_id` = ' . $user_id .' OR `to_user_id` = ' . $user_id . ') AND `asset_code` = ' . "'".$param['asset_code']."'";
        // 如果是dg就不分页了,直接查询所有
        if ($param['asset_code'] == DG) {
            $in_out_info = Db::name('account_record')->where($where_str)->order('create_time desc')->select();
        } else {
            $in_out_info = Db::name('account_record')->where($where_str)->order('create_time desc')->limit($page['m'], $page['n'])->select();
        }

        if (empty($in_out_info)) {
            return [];
        }

        $withdraw_id = [];
        $deposit_id = [];
        foreach ($in_out_info as $item1) {
            if ($item1['operation_type'] == 4) { // 提现
                $withdraw_id[] = $item1['deposit_withdraw_id'];
            }
            if ($item1['operation_type'] == 3) { // 充值
                $deposit_id[] = $item1['deposit_withdraw_id'];
            }
        }

        $withdraw_info_new = [];
        if (!empty($withdraw_id)) {
            $withdraw_info = Db::name('withdraw_request')->field('id, withdraw_status')->whereIn('id', $withdraw_id)->select();
            foreach ($withdraw_info as $item2) {
                $withdraw_info_new[$item2['id']]['withdraw_status'] = $item2['withdraw_status'];
            }
        }

        $deposit_info_new = [];
        if (!empty($deposit_id)) {
            $deposit_info = Db::name('deposit_request')->field('id, deposit_status')->whereIn('id', $deposit_id)->select();
            foreach ($deposit_info as $item2) {
                $deposit_info_new[$item2['id']]['deposit_status'] = $item2['deposit_status'];
            }
        }

        // 处理dg返回数据,当为团长时,2,3,4,5,6级好友的奖励记录只显示一条
        if ($param['asset_code'] == DG) {
            $dg_23456 = [8, 15 , 16, 17, 18];
            $dg_result = [];
            $dg_23456_one = [];
            foreach ($in_out_info as $k => $v) {
                if (in_array($v['operation_type'], $dg_23456)){
                    $create_date = date('Y-m-d', intval($v['create_time'] / 1000));
                    $dg_23456_one[$create_date][] = $v;
                } else {
                    $dg_result[] = $v;
                }
            }
            // 处理带日期的数据
            $insert_data = [];
            $dg_amount_arr = [];
            foreach ($dg_23456_one as $vk => $va) {
                $dg_amount_arr[$vk]['amount'] = 0;
                foreach ($va as $vk1 => $va1) {
                    // 取第一条作为插入数据
                    if ($vk1 == 0) {
                        $insert_data[$vk] = $va1;
                    }
                    $dg_amount_arr[$vk]['amount'] += $va1['amount'];
                }
            }

            foreach ($insert_data as $insert_key => &$insert_datum) {
                $insert_datum['amount'] = $dg_amount_arr[$insert_key]['amount'];
                $insert_datum['operation_type'] = 111;// 二级以后好友合并
            }
            $insert_data = array_values($insert_data);
            $in_out_info = array_merge($dg_result, $insert_data);
            // 排序
            $in_out_info = sortArrByField($in_out_info, 'create_time');
        }

        $result = [];
        foreach ($in_out_info as $key => $item) {
            $user_tmp = [];
            // 只有dgv才有用户之间的转账
            if ($item['from_user_id'] == $user_id) {
                $result[$key]['in_out'] = 'out';
                if ($item['to_user_id'] != 0 && $item['asset_code'] == DGV) {
                    $user_tmp = Db::name('user')->field('nickname, mobile, auth_level')->where('user_id', $item['to_user_id'])->find();
                }
            }

            if ($item['to_user_id'] == $user_id) {
                $result[$key]['in_out'] = 'in';
                if ($item['from_user_id'] != 0 && $item['asset_code'] == DGV) {
                    $user_tmp = Db::name('user')->field('nickname, mobile, auth_level')->where('user_id', $item['from_user_id'])->find();
                }
            }

            if (!empty($user_tmp)) {
                $result[$key]['nickname'] = $user_tmp['nickname'];
                $result[$key]['mobile'] = $user_tmp['mobile'];
            }

            if (!empty($withdraw_info_new) && isset($withdraw_info_new[$item['deposit_withdraw_id']])) {
                $result[$key]['withdraw_status'] = $withdraw_info_new[$item['deposit_withdraw_id']]['withdraw_status'];
            }

            if (!empty($deposit_info_new) && isset($deposit_info_new[$item['deposit_withdraw_id']])) {
                $result[$key]['deposit_status'] = $deposit_info_new[$item['deposit_withdraw_id']]['deposit_status'];
            }
            // 判断操作类型
            $operation_desc = $this->operation_desc($item['operation_type'], $users['auth_level']);
            $result[$key]['amount'] =  (string)floatval($item['amount']);
            $result[$key]['asset_code'] = $item['asset_code'];
            $result[$key]['operation_type'] = $item['operation_type'];
            $result[$key]['operation_desc'] = $operation_desc;
            $result[$key]['create_time'] = $item['create_time'];
            $result[$key]['id'] = $item['id'];
        }
        return $result;
    }

    /**
     * 获取充值信息
     *
     * @param $user_info
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doDepositInfo($user_info)
    {
        $user_id = $user_info['user_id'];
        $users = $this->getUserInfoByUserId($user_id);
        $result['user_code_invite'] = 'v' . $users['user_code_invite'] . 'v';
        $result['deposit_address'] = DEPOSIT_ADDRESS;
        return $result;
    }

    /**
     * 返回操作类型描述
     *
     * @param $operation_type
     * @param $auth_level
     * @return string
     */
    public function operation_desc($operation_type, $auth_level)
    {
        $operation_desc = "";
        switch ($operation_type) {
            case 1:
                $operation_desc = "转账";
                break;
            case 2:
                $operation_desc = "";
                break;
            case 3:
                $operation_desc = "充币";
                break;
            case 4:
                $operation_desc = "提币";
                break;
            case 8:
            case 7:
            case 5:
            case 15:
            case 16:
            case 17:
            case 18:
            case 19:
                $operation_desc = "好友挖矿奖励";
                if ($auth_level == 1) {
                    $operation_desc = "社区挖矿奖励";
                }
                break;
            case 6:
                $operation_desc = "注册奖励";
                break;
            case 10:
                $operation_desc = "DGV兑换DG获得";
                break;
            case 9:
                $operation_desc = "DGV兑换DG消耗";
                break;
            case 11:
                $operation_desc = "当日挖矿DG";
                break;
            case 13:
                $operation_desc = "DG与DGV进行POT奖励";
                break;
            case 12:
                $operation_desc = "DG与DGV进行POT消耗";
                break;
            case 14:
                $operation_desc = "当日未POT销毁DG";
                break;
            case 20:
                $operation_desc = "当日解锁DGV";
                break;
            case 21:
                $operation_desc = "参与锁仓DGV";
                break;
            case 111:
                $operation_desc = "社区挖矿奖励";
                break;
        }
        return $operation_desc;
    }

    /**
     * 冻结DGV
     *
     * @param $param
     * @param $user_info
     * @return int
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public function doFrozenDgv($param, $user_info)
    {
//        $user_info['user_id'] = 1;
        $user_id = $user_info['user_id'];
        $this->check_params($param, ['amount', 'safety_password']);
        // 判断金额,
        $amount = $this->do_amount($param['amount']);
        // 判断用户安全码是否正确
        $this->safety_password_right($user_id, $param['safety_password']);
        // 获取自己账户的余额是否充足
        $this->getUserAssetCodeAmount($user_id, DGV, $amount);
        $count = $this->available_to_frozen($user_id, DGV, $param['amount']);
        // 开启事务
        Db::startTrans();
        if ($count == 0) {
            Db::rollback();
            Log::write("用户[$user_id],冻结dgv失败", 'error');
            throw new AppException(FROZEN_DGV_ERROR, lang('FROZEN_DGV_ERROR'));
        }
        // 写入记录
        $record_id = $this->create_transfer_record($user_id, 0, DGV, $amount, 21, "手动锁仓");// 21=参与锁仓DGV
        if ($record_id == 0) {
            Db::rollback();
            Log::write("用户[$user_id],冻结dgv失败", 'error');
            throw new AppException(FROZEN_DGV_ERROR, lang('FROZEN_DGV_ERROR'));
        }
        Db::commit();
        $result['amount'] = $amount;
        return $result;
    }
}