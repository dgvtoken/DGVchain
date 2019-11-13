<?php

namespace app\common\model;

use PDOStatement;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Log;
use think\Model;
use app\common\exception\AppException;
use think\Db;
use Firebase\JWT\JWT;

class BaseCommonModel extends Model
{
    /**
     * 连接redis
     */
    public function getRedis()
    {
        $redis = new \Redis();
        //连接扩展
        $redis->connect("127.0.0.1", "6379");
        $redis->select(5);
        return $redis;
    }


    /**
     * 检测参数
     *
     * @param $params
     * @param array $check_array
     * @return array|false|object|PDOStatement|string|Model
     * @throws AppException
     */
    public function check_params($params, $check_array)
    {
        foreach ($check_array as $item) {
            if (!isset($params[$item])) {
                Log::write("参数不合规范,缺少参数[$item]", 'error');
                throw new AppException(PARAM_ERROR, lang('Param error'));
            }
        }
    }

    /**
     * 处理分页(其实使用page方法就解决了)
     * @param $param
     * @param $page_size
     * @return mixed
     */
    public function get_page($param, $page_size = 20)
    {
        $page = isset($param['page']) ? $param['page'] : 1;
        $page_size = isset($param['page_size']) ? $param['page_size'] : $page_size;
        $m = ($page - 1) * $page_size;
        $n = $page_size;

        $result['m'] = $m;
        $result['n'] = $n;

        return $result;
    }

    /**
     * 创建token, 并且把token放到redis中,利用redis来刷新token
     * @param $userInfo
     * @return string
     */
    public function createJwt($userInfo)
    {
        $key = md5(JWT_KEY); //jwt的签发密钥，验证token的时候需要用到
        $time = time(); //签发时间
        $expire = $time + JWT_EXPIRE_TIME; //过期时间
        $data = array(
            "user_info" => $userInfo,
            "iss" => "WDX",//签发组织
            "aud" => "WDX", //签发作者
            "iat" => $time,
            "nbf" => $time,
            "exp" => $expire
        );
        $token = JWT::encode($data, $key);
        return $token;
    }

    /**
     * 解析token
     * @param $token
     * @return object
     * @throws AppException
     */
    public function parseJwt($token)
    {
        $key = md5(JWT_KEY); //jwt的签发密钥，验证token的时候需要用到
        try {
            $user_info = JWT::decode($token, $key, array('HS256'));
            // 获取过期时间,和当前时间相比,如果小于24h,就给用户重新分配新token,大于当前时间,就提示用户登录
            $exp = $user_info->exp;
            if ($exp - time() < LIMIT_EXPIRE_TIME) {
                $users['user_id'] = $user_info->user_info->user_id;
                $users['mobile'] = $user_info->user_info->mobile;
                $refresh_token = $this->createJwt($users);
                $data['map']['refresh_token'] = $refresh_token;
                Log::write("老token,已经过期,重新分配新的", 'error');
                throw new AppException(REFRESH_TOKEN, lang('Refresh token'), $data);
            }
            return $user_info;
        } catch (\Exception $e) {
            Log::write("解析token失败或者已经过期,请重新登录", 'error');
            throw new AppException(PLEASE_LOGIN, lang('Please login'));
        }
    }

    /**
     * 检测字符串长度
     *不能纯数字,
     * @param $str
     * @param $high
     * @param $low
     * @throws AppException
     */
    public function check_password($str, $high, $low)
    {
        if (is_numeric($str) || strlen($str) > $high || strlen($str) < $low) {
            Log::write("字符串长度不合法[$str]", 'error');
            throw new AppException(STR_LENGTH_NOT_LEGAL, lang('STR_LENGTH_NOT_LEGAL'));
        }

        $rules = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9a-zA-Z]+$/';
        if (!preg_match($rules, $str)) {
            Log::write("密码不合法:[$str]", 'error');
            throw new AppException(STR_LENGTH_NOT_LEGAL, lang('STR_LENGTH_NOT_LEGAL'));
        }
    }

    /**
     * 检测token是否正确,以及获取用户信息
     *
     * @param $token
     * @return array|false|object|\PDOStatement|string|Model
     * @throws AppException
     */
    public function getUserInfoByToken($token)
    {
        $user_info = $this->parseJwt($token);
        if (empty($user_info)) {
            Log::write("Token失效,请重新登录", 'error');
            throw new AppException(PLEASE_LOGIN, lang('Please login'));
        }
        $user_info = json_decode(json_encode($user_info), true);
        return $user_info['user_info'];
    }

    /**
     * 验证手机验证码是否正确
     *
     * @param $mobile
     * @param $type
     * @param $code
     * @return array|false|\PDOStatement|string|Model
     * @throws AppException
     * @throws Exception
     */
    public function verification_code($mobile, $type, $code)
    {
        $where['mobile'] = $mobile;
        $where['type'] = $type;
        $where['used'] = 0;
        $code_info = Db::name('verification_code')->where($where)->order('create_time desc')->find();
        if (empty($code_info)) {
            Log::write("已发的验证码里,查不到该手机号码,手机号是:[$mobile]", 'error');
            throw new AppException(VERIFICATION_CODE_ERROR, lang('VERIFICATION_CODE_ERROR'));
        }

        if ($code != $code_info['code']) {
            Log::write("验证码不对,用户验证码是[$code], 数据库里的验证码是[" . $code_info['code'] . "],手机号是:[$mobile]", 'error');
            throw new AppException(VERIFICATION_CODE_ERROR, lang('VERIFICATION_CODE_ERROR'));
        }

        if (msectime() - $code_info['create_time'] > VERIFICATION_CODE_EXPIRE_TIME) {
            $used = 2;
            $this->verification_code_update(msectime(), $code_info, $used);

            Log::write("验证码已经过期, 请重新请求,用户验证码是[$code],手机号是:[$mobile]", 'error');
            throw new AppException(VERIFICATION_CODE_EXPIRE, lang('VERIFICATION_CODE_EXPIRE'));
        }
        return $code_info;
    }

    /**
     * 更新验证码使用状态,和上面的方法分开,是为了事务
     *
     * @param $time
     * @param $code_info
     * @param $used 0=正常使用 2=过期
     * @throws Exception
     */
    public function verification_code_update($time, $code_info, $used)
    {
        $data['used'] = $used;
        $data['update_time'] = $time;
        Db::name('verification_code')->where('id', $code_info['id'])->update($data);
    }

    /**
     * 根据用户id获取用户信息
     *
     * @param $user_id
     * @return array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getUserInfoByUserId($user_id)
    {
        $user_info = Db::name('user')->where('user_id', $user_id)->find();
        return $user_info;
    }

    /**
     * 处理用户个人信息
     *
     * @param $user_info
     * @return mixed
     */
    public function doUserInfo($user_info)
    {
        unset($user_info['user_id']);
        unset($user_info['login_password']);
        unset($user_info['login_salt']);
        unset($user_info['safety_password']);
        unset($user_info['safety_salt']);
        if ($user_info['avatar_url'] != '') {
            $user_info['avatar_url'] = DOMAIN . $user_info['avatar_url'];
        }
        return $user_info;
    }

    /**
     * 通过手机号获取用户信息
     *
     * @param $mobile
     * @return array|false|PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUserInfoByMobile($mobile)
    {
        $user_info = Db::name('user')->where('mobile', $mobile)->find();
        return $user_info;
    }

    /**
     * 验证手机号是否合法
     *
     * @param $mobile
     * @return bool
     * @throws AppException
     */
    public function is_mobile($mobile)
    {
        $rules = "/^((13[0-9])|(14[5,7])|(15[0-3,5-9])|(17[0-9])|(18[0-9])|166|(19[1,8-9]))\\d{8}$/A";
        $bool = preg_match($rules, $mobile);
        if (!$bool) {
            Log::write("手机号码不合法,手机号是:[$mobile]", 'error');
            throw new AppException(MOBILE_NOT_LEGAL, lang('MOBILE_NOT_LEGAL'));
        }
        return true;
    }

    /**
     * 检测eos账户是否合法
     * @param $eos_account
     * @return bool
     * @throws AppException
     */
    public function check_eos_account($eos_account)
    {
        $rules = "/(^[1-5]{12}$)|(^[a-z]{12}$)|(^[1-5a-z]{12}$)/";
        $bool = preg_match($rules, $eos_account);
        if (!$bool) {
            Log::write("eos账户不合法,eos账户是:[$eos_account]", 'error');
            throw new AppException(EOS_ACCOUNT_NOT_LEGAL, lang('EOS_ACCOUNT_NOT_LEGAL'));
        }
        return true;
    }

    /**
     * 判断转账金额是否合法
     *
     * @param $amount
     * @return mixed
     * @throws AppException
     */
    public function do_amount($amount)
    {
        if (!is_numeric($amount) || $amount == 0) {
            Log::write("转账金额格式不对,用户输入金额是:[$amount]", 'error');
            throw new AppException(AMOUNT_FORMAT_ERROR, lang('AMOUNT_FORMAT_ERROR'));
        }

        if (ceil($amount) == $amount)
            return $amount;
        $amount_zero = floatval($amount);
        // 只能小数点4位
        list($int, $float) = explode('.', $amount_zero);
        if (strlen($float) > 4 || $amount_zero < 0.0001) {
            Log::write("转账金额格式不对,只支持小数点后的4位,用户输入金额是:[$amount]", 'error');
            throw new AppException(AMOUNT_FORMAT_OVER_FOUR, lang('AMOUNT_FORMAT_OVER_FOUR'));
        }
        return $amount;
    }

    /**
     * 判断用户安全码是否正确
     *
     * @param $user_id
     * @param $input_safety_password
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function safety_password_right($user_id, $input_safety_password)
    {
        $user_info = $this->getUserInfoByUserId($user_id);
        $safety_salt = $user_info['safety_salt'];
        $input_safety = md5($input_safety_password . $safety_salt);
        if ($input_safety != $user_info['safety_password']) {
            Log::write("用户id:[$user_id], 输入的安全码不对", 'error');
            throw new AppException(SAFETY_PASSWORD_ERROR, lang('SAFETY_PASSWORD_ERROR'));
        }
    }

    /**
     * 通过邀请码获取用户信息
     *
     * @param $code_invite
     * @return array|false|\PDOStatement|string|Model
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUserInfoByCodeInvite($code_invite)
    {
        $user_info = Db::name('user')->field('code_invite, user_id, auth_level, user_code_invite')->where('user_code_invite', $code_invite)->find();
        return $user_info;
    }

    /**
     * 添加转账记录
     *
     * @param $user_id
     * @param $to_user_id
     * @param $asset_code
     * @param $amount
     * @param $operation_type
     * @param $remark
     * @return int|string
     */
    public function create_transfer_record($user_id, $to_user_id, $asset_code, $amount, $operation_type, $remark = '')
    {
        $insert_data = [
            'from_user_id' => $user_id,
            'to_user_id' => $to_user_id,
            'amount' => $amount,
            'asset_code' => $asset_code,
            'operation_type' => $operation_type,
            'remark' => $remark,
            'create_time' => msectime(),
            'update_time' => 0,
        ];
        $record_id = Db::name('account_record')->insertGetId($insert_data);
        return $record_id;
    }

    /**
     * 给用户+钱
     * @param $user_id
     * @param $asset_code
     * @param $amount
     * @param $field
     * @throws Exception
     */
    public function add_money($user_id, $asset_code, $amount, $field = 'amount_available')
    {
        $where['user_id'] = $user_id;
        $where['asset_code'] = $asset_code;
        Db::name('user_account')->where($where)->setInc($field, $amount);
        if ($asset_code == DG) {
            $this->user_now_amount($where);
        }
    }

    /**
     * DG 币超过5w记录一下
     *
     * @param $where
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @throws PDOException
     */
    public function user_now_amount($where)
    {
        // 插询用户当前账户余额
        $dg_amount_info = Db::name('user_account')->where($where)->find();
        if ($dg_amount_info['enough_money'] == 1) {
            return;
        }

        $dg_amount = $dg_amount_info['amount_available'] + $dg_amount_info['amount_frozen'];
        if ($dg_amount >= FRIENDS_DG_AMOUNT) {
            $update_data['enough_money'] = 1;
            $update_data['update_time'] = msectime();
            Db::name('user_account')->where($where)->update($update_data);
        }
    }


    /**
     * 给用户-钱
     * @param $user_id
     * @param $asset_code
     * @param $amount
     * @param $field
     * @throws Exception
     */
    public function reduce_money($user_id, $asset_code, $amount, $field = 'amount_available')
    {
        $where['user_id'] = $user_id;
        $where['asset_code'] = $asset_code;
        Db::name('user_account')->where($where)->setDec($field, $amount);
    }

    /**
     * @param $user_id
     * @param $asset_code
     * @param $amount
     * @return mixed
     */
    public function available_to_frozen($user_id, $asset_code, $amount)
    {
        $update_time = msectime();
        $sql = sprintf("UPDATE dgv_user_account SET amount_available = amount_available - %f, amount_frozen = amount_frozen + %f, update_time = %s WHERE user_id = %d AND asset_code = '%s' ", $amount, $amount, $update_time, $user_id, $asset_code);
        $count = Db::execute($sql);
        return $count;
    }

    /**
     * @param $user_id
     * @param $asset_code
     * @param $amount
     * @return mixed
     */
    public function frozen_to_available($user_id, $asset_code, $amount)
    {
        $update_time = msectime();
        $sql = sprintf("UPDATE dgv_user_account SET amount_frozen = amount_frozen - %f, amount_available = amount_available + %f, update_time = %s WHERE user_id = %d AND asset_code = '%s' ", $amount, $amount, $update_time, $user_id, $asset_code);
        $count = Db::execute($sql);
        return $count;
    }

    /**
     * 创建用户资金账户
     * @param $where
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function create_account($where)
    {
        $user_account = Db::name('user_account')->where($where)->find();
        if (!empty($user_account)) {
            return true;
        }

        $insert_data = [
            'user_id' => $where['user_id'],
            'asset_code' => $where['asset_code'],
            'amount_available' => 0,
            'amount_frozen' => 0,
            'create_time' => msectime(),
            'update_time' => 0,
        ];
        Db::name('user_account')->insert($insert_data);
    }

    /**
     * 上传文件
     * @param $avatar
     * @param $name
     * @return string
     * @throws AppException
     */
    public function upload_image($avatar, $name = 'image')
    {
        $file = request()->file($name);
        if (!$file) {
            return '';
        }

        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads/' . $avatar);

        if ($info) {
            return '/uploads/' . $avatar . '/' . $info->getSaveName();
        } else {
            Log::write("上传文件失败=>", 'error');
            throw new AppException(UPLOAD_AVATAR_ERROR, lang('UPLOAD_AVATAR_ERROR'));
        }
    }
}