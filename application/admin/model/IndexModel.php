<?php

namespace app\admin\model;

use app\common\model\BaseCommonModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Db;
use app\common\exception\AppException;
use think\exception\DbException;

class IndexModel extends BaseCommonModel
{

    /**
     * 用户登录
     *
     * @param $param
     * @return int
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function doLogin($param)
    {
        // 查询该用户是否存在
        $user_info = Db::connect('admin')->name('users')->where('username', $param['username'])->find();
        if (empty($user_info) || $user_info['status'] == 0) {
            throw new AppException(USER_NOT_EXITS, lang('USER_NOT_EXITS'));
        }
        $password = $param['newpassword'];
        // 验证密码
        $password_salt = $user_info['password_salt'];
        $user_password = $user_info['password'];

        $input_md5 = md5($password . $password_salt);
        if ($user_password != $input_md5) {
            throw new AppException(ADMIN_USER_PASSWORD_WRONG, lang('ADMIN_USER_PASSWORD_WRONG'));
        }
        session('admin_id', $user_info['admin_id']);
        return 1;
    }

    /**
     * 返回用户信息
     * @param $admin_id
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function doIndex($admin_id)
    {
        $user_info = Db::connect('admin')->name('users')->where('admin_id', $admin_id)->find();
        $result['username'] = $user_info['username'];
        return $result;
    }
}