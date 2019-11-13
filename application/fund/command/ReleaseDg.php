<?php

namespace app\fund\command;

use app\common\exception\AppException;
use app\common\model\BaseCommonModel;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Log;

class ReleaseDg extends Command
{
    // php think sending release-dg

    // 每天凌晨释放锁仓字段里的 1% 到可用余额 a , 同时查找DGV的锁仓金额 b , 使用 b - a 得到一个值 c
    // 每天释放的时候判断一次c，判断完就立马变为可用
    protected function configure()
    {
        $this->setName('sending')
            ->addArgument('cmd', Argument::OPTIONAL, "options:" . PHP_EOL . "release-dg")
            ->setDescription('每天凌晨释放锁仓的dg币,到可用余额');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function execute(Input $input, Output $output)
    {
        $cmd = trim($input->getArgument('cmd'));
        if ($cmd == 'release-dg') {
            $model = new BaseCommonModel();
            $this->release_dg($output, $model);
            $this->release_dgv($output, $model);
        }
    }


    /**
     * 释放dg
     *
     * @param Output $output
     * @param BaseCommonModel $model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function release_dg(Output $output, BaseCommonModel $model)
    {
        // 查询所有冻结金额
        $user_account = Db::name('user_account')->field('user_id, amount_available, amount_frozen')->where('asset_code', DG)->select();
        if (empty($user_account)) {
            $output->writeln("没有需要释放的DG用户");
            return;
        }
        $output->writeln(date('Y-m-d H:i:s') . "=>释放dg处理开始");


        // 拿到所有用户user_id
        $user_ids = [];
        foreach ($user_account as $item1) {
            $user_ids[] = $item1['user_id'];
        }

        // 查询发送记录
        $send_user_ids = $this->select_send($user_ids);
        if (!empty($send_user_ids)) {
            $user_ids = array_values(array_filter(array_diff($user_ids, $send_user_ids)));
        }

        // 查询每天释放的比例
        $release_rate = Db::name('profile')->where('profile_key', RELEASE_RATE)->value('profile_value');

        // 查询好友推荐关系表
        $friends_relation = Db::name('friends_relation')->field('user_id, user_ids')->whereNotNull('user_ids')->whereIn('user_id', $user_ids)->select();

        $user_friends = [];
        foreach ($friends_relation as $index) {
            // 查询用户关系
            $user_arr = explode(',', $index['user_ids']);
            // 保持where in 顺序不变
            $exp = "FIELD(user_id, " . $index['user_ids'] . ")";
            $user_friends[$index['user_id']] = Db::name('user')->field('user_id, auth_level')->whereIn('user_id', $user_arr)->orderRaw($exp)->select();
        }

        // 遍历发送奖励
        foreach ($user_account as $item) {
            if (!in_array($item['user_id'], $user_ids)) {
                $output->writeln(date('Y-m-d', time()) . "=>处理用户[" . $item['user_id'] . "],今日已经处理.");
                continue;
            }
            $this->do_release_dg($item, $release_rate, $output, $model, $user_friends);
        }

        $output->writeln(date('Y-m-d H:i:s') . "=>释放dg处理完");
    }

    /**
     * 释放dgv
     *
     * @param Output $output
     * @param BaseCommonModel $model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function release_dgv(Output $output, BaseCommonModel $model)
    {
        // 查询所有冻结金额
        $user_account = Db::name('user_account')->field('user_id, asset_code, amount_available, amount_frozen')->select();
        if (empty($user_account)) {
            $output->writeln("没有需要释放的DGV用户");
            return;
        }
        $output->writeln(date('Y-m-d H:i:s') . "=>释放DGV处理开始");


        // 拿到所有用户user_id
        $user_ids = [];
        foreach ($user_account as $item1) {
            $user_ids[] = $item1['user_id'];
        }
        // 去重
        $user_ids = array_unique($user_ids);
        // 查询发送记录
        $send_user_ids = $this->select_send($user_ids, 'release_dgv');
        if (!empty($send_user_ids)) {
            $user_ids = array_values(array_filter(array_diff($user_ids, $send_user_ids)));
        }

        $result = [];
        foreach ($user_account as $item) {
            if (!in_array($item['user_id'], $user_ids)) {
                $output->writeln(date('Y-m-d', time()) . "=>释放dgv处理用户[" . $item['user_id'] . "],今日已经处理.");
                continue;
            }
            // 拿到user_id,dgv冻结额度,dg的可用额度
            $result[$item['user_id']]['user_id'] = $item['user_id'];
            if ($item['asset_code'] == DGV) {
                $result[$item['user_id']]['dgv_amount_frozen'] = $item['amount_frozen'];
            } else if ($item['asset_code'] == DG) {
                $result[$item['user_id']]['dg_amount_available'] = $item['amount_available'];
            }
        }

        if (empty($result)) {
            $output->writeln(date('Y-m-d', time()) . "=>释放dgv已经处理完毕.");
            return;
        }

        // 释放dgv
        foreach ($result as $value) {
            $this->do_release_dgv($value['user_id'], $value['dgv_amount_frozen'], $value['dg_amount_available'], $output, $model);
        }

        $output->writeln(date('Y-m-d H:i:s') . "=>释放dgv处理完");
    }

    /**
     * 发送奖励
     *
     * @param $info
     * @param $release_rate
     * @param Output $output
     * @param BaseCommonModel $model
     * @param $user_friends
     * @return bool|int
     */
    private function do_release_dg($info, $release_rate, Output $output, BaseCommonModel $model, $user_friends)
    {
        $user_id = $info['user_id'];

        // 发送奖励 开启事务
        Db::startTrans();
        try {
            // 先归零dg的可用账户
            if ($info['amount_available'] > 0) {
                $this->set_money_dg($user_id, $info['amount_available'], $model);
            }
            // 释放冻结dg到可用账户
            $dg_amount = $info['amount_frozen'] * $release_rate;
            // 释放dg最小金额
            if ($dg_amount < MIN_AMOUNT_CHANGE) {
                Db::commit();
                $output->writeln("处理用户[$user_id],释放DG金额小于0.0001=>$dg_amount");
                return 1;
            }

            $model->frozen_to_available($user_id, DG, $dg_amount);
            $model->create_transfer_record(0, $user_id, DG, $dg_amount, 11, '当日解锁DG,挖矿释放比例[' . $release_rate . "],释放之前冻结金额是[" . $info['amount_frozen'] . "]");

            // 添加发送记录
            $this->add_release_dg_log($user_id, $dg_amount);

            // 给邀请人发释放奖励
            $this->send_friends($user_id, $dg_amount, $user_friends, $output, $model);

            Db::commit();
            $output->writeln(date('Y-m-d', time()) . "->处理用户[$user_id],释放DG金额=>$dg_amount");
            return 1;
        } catch (\Exception $e) {
            Db::rollback();
            $output->writeln("用户id:[$user_id], 释放dg失败, 失败信息[" . $e->getMessage() . "], 'error'");
        }
    }

    /**
     * 添加发送记录
     *
     * @param $user_id
     * @param $amount
     */
    private function add_release_dg_log($user_id, $amount)
    {
        $insert_data = [
            'user_id' => $user_id,
            'amount' => $amount,
            'create_time' => msectime(),
            'create_date' => date('Y-m-d'),
        ];
        Db::name('release_dg')->insertGetId($insert_data);
    }

    /**
     * 查询今天是否已经发送过
     *
     * @param $user_ids
     * @param string $table
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function select_send($user_ids, $table = 'release_dg')
    {
        $is_send = Db::name($table)->whereIn('user_id', $user_ids)->where('create_date', date('Y-m-d'))->select();
        if (empty($is_send)) {
            return [];
        }

        $send_user_ids = [];
        foreach ($is_send as $item) {
            $send_user_ids[] = $item['user_id'];
        }
        return $send_user_ids;
    }

    /**
     * 设置DG币归零
     *
     * @param $user_id
     * @param $amount
     * @param BaseCommonModel $model
     * @throws Exception
     */
    public function set_money_dg($user_id, $amount, BaseCommonModel $model)
    {
        $where['user_id'] = $user_id;
        $where['asset_code'] = DG;

        $data['amount_available'] = $amount;
        Db::name('user_account')->where($where)->setDec('amount_available', $amount);

        $model->create_transfer_record($user_id, 0, DG, $amount, 14, '当日未兑利销毁DG');
    }

    /**
     * 给邀请人发奖励
     *
     * @param $user_id
     * @param $dg_amount
     * @param $user_friends
     * @param Output $output
     * @param BaseCommonModel $model
     * @throws Exception
     */
    private function send_friends($user_id, $dg_amount, $user_friends, Output $output, BaseCommonModel $model)
    {
        if (!isset($user_friends[$user_id])) {
            return;
        }

        // (在记录返回时处理, 不在这里处理了)
        // 未成为社区:一级好友,没人一条记录,
        // 成为社区:一级好友,每人一条记录, 二级以后合并成一条记录

        $user_invite_info = $user_friends[$user_id];
        // 如果该用户不是团长,则发到是团长的邀请人结束
        foreach ($user_invite_info as $key => $value) {
            if ($value['auth_level'] == 1) { // 是群主,加1,2,3,4,5,6级好友
                $this->send_reward($value['user_id'], $key, $dg_amount, $model, $output);
                // 如果是群主,直接停止,不发了
                break;
            } else if ($value['auth_level'] == 100 && $key == 0) { // 只加一级好友
                $amount_available = $dg_amount * NOT_GROUP_REWARD_RATE;
                $model->add_money($value['user_id'], DG, $amount_available);
                $model->create_transfer_record(0, $value['user_id'], DG, $amount_available, 7, $remark = '1级好友推荐奖励');
            }
        }
        return;
    }

    /**
     * 加钱
     *
     * @param $user_id
     * @param $key
     * @param $reward_dg_amount
     * @param BaseCommonModel $model
     * @param Output $output
     * @throws Exception
     */
    private function send_reward($user_id, $key, $reward_dg_amount, BaseCommonModel $model, Output $output)
    {
        $result = $this->invite_reward_rate($key);
        $amount_frozen = $reward_dg_amount * $result['rate'];
        if ($amount_frozen < MIN_AMOUNT_CHANGE) {
            $output->writeln("处理用户[$user_id],释放DG金额太小,给邀请人发送的金额太小,就不给邀请人发了=>$amount_frozen");
            return;
        }

        // 发放到可用账户
        $model->add_money($user_id, DG, $amount_frozen);
        $model->create_transfer_record(0, $user_id, DG, $amount_frozen, $result['operation_type'], $remark = $key + 1 . '推荐奖励(好友)-释放金额是[' . $reward_dg_amount . '], 奖励汇率是[' . $result['rate'] . ']');
    }


    /**
     * 好友推荐级别,奖励比例
     *
     * @param $key
     * @return float|int
     */
    private function invite_reward_rate($key)
    {
        switch ($key) {
            case 0:
                $rate = GROUP_REWARD_RATE;
                $operation_type = 19;
                break;
            case 1:
                $rate = TWO_FRIEND;
                $operation_type = 8;
                break;
            case 2:
                $rate = THREE_FRIEND;
                $operation_type = 15;
                break;
            case 3:
                $rate = FOUR_FRIEND;
                $operation_type = 16;
                break;
            case 4:
                $rate = FIVE_FRIEND;
                $operation_type = 17;
                break;
            case 5:
                $rate = SIX_FRIEND;
                $operation_type = 18;
                break;
            default:
                $rate = 0;
                $operation_type = 5;
        }
        $result['rate'] = $rate;
        $result['operation_type'] = $operation_type;
        return $result;
    }

    /**
     * 处理释放dgv
     * @param $user_id
     * @param $dgv_amount_frozen
     * @param $dg_amount_available
     * @param Output $output
     * @param BaseCommonModel $model
     */
    private function do_release_dgv($user_id, $dgv_amount_frozen, $dg_amount_available, Output $output, BaseCommonModel $model)
    {
        // 判断
        $release_dgv_amount = $dgv_amount_frozen - $dg_amount_available;
        $output->writeln(date('Y-m-d', time()) . "->处理用户[$user_id],DG可用额度=>[$dg_amount_available], 冻结的DGV金额是[$dgv_amount_frozen]");
        // 释放, 冻结dgv小于最小操作的额度
        if ($release_dgv_amount < MIN_AMOUNT_CHANGE) {
            $this->add_release_dgv_log($user_id, 0);
            $output->writeln(date('Y-m-d', time()) . "->处理用户[$user_id], 释放的DGV额度是[$release_dgv_amount], 释放额度太小,跳过");
            return;
        }
        // 开始事务
        Db::startTrans();
        try {
            $model->frozen_to_available($user_id, DGV, $release_dgv_amount);
            $model->create_transfer_record(0, $user_id, DGV, $release_dgv_amount, 20, '当日解锁DGV,' . "DG可用额度=>[$dg_amount_available], 冻结的DGV金额是[$dgv_amount_frozen]");
            $this->add_release_dgv_log($user_id, $release_dgv_amount);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $output->writeln("用户id:[$user_id], 释放dgv失败, 失败信息[" . $e->getMessage() . "], 'error'");
        }
    }

    /**
     * 添加发送记录
     *
     * @param $user_id
     * @param $amount
     */
    private function add_release_dgv_log($user_id, $amount)
    {
        $insert_data = [
            'user_id' => $user_id,
            'amount' => $amount,
            'create_time' => msectime(),
            'create_date' => date('Y-m-d'),
        ];
        Db::name('release_dgv')->insertGetId($insert_data);
    }
}