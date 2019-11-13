<?php
namespace app\index\model;

use app\common\model\BaseCommonModel;
use code\InviteCode;
use think\Exception;
use think\Db;
use think\Log;
use app\common\exception\AppException;

class CommonModel extends BaseCommonModel
{
    /**
     * 生成用户唯一邀请码
     *
     * @param $user_id
     * @return string
     */
    public function get_invite_code($user_id)
    {
        $invite_code = new InviteCode();
        $code = $invite_code->id2Code($user_id);
        return $code;
    }

    /**
     * 比较注册手机号,和发送验证码手机号是否一致
     *
     * @param $send_code_mobile
     * @param $register_mobile
     * @return bool
     * @throws AppException
     */
    public function compare_mobile($send_code_mobile, $register_mobile)
    {
        if ($send_code_mobile != $register_mobile) {
            Log::write("注册手机号[$register_mobile]和发送验证码手机号[$send_code_mobile]不一致", 'error');
            throw new AppException(REGISTER_MOBILE_SEND_CODE_MOBILE_DIFFERENT, lang('REGISTER_MOBILE_SEND_CODE_MOBILE_DIFFERENT'));
        }
        return true;
    }

    /**
     * 更新密码/安全码
     *
     * @param $user_id
     * @param $password
     * @param string $modify_type
     * @throws Exception
     */
    public function update_password($user_id, $password, $modify_type = 'login')
    {
        $salt = create_uuid();
        $update_data = [
            $modify_type . '_salt' => $salt,
            $modify_type . '_password' => md5($password . $salt),
            'update_time' => msectime(),
        ];
        Db::name('user')->where('user_id', $user_id)->update($update_data);
    }

    /**
     * 判断安全码和密码是否一样
     *
     * @param $password
     * @param $user_info
     * @param string $type
     * @return bool
     * @throws AppException
     */
    public function login_safety_password($password, $user_info, $type = 'login')
    {
        $md5 = md5($password . $user_info[$type . '_salt']);
        $user_password = $user_info[$type . '_password'];
        if ($md5 == $user_password) {
            Log::write("登录密码和安全码不能一样", 'error');
            throw new AppException(LOGIN_PASSWORD_SAFETY_PASSWORD_EQUAL, lang('LOGIN_PASSWORD_SAFETY_PASSWORD_EQUAL'));
        }
        return true;
    }

}