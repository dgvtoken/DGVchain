<?php

namespace app\admin\controller;

use app\admin\model\AppUserModel;
use app\common\exception\AppException;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException as DbExceptionAlias;
use think\response\Json;

class AppUser extends Common
{
    protected $model;

    public function __construct()
    {
        $this->model = new AppUserModel();
        parent::__construct();
    }

    /**
     * app用户列表
     *
     * @return mixed
     * @throws DbExceptionAlias
     * @throws Exception
     */
    public function index()
    {
        $result = $this->model->doIndex($this->param);
        $this->assign('result', $result);
        $this->assign('num', $result['num']);
        $this->assign('pages', $result['pages']);
        $this->assign('keyword', $result['keyword']);
        return $this->fetch();
    }

    /**
     * app下某个用户下的邀请列表
     *
     * @return mixed
     * @throws DbExceptionAlias
     * @throws Exception
     */
    public function up_user_index()
    {
        $result = $this->model->doUpUserIndex($this->param);
        $this->assign('result', $result);
        $this->assign('user_code_invite', $this->param['user_code_invite']);
        $this->assign('num', $result['num']);
        $this->assign('pages', $result['pages']);
        $this->assign('keyword', $result['keyword']);
        return $this->fetch();
    }

    /**
     * 新增用户数
     *
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbExceptionAlias
     * @throws ModelNotFoundException
     */
    public function new_user()
    {
        $result = $this->model->doNewUser($this->param);

        $this->assign('renshu', $result['renshu_str']);
        $this->assign('date', $result['date']);
        $this->assign('today', $result['today']);
        return $this->fetch();
    }

    /**
     * 获取用户账户信息
     *
     * @return mixed
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbExceptionAlias
     * @throws ModelNotFoundException
     */
    public function user_account()
    {
        $result = $this->model->doUserAccount($this->param);
        $this->assign('result', $result);
        $this->assign('admin_id', session("admin_id"));
        return $this->fetch();
    }

    /**
     * app用户DG锁仓排名
     *
     * @return mixed
     * @throws DbExceptionAlias
     * @throws Exception
     */
    public function user_dg_index()
    {
        $result = $this->model->doUserDgIndex($this->param);
        $this->assign('result', $result);
        $this->assign('num', $result['num']);
        $this->assign('pages', $result['pages']);
        $this->assign('keyword', $result['keyword']);
        return $this->fetch();
    }


    /**
     * app用户DGV总和排名
     *
     * @return mixed
     * @throws DbExceptionAlias
     * @throws Exception
     */
    public function user_dgv_index()
    {
        $result = $this->model->doUserDgvIndex($this->param);
        $this->assign('result', $result);
        $this->assign('num', $result['num']);
        $this->assign('pages', $result['pages']);
        $this->assign('keyword', $result['keyword']);
        return $this->fetch();
    }
}