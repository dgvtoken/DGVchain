<?php

namespace app\index\model;

use PDOStatement;
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
     * 发送验证码
     * @param $param
     * @param $header
     * @return mixed
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function doSendSms($param, $header)
    {
        $this->check_params($param, ['mobile']);
        $type = isset($param['type']) ? $param['type'] : 1;
        $mobile = $param['mobile'];
        $this->is_mobile($mobile);
        $user_info = $this->getUserInfoByMobile($mobile);
        if ($type == 1) {
            // 去判断用户表里是否已经有了该用户
            if (!empty($user_info)) {
                Log::write("该手机号已经被绑定,手机号是:[$mobile]", 'error');
                throw new AppException(MOBILE_HAS_BIND, lang('Mobile has bind'));
            }
        } else if ($type != 4 && $type != 1) {
            if (!isset($header['token'])) {
                Log::write("缺少参数[token]", 'error');
                throw new AppException(PARAM_ERROR, lang('Param error')); // 参数错误
            }
            $this->getUserInfoByToken($header['token']);
            if (empty($user_info)) {
                Log::write("该手机号还没注册,手机号是:[$mobile]", 'error');
                throw new AppException(MOBILE_NOT_REGISTER, lang('MOBILE_NOT_REGISTER'));
            }
        }
        // 检测发验证码的频率 3min内只能发两次, 且每次发之前把历史未使用的验证码置为过期
        $this->send_rate($mobile, $type);
        $code = rand(1000, 9999);
        $info = $this->send_verify($mobile, $code);

        if (empty($info) || $info['error_code'] != 0) {
            Log::write("手机号:[$mobile], 发送验证码错误,错误信息是:" . json_encode($info), 'error');
            throw new AppException(SEND_VERIFICATION_CODE_ERROR, lang('SEND_VERIFICATION_CODE_ERROR')); // 参数错误
        }

        // 把code写入数据库
        $data['mobile'] = $mobile;
        $data['code'] = $code;
        $data['type'] = $type;
        $data['create_time'] = msectime();
        $data['update_time'] = 0;

        Db::name('verification_code')->insert($data);

        $result['mobile'] = $mobile;
        return $result;
    }

    /**
     * 检测用户请求验证码频率
     *
     * @param $mobile
     * @param $type
     * @return bool
     * @throws AppException
     * @throws Exception
     */
    private function send_rate($mobile, $type)
    {
        $where['mobile'] = $mobile;
        $where['type'] = $type;
        $where['used'] = 0;
        $time_diff = msectime() - VERIFICATION_CODE_EXPIRE_TIME;
        $code_info = Db::name('verification_code')->where($where)->where('create_time', '>', $time_diff)->order('create_time desc')->select();
        $count = count($code_info);
        if ($count >= 2) {
            Log::write("手机号:[$mobile], 请求太频繁,请稍后重试", 'error');
            throw new AppException(SEND_CODE_REQUEST_TOO_MANY, lang('Send code request too many')); // 参数错误
        }

        if ($count == 0 || $count == 1) {
            return true;
        }

        $data['used'] = 2;
        $data['update_time'] = msectime();
        foreach ($code_info as $item) {
            Db::name('verification_code')->where('id', $item['id'])->update($data);// 置为过期
        }

        return true;
    }

    /**
     * 聚合发送验证码
     *
     * @param $mobile
     * @param $code
     * @return mixed
     */
    private function send_verify($mobile, $code)
    {
        $url = "http://v.juhe.cn/sms/send";
        $params = array(
            'key' => JUHE_KEY, //您申请的APPKEY
            'mobile' => $mobile, //接受短信的用户手机号码
            'tpl_id' => JUHE_TPL_ID, //您申请的短信模板ID，根据实际情况修改
            'tpl_value' => "#code#=$code&#company#=聚合数据" //您设置的模板变量，根据实际情况修改
        );

        $param_string = http_build_query($params);
        $content = juheCurl($url, $param_string);
        $result = json_decode($content, true);
        return $result;
    }

    /**
     * 用户注册
     *
     * @param $param
     * @return mixed
     * @throws AppException
     * @throws Exception
     */
    public function doRegister($param)
    {
        $this->check_params($param, ['mobile', 'login_password', 're_login_password', 'safety_password', 're_safety_password', 'code_invite', 'code']);
        // 判断手机号是否合法
        $mobile = $param['mobile'];
        $this->is_mobile($mobile);
        $user_info = $this->getUserInfoByMobile($mobile);
        if (!empty($user_info)) {
            Log::write("该手机号已经被绑定,手机号是:[$mobile]", 'error');
            throw new AppException(MOBILE_HAS_BIND, lang('Mobile has bind'));
        }

        $this->check_password($param['login_password'], 32, 5);// 大于5位小于32位
        // 判断密码是否一样
        if ($param['login_password'] != $param['re_login_password']) {
            Log::write("手机号[$mobile]两次输入密码不一样,1次是[" . $param['login_password'] . "],2次是[" . $param['re_login_password'] . "]", 'error');
            throw new AppException(TWO_PASSWORDS_DIFFERENT, lang('TWO_PASSWORDS_DIFFERENT'));
        }

        $this->check_password($param['safety_password'], 32, 5);// 大于5位小于32位
        // 判断安全码是否一样
        if ($param['safety_password'] != $param['re_safety_password']) {
            Log::write("手机号[$mobile]两次输入安全码不一样,1次是[" . $param['safety_password'] . "],2次是[" . $param['re_safety_password'] . "]", 'error');
            throw new AppException(TWO_SAFETY_PASSWORDS_DIFFERENT, lang('TWO_SAFETY_PASSWORDS_DIFFERENT'));
        }

        if ($param['login_password'] == $param['safety_password']) {
            Log::write("手机号[$mobile],密码和安全码不能一样", 'error');
            throw new AppException(PASSWORD_SAFETY_PASSWORD_EQUALLY, lang('PASSWORD_SAFETY_PASSWORD_EQUALLY'));
        }

        // 验证验证码
        $type = 1;
        $code_info = $this->verification_code($mobile, $type, $param['code']);
        // 验证邀请码
        $invite_info = $this->getUserInfoByCodeInvite($param['code_invite']);
        if (empty($invite_info)) {
            Log::write("手机号[$mobile],邀请码[" . $param['code_invite'] . "]不存在", 'error');
            throw new AppException(INVITE_CODE_NOT_EXIST, lang('INVITE_CODE_NOT_EXIST'));
        }

        // 开始插入数据库
        $login_salt = create_uuid();
        $safety_salt = create_uuid();
        $insert_data = [
            'mobile' => $mobile,
            'login_salt' => $login_salt,
            'login_password' => md5($param['login_password'] . $login_salt),
            'safety_salt' => $safety_salt,
            'code_invite' => $param['code_invite'],
            'safety_password' => md5($param['safety_password'] . $safety_salt),
            'create_time' => msectime(),
            'update_time' => 0,
        ];
        // 加上事务吧
        Db::startTrans();
        try {
            $user_id = Db::name('user')->insertGetId($insert_data);
            // 生成自己的邀请码
            $code = $this->get_invite_code($user_id);
            $update_data1 = [
                'user_code_invite' => $code,
            ];
            Db::name('user')->where('user_id', $user_id)->update($update_data1);
//            Log::write("code:" . Db::name('user')->getLastSql(), "error");
            // 创建默认账户,并给推荐人发奖励
            $this->create_user_account($user_id, $invite_info);
            // 更改验证码状态
            $this->verification_code_update(msectime(), $code_info, 1);
            Db::commit();
            return 1;
        } catch (\Exception $e) {
            Db::rollback();
            Log::write("手机号[$mobile],注册失败, 失败信息[" . $e->getMessage() . "]", 'error');
            throw new AppException(REGISTER_ERROR, lang('REGISTER_ERROR'));
        }
    }

    /**
     * 登录
     *
     * @param $param
     * @return mixed
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doSignIn($param)
    {
        $this->check_params($param, ['mobile', 'login_password']);
        // 判断手机号是否合法
        $mobile = $param['mobile'];
        $this->is_mobile($mobile);
        $user_info = $this->getUserInfoByMobile($mobile);
        if (empty($user_info)) {
            Log::write("该手机号还没注册,手机号是:[$mobile]", 'error');
            throw new AppException(MOBILE_NOT_REGISTER, lang('MOBILE_NOT_REGISTER'));
        }
        $login_salt = $user_info['login_salt'];
        $login_password = md5($param['login_password'] . $login_salt);
        if ($login_password != $user_info['login_password']) {
            Log::write("密码不对,手机号是:[$mobile]", 'error');
            throw new AppException(PASSWORD_ERROR, lang('PASSWORD_ERROR'));
        }

        // 创建token
        $user_token['user_id'] = $user_info['user_id'];
        $user_token['mobile'] = $mobile;
        $token = $this->createJwt($user_token);
        $data['token'] = $token;
        return $data;
    }

    /**
     * 创建默认账户, 并发送奖励
     *
     * @param $user_id
     * @param $invite_info
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    private function create_user_account($user_id, $invite_info)
    {
        $insert_data1 = [
            'user_id' => $user_id,
            'asset_code' => DGV,
            'amount_available' => 0,
            'amount_frozen' => REGISTER_REWARD_DGV,
            'create_time' => msectime(),
            'update_time' => 0,
        ];

        $reward_dg_amount = REGISTER_REWARD_DGV_1K;
        if (date('Y-m-d', time()) == REWARD_DAY) {
            $reward_dg_amount = REGISTER_REWARD_DGV_1W;
        }

        $insert_data2 = [
            'user_id' => $user_id,
            'asset_code' => DG,
            'amount_available' => 0,
            'amount_frozen' => $reward_dg_amount,
            'create_time' => msectime(),
            'update_time' => 0,
        ];

        $data[] = $insert_data1;
        $data[] = $insert_data2;
        Db::name('user_account')->insertAll($data);
        // 获取推荐人id
        $invite_users = $this->get_invite_users($invite_info);
        array_unshift($invite_users, $invite_info);
        // 放到一个表中,方便发放奖励
        $this->user_friends_relation($user_id, $invite_users);
        // 发送奖励给邀请人(不在这操作了,定时任务发放)
//        $this->send_invite_users($invite_users, $reward_dg_amount);

        // 添加到记录表
//        $operation_type = 5; // 注册推荐奖励
        $from_user_id = 0;// 系统发放
//        $this->create_transfer_record($from_user_id, $invite_user_id, KOK, REGISTER_REWARD_INVITE_KOK, $operation_type, $remark = '注册推荐奖励');
        // 新用户注册奖励
        if (REGISTER_REWARD_DGV != 0) {
            $this->create_transfer_record($from_user_id, $user_id, DGV, REGISTER_REWARD_DGV, 6, $remark = '注册奖励');
        }

        if ($reward_dg_amount != 0) {
            $this->create_transfer_record($from_user_id, $user_id, DG, $reward_dg_amount, 6, $remark = '注册奖励');
        }
        // 更新邀请人数
        Db::name('user')->where('user_code_invite', $invite_info['user_code_invite'])->setInc('invite_num');
    }

    /**
     *  获取用户邀请好友(我的邀请)
     *
     * @param $param
     * @param $user_info
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public function doInviteUser($param, $user_info)
    {
        // 一级总矿池的意思是：下一级冻结DG的总和。
        // 社区总矿池的意思是：5级好友冻结DG的总和。
        // $user_info['user_id'] = 17;
        $user_id = $user_info['user_id'];
        $users = $this->getUserInfoByUserId($user_id);
        $user_code_invite = $users['user_code_invite'];
        // 获取一级好友的
        $one_info = Db::name('user')->field('user_id, mobile, user_code_invite, auth_level, create_time')->where('code_invite', $user_code_invite)->select();
        $one_total = count($one_info);// 一级好友总数

        $result['one_total'] = $one_total;
        $result['auth_level'] = $users['auth_level'];
        if ($one_total == 0) {
            $result['group_total'] = 0; // 团队总人数
            $result['one_reward'] = 0; // 一级总收益
            $result['over_15w'] = 0; // 超过15w人数
            $result['one_pool'] = 0; // 一级好友总冻结dg的和
            $result['all_pool'] = 0; // 5级好友冻结DG的总和
            $result['group_reward'] = 0; // 团队总收益
            return $result;
        }

        // 获取一级好友奖励
        $where_one = [
            'to_user_id' => $user_id,
            'asset_code' => DG,
        ];

        $one_reward = Db::name('account_record')->where($where_one)->where('operation_type', 7)->sum('amount');
        $group_code = [];
        $one_user_id = [];
        foreach ($one_info as &$item) {
            $one_user_id[] = $item['user_id'];
            $group_code[] = $item['user_code_invite'];
        }

        // 团队(社区)总人数(递归)
        $group_2_6_info = $this->user_recursion($group_code);
        $group_total = count($group_code) + $group_2_6_info['invite_users'];
        // 一级好友持币曾经超过15w(DG)
        $over_15w = Db::name('user_account')->whereIn('user_id', $one_user_id)->where('enough_money', 1)->where('asset_code', DG)->count('user_id');

        // 团队总收入
        $group_reward = 0;
        if ($users['auth_level'] == 1) {
            $group_reward = Db::name('account_record')->where('to_user_id', $user_id)->where('asset_code', DG)->whereIn('operation_type', [19, 8, 15, 16, 17, 18])->sum('amount');

        }
        $result['group_total'] = $group_total;
        $result['one_reward'] = $one_reward;
        $result['over_15w'] = $over_15w;
        $result['group_reward'] = $group_reward;

        // 一级好友的总冻结
        $one_pool = Db::name('user_account')->whereIn('user_id', $one_user_id)->where('asset_code', DG)->sum('amount_frozen');
        // 二级到6级
        $user_ids = array_merge($one_user_id, $group_2_6_info['user_ids']);
        $all_pool = Db::name('user_account')->whereIn('user_id', $user_ids)->where('asset_code', DG)->sum('amount_frozen');
        $result['one_pool'] = $one_pool; // 一级好友总冻结dg的和
        $result['all_pool'] = $all_pool; // 5级好友冻结DG的总和
        return $result;
    }

    /**
     * 递归查询6级好友人数
     *
     * @param $one_codes
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function user_recursion($one_codes)
    {
        $result = [];
        static $i = 0;
        static $invite_users = 0;
        static $user_ids = [];
        if ($i > UP_USER_LEVEL) {
            $result['invite_users'] = $invite_users;
            $result['user_ids'] = $user_ids;
            return $result;
        }
        $i++;

        $invite_user_info = Db::name('user')->field('user_code_invite, code_invite, user_id')->whereIn('code_invite', $one_codes)->select();
        if (empty($invite_user_info)) {
            $result['invite_users'] = $invite_users;
            $result['user_ids'] = $user_ids;
            return $result;
        }

        $group_code = [];
        foreach ($invite_user_info as $item) {
            $group_code[] = $item['user_code_invite'];
            $user_ids[] = $item['user_id'];
        }
        $user_no = count($group_code);
        $invite_users += $user_no;

        $this->user_recursion($group_code);

        $result['invite_users'] = $invite_users;
        $result['user_ids'] = $user_ids;
        return $result;
    }

    /**
     * 联系我们
     *
     * @param $param
     * @param $user_info
     * @return int
     * @throws AppException
     */
    public function doContactUs($param, $user_info)
    {
        $this->check_params($param, ['title', 'content']);
        $data['title'] = $param['title'];
        $data['content'] = $param['content'];
        $data['user_id'] = $user_info['user_id'];
        $data['create_time'] = msectime();
        $data['update_time'] = 0;
        Db::name('contact_us')->insert($data);
        return 1;
    }

    /**
     * @param $param
     * @return false|PDOStatement|string|Collection
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doProblemMessage($param)
    {
        $page = $this->get_page($param);
        $type = isset($param['content_type']) ? $param['content_type'] : 1;
        $result = Db::name('problem_message')->where('content_type', $type)->where('type', 1)->order('create_time desc')->limit($page['m'], $page['n'])->select();
        return $result;
    }

    /**
     * @param $param
     * @param $version
     * @return mixed
     * @throws AppException
     */
    public function doUpgradeCheck($param, $version)
    {
        $this->check_params($param, ['user_agent']);
        $user_agent = $param['user_agent'];
        if ($user_agent == 'android') {
            if ($version < VERSION_APP_ANDROID) {
                $payload['app_url'] = APP_URL_ANDROID;
                $payload['app_content'] = UPDATE_CONTENT;
                $payload['is_update'] = IS_UPDATE_ANDROID;
                return $payload;
            } else {
                Log::write("APP不需要更新android", 'error');
                throw new AppException(STATUS_NO_UPDATE, lang('STATUS_NO_UPDATE'));
            }
        } else {
            if ($version < VERSION_APP_IOS) {
                $payload['app_url'] = APP_URL_IOS;
                $payload['app_content'] = UPDATE_CONTENT;
                $payload['is_update'] = IS_UPDATE_IOS;
                return $payload;
            } else {
                Log::write("APP不需要更新ios", 'error');
                throw new AppException(STATUS_NO_UPDATE, lang('STATUS_NO_UPDATE'));
            }
        }
    }


    /**
     * 获取用户邀请好友列表
     *
     * @param $invite_info
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function get_invite_users(array $invite_info)
    {
        static $i = 0;
        static $invite_user_info = [];
        if ($i > UP_USER_LEVEL) {
            return $invite_user_info;
        }
        $i++;

        // $invite_info == 是上级好友
        // 开始找寻上上级好友user_id
        $up_user = $this->get_up_user($invite_info);
        if (empty($up_user)) {
            return $invite_user_info;
        }

        $invite_user_info[] = $up_user;

        $this->get_invite_users($up_user);

        return $invite_user_info;
    }

    /**
     * 返回上级好友信息
     *
     * @param $invite_info
     * @return array|false|PDOStatement|string|\think\Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function get_up_user(array $invite_info)
    {
        if (empty($invite_info)) {
            return [];
        }
        $up_user = Db::name('user')->field('user_id, code_invite, auth_level, user_code_invite')->where('user_code_invite', $invite_info['code_invite'])->find();
        return $up_user;
    }


    /**
     * 拿到好友推荐关系
     *
     * @param $user_id
     * @param $invite_info
     */
    private function user_friends_relation($user_id, $invite_info)
    {
        // 拿到所有用户user_id
        $user_ids = [];
        foreach ($invite_info as $item) {
            $user_ids[] = $item['user_id'];
        }

        $user_id_string = implode(",", $user_ids);
        $data = [
            'user_id' => $user_id,
            'user_ids' => $user_id_string,
            'create_time' => msectime(),
        ];

        Db::name('friends_relation')->insertGetId($data);
    }
}