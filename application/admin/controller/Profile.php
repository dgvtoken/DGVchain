<?php

namespace app\admin\controller;

use app\admin\model\ProfileModel;
use app\common\exception\AppException;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException as DbExceptionAlias;
use think\response\Json;

class Profile extends Common
{
    protected $model;

    public function __construct()
    {
        $this->model = new ProfileModel();
        parent::__construct();
    }

    /**
     * 配置列表
     *
     * @return mixed
     * @throws DbExceptionAlias
     * @throws Exception
     */
    public function index()
    {
        $result = $this->model->doIndex($this->param);
        $this->assign('result', $result);
        return $this->fetch();
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
    public function edit()
    {
        $profile = Db::name('profile')->where('id', $this->param['id'])->find();
        $this->assign('profile', $profile);
        return $this->fetch();
    }

    /**
     * 更新配置
     *
     * @return Json
     * @throws Exception
     */
    public function update_profile()
    {
        $param = $this->param;
        $result = $this->model->doUpdateProfile($param);
        return json_data($result);
    }
}