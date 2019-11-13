<?php
namespace app\admin\controller;

use app\admin\model\UserModel;
use app\common\exception\AppException;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException as DbExceptionAlias;
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
     * 管理员列表
     *
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbExceptionAlias
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function admin_index()
    {
        $result = $this->model->doAdminIndex($this->param);
        $this->assign('result', $result);
        return $this->fetch();
    }

    /**
     * 更新管理员个人信息
     *
     * @return Json
     * @throws AppException
     * @throws Exception
     */
    public function update_admin()
    {
        $param = $this->param;
        $result = $this->model->doUpdateAdmin($param);
        return json_data($result);
    }

    /**
     * 管理员
     *
     * @return mixed
     */
    public function admin_add()
    {
        return $this->fetch();
    }

    /**
     * 添加管理员
     *
     * @return mixed
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbExceptionAlias
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function add_admin()
    {
        $result = $this->model->doAddAdmin($this->param);
        return json_data($result);
    }

    /**
     * 编辑页面
     *
     * @return mixed
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbExceptionAlias
     */
    public function admin_edit()
    {
        $user_info = Db::connect('admin')->name('users')->field('username, admin_id')->where('admin_id', $this->param['admin_id'])->find();
        $this->assign('username', $user_info['username']);
        $this->assign('admin_id', $user_info['admin_id']);
        return $this->fetch();
    }

    /**
     * 更新管理员个人信息
     *
     * @return Json
     * @throws AppException
     * @throws Exception
     */
    public function update_admin_info()
    {
        $param = $this->param;
        $result = $this->model->doUpdateAdminInfo($param);

        return json_data($result);
    }
}