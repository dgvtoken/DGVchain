<?php

namespace app\fund\model;

use app\common\model\BaseCommonModel;
use PDOStatement;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Db;
use think\exception\DbException;
use think\Log;
use app\common\exception\AppException;
use think\Model;

class CommonModel extends BaseCommonModel
{
    /**
     * 判断用户某个币种余额是否充足
     *
     * @param $user_id
     * @param $asset_code
     * @param $amount
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUserAssetCodeAmount($user_id, $asset_code, $amount)
    {
        $user_account = $this->get_user_account($user_id, $asset_code);
        if (empty($user_account)) {
            Log::write("用户id:[$user_id], 币种[$asset_code],暂无该币种信息", 'error');
            throw new AppException(USER_ACCOUNT_ASSET_CODE_ERROR, lang('USER_ACCOUNT_ASSET_CODE_ERROR'));
        }

        if ($user_account['account_status'] != 'OK') {
            Log::write("用户id:[$user_id], 币种[$asset_code]账户已经被冻结", 'error');
            throw new AppException(USER_ACCOUNT_FROZEN, lang('USER_ACCOUNT_FROZEN'));
        }

        // 比较可用余额
        if ($amount > $user_account['amount_available']) {
            Log::write("用户id:[$user_id], 币种[$asset_code]账户余额[" . $user_account['amount_available'] . "]不足", 'error');
            throw new AppException(USER_ACCOUNT_NOT_ENOUGH, lang('USER_ACCOUNT_NOT_ENOUGH'));
        }
    }

    /**
     * 获取某个币种的详情
     *
     * @param $user_id
     * @param $asset_code
     * @return array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function get_user_account($user_id, $asset_code)
    {
        $where = [
            'user_id' => $user_id,
            'asset_code' => $asset_code,
        ];
        $user_account = Db::name('user_account')->where($where)->find();
        return $user_account;
    }

    /**
     *  转账
     *
     * @param $user_id
     * @param $to_user_id
     * @param $asset_code
     * @param $amount
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */

    public function user_transfer($user_id, $to_user_id, $asset_code, $amount)
    {
        $where['user_id'] = $to_user_id;
        $where['asset_code'] = $asset_code;
        // 先查询$to_user_id 是否有该币种的账户
        $this->create_account($where);
        Db::startTrans();
        try {
            // 给$user_id账户-钱,$to_user_id+钱
            $this->add_money($to_user_id, $asset_code, $amount);
            $this->reduce_money($user_id, $asset_code, $amount);
            // 添加转账记录
            $this->create_transfer_record($user_id, $to_user_id, $asset_code, $amount, 1, '');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::write("转账人id:[$user_id], 币种[$asset_code], 被转账人[$to_user_id]失败, 失败信息[" . $e->getMessage() . "]", 'error');
            throw new AppException(ACCOUNT_TRANSFER_ERROR, lang('ACCOUNT_TRANSFER_ERROR'));
        }

    }

    /**
     * @param $user_id
     * @return int|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function get_user_recommend($user_id)
    {
        $user_info = $this->getUserInfoByUserId($user_id);
        $code_invite = $user_info['code_invite'];
        $recommend_info = $this->getUserInfoByCodeInvite($code_invite);
        if (empty($recommend_info)) {
            return 0;
        }
        return $recommend_info['user_id'];
    }

    /**
     * 获取用户账户操作记录
     *
     * @param $user_id
     * @param $asset_code
     * @param array $where
     * @return false|PDOStatement|string|Collection
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function get_account_record($user_id, $asset_code, $where = [])
    {
        $where['user_id'] = $user_id;
        $where['asset_code'] = $asset_code;
        $account_record = Db::name('account_record')->where($where)->order('create_time')->select();
        return $account_record;
    }
}