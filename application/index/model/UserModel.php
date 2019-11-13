<?php

namespace app\index\model;

use app\common\exception\AppException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Db;
use think\exception\DbException;
use think\Log;

class UserModel extends CommonModel
{

    /**
     * 找回密码
     *
     * @param $param
     * @return mixed
     * @throws AppException
     * @throws Exception
     */
    public function doFindPassword($param)
    {
//        $user_info['user_id'] = 1;
        $this->check_params($param, ['mobile', 'new_password', 're_new_password', 'code']);
        $mobile = $param['mobile'];
        $this->is_mobile($mobile);
        $users = $this->getUserInfoByMobile($mobile);
        if (empty($users)) {
            Log::write("该手机号还未注册,手机号是:[$mobile]", 'error');
            throw new AppException(MOBILE_NOT_REGISTER, lang('MOBILE_NOT_REGISTER'));
        }

        $this->check_password($param['new_password'], 32, 5);// 大于5位小于32位
        // 判断密码是否一样
        if ($param['new_password'] != $param['re_new_password']) {
            Log::write("手机号[$mobile]两次输入密码不一样,1次是[" . $param['new_password'] . "],2次是[" . $param['re_new_password'] . "]", 'error');
            throw new AppException(TWO_PASSWORDS_DIFFERENT, lang('TWO_PASSWORDS_DIFFERENT'));
        }

        $this->login_safety_password($param['new_password'], $users, 'safety');
        // 验证验证码
        $type = 4; // 忘记密码类型
        $code_info = $this->verification_code($mobile, $type, $param['code']);
        // 更改密码
        $this->update_password($users['user_id'], $param['new_password']);

        // 更改验证码状态
        $this->verification_code_update(msectime(), $code_info, 1);

        $result['mobile'] = $mobile;
        return $result;
    }

    /**
     * 修改安全码/登录密码
     *
     * @param $param
     * @param $user_info
     * @return mixed
     * @throws AppException
     * @throws Exception
     */
    public function doModifyPassword($param, $user_info)
    {
//        $user_info['user_id'] = 1;

        $this->check_params($param, ['password', 're_password', 'code']);
        $type = isset($param['type']) ? $param['type'] : 2; // 默认修改密码
        $users = $this->getUserInfoByUserId($user_info['user_id']);
        $mobile = $users['mobile'];

        $this->check_password($param['password'], 32, 5);// 大于5位小于32位
        // 判断密码是否一样
        if ($param['password'] != $param['re_password']) {
            Log::write("手机号[$mobile]两次输入密码不一样,1次是[" . $param['password'] . "],2次是[" . $param['re_password'] . "]", 'error');
            throw new AppException(TWO_PASSWORDS_DIFFERENT, lang('TWO_PASSWORDS_DIFFERENT'));
        }

        $modify_type = 'login';
        $password_type = 'safety';
        if ($type == 3) {
            $modify_type = 'safety';
            $password_type = 'login';
        }
        $this->login_safety_password($param['password'], $users, $password_type);
        $code_info = $this->verification_code($mobile, $type, $param['code']);

        // 更改密码
        $this->update_password($user_info['user_id'], $param['password'], $modify_type);
        // 更改验证码状态
        $this->verification_code_update(msectime(), $code_info, 1);
        $result['mobile'] = $mobile;
        return $result;
    }

    /**
     * 获取用户信息
     *
     * @param $user_info
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function doMyIndex($user_info)
    {
        $users = $this->getUserInfoByUserId($user_info['user_id']);
        $result = $this->doUserInfo($users);
        return $result;
    }

    /**
     * 更新用户信息
     *
     * @param $param
     * @param $user_info
     * @return mixed
     * @throws AppException
     * @throws Exception
     */
    public function doUpdateUser($param, $user_info)
    {
//        $user_info['user_id'] = 1;
        $avatar_url = $this->upload_image('avatar');
        if ($avatar_url != '') {
            $data['avatar_url'] = $avatar_url;
        }
        $users = $this->getUserInfoByUserId($user_info['user_id']);
        $data['nickname'] = isset($param['nickname']) ? base64_decode($param['nickname']) : $users['nickname'];
        $data['sex'] = isset($param['sex']) ? $param['sex'] : $users['sex'];
        $data['update_time'] = msectime();
        Db::name('user')->where('user_id', $user_info['user_id'])->update($data);
        $result['nickname'] = $data['nickname'];
        return $result;
    }

    /**
     * 处理邀请码图片
     * @param $user_info
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doInviteImage($user_info)
    {
//        $user_info['user_id'] = 1;
        $users = $this->getUserInfoByUserId($user_info['user_id']);
        $invite_code = $users['user_code_invite'];
        $port = $_SERVER["SERVER_PORT"];
        $path = explode('?', $_SERVER["REQUEST_URI"]);
        if ($port == NULL) {
            $url = HTTP . $_SERVER["SERVER_NAME"] . $path[0];
        } else {
            $url = HTTP . $_SERVER["SERVER_NAME"] . ':' . $port . $path[0];
        }
        $url = str_replace('invite_image', 'get_image', $url);
        $result['invite_url'] = $url . "?invite_code=" . $invite_code;
        return $result;
    }

    /**
     * 获取邀请码图片
     *
     * @param $param
     * @throws AppException
     */
    public function doGetImage($param)
    {
        $this->check_params($param, ['invite_code']);
        $invite_code = $param['invite_code'];
        $img = imagecreatefrompng("./static/share.png");
        $col = imagecolorallocate($img, 255, 96, 0);
        $font = ROOT_PATH . "public/static/PingFang-SC-Semibold.ttf"; //字体所放目录
        $come = iconv("utf-8", "utf-8", $invite_code);
        $w = 190;
        if (strpos($invite_code, 'I') !== false) {
            $w = 220;
        }
        $h = 900;
        imagettftext($img, 60, 0, $w, $h, $col, $font, $come);
        header("content-type:image/png");
        imagepng($img);
        imagedestroy($img);
        exit();
    }

}