<?php

namespace app\index\controller;

use app\common\exception\AppException;
use app\index\model\UserModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\response\Json;

class User extends Common
{
    protected $model;

    public function __construct()
    {
        $this->model = new UserModel();
        parent::__construct();
    }

    /**
     * 找回密码
     *
     * @return Json
     * @throws AppException
     * @throws Exception
     */
    public function find_password()
    {
        $param = $this->param;
        $result = $this->model->doFindPassword($param);
        return json_data($result);
    }

    /**
     * 修改密码/或者安全码
     *
     * @return Json
     * @throws AppException
     * @throws Exception
     */
    public function modify_password()
    {
        $param = $this->param;
        $result = $this->model->doModifyPassword($param, $this->user_info);
        return json_data($result);
    }

    /**
     * 获取用户信息
     *
     * @return Json
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function my_index()
    {
        $result = $this->model->doMyIndex($this->user_info);
        return json_data($result);
    }

    /**
     * 获取用户信息
     *
     * @return Json
     * @throws AppException
     * @throws Exception
     */
    public function update_user()
    {
        $param = $this->param;
        $result = $this->model->doUpdateUser($param, $this->user_info);
        return json_data($result);
    }

    /**
     * 获取邀请图片
     *
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function invite_image()
    {
        $result = $this->model->doInviteImage($this->user_info);
        return json_data($result);
    }

    /**
     * 邀请码图片
     *
     * @throws AppException
     */
    public function get_image()
    {
        $param = $this->param;
        $this->model->doGetImage($param);
    }
}