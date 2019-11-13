<?php

namespace app\admin\model;

use app\common\model\BaseCommonModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Db;
use app\common\exception\AppException;
use think\exception\DbException;

class UserModel extends BaseCommonModel
{

    /**
     * 管理员列表
     *
     * @param $param
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function doAdminIndex($param)
    {
        // 所有管理员
        $admin_user = Db::connect('admin')->name('users')->where('status <> 2')->select();
        $result['total'] = count($admin_user);
        if (count($admin_user) == 0) {
            $result['admin_list'] = [];
            return $result;
        }

        $admin_list = [];
        foreach ($admin_user as $key => $item) {
            $admin_list[$key]['username'] = $item['username'];
            $admin_list[$key]['admin_id'] = $item['admin_id'];
            $admin_list[$key]['avatar'] = $item['avatar'];
            $admin_list[$key]['create_time'] = $item['create_time'];
            $admin_list[$key]['status'] = $item['status'];
        }
        $result['admin_list'] = $admin_list;
        return $result;
    }

    /**
     * 更新个人信息
     *
     * @param $param
     * @return int
     * @throws AppException
     * @throws Exception
     */
    public function doUpdateAdmin($param)
    {
        $admin_id = $param['admin_id'];
        $admin_user = Db::connect('admin')->name('users')->where('admin_id', $admin_id)->find();
        if (empty($admin_user)) {
            throw new AppException(USER_NOT_EXITS, lang('USER_NOT_EXITS'));
        }

        $update_data['status'] = isset($param['status']) ? $param['status'] : $admin_user['status'];
//        if (isset($param['username'])) {
//            $update_data['username'] = $param['username'];
//            $is_exits = Db::connect('admin')->name('users')->where('username', $param['username'])->find();
//            if (!empty($is_exits)) {
//                throw new AppException(USER_NAME_HAS_EXITS, lang('USER_NAME_HAS_EXITS'));
//            }
//        }

        $update_data['update_time'] = msectime();
        Db::connect('admin')->name('users')->where('admin_id', $admin_id)->update($update_data);
        return 1;
    }

    /**
     * add admin_user
     *
     * @param $param
     * @return int
     * @throws AppException
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function doAddAdmin($param)
    {
        $data['username'] = $param['adminName'];
        $data['password_salt'] = create_uuid();
        $data['create_time'] = time();
        $data['password'] = md5($param['password'] . $data['password_salt']);

        $is_exits = Db::connect('admin')->name('users')->where('username', $param['adminName'])->find();
        if (!empty($is_exits)) {
            throw new AppException(USER_NAME_HAS_EXITS, lang('USER_NAME_HAS_EXITS'));
        }

        Db::connect('admin')->name('users')->insert($data);
        return 1;
    }

    /**
     * 更新管理员信息
     *
     * @param $param
     * @return int
     * @throws AppException
     * @throws Exception
     */
    public function doUpdateAdminInfo($param)
    {
        $admin_id = $param['admin_id'];
        $admin_user = Db::connect('admin')->name('users')->where('admin_id', $admin_id)->find();
        if (empty($admin_user)) {
            throw new AppException(USER_NOT_EXITS, lang('USER_NOT_EXITS'));
        }

        if (isset($param['adminName'])) {
            $update_data['username'] = $param['adminName'];
            $is_exits = Db::connect('admin')->name('users')->where('username', $param['adminName'])->find();
            if (!empty($is_exits)) {
                throw new AppException(USER_NAME_HAS_EXITS, lang('USER_NAME_HAS_EXITS'));
            }
        }

        $update_data['update_time'] = msectime();
        Db::connect('admin')->name('users')->where('admin_id', $admin_id)->update($update_data);
        return 1;
    }
}