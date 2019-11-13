<?php

namespace app\fund\controller;


use app\common\exception\AppException;
use app\fund\model\IndexModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\response\Json;

class Index extends Common
{
    protected $model;

    public function __construct()
    {
        $this->model = new IndexModel();
        parent::__construct();
    }


    /**
     * 开启团队奖励
     *
     * @return Json
     * @throws AppException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    public function open_group()
    {
        $result = $this->model->doOpenGroup($this->user_info);
        return json_data($result);
    }

    /**
     * dgv兑换dg币
     *
     * @return Json
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function dgv_to_dg()
    {
        $result = $this->model->doDgvToDg($this->param, $this->user_info);
        return json_data($result);
    }

    /**
     * 使用DG币勾兑成DGV
     *
     * @return Json
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function dg_to_dgv()
    {
        $result = $this->model->doDgToDgv($this->param, $this->user_info);
        return json_data($result);
    }

    /**
     * 获取汇率
     *
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function get_rate()
    {
        $result = $this->model->doGetRate();
        return json_data($result);
    }
}
