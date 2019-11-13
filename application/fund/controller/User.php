<?php

namespace app\fund\controller;

use app\common\exception\AppException;
use app\fund\model\UserModel;
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
     * 获取用户资产账户详情
     *
     * @return Json
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function accounts()
    {
        $result = $this->model->doAccounts($this->param, $this->user_info);
        return json_data($result, LISTS);
    }

    /**
     *  用户与用户之间转账
     *
     * @return Json
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function transfer()
    {
        $result = $this->model->doTransfer($this->param, $this->user_info);
        return json_data($result);
    }

    /**
     * 提现请求
     *
     * @return Json
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function withdraw()
    {
        $result = $this->model->doWithdraw($this->param, $this->user_info);
        return json_data($result);
    }

    /**
     * 获取某个币种转账/充值提现的详情
     *
     * @return Json
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function assets_details()
    {
        $result = $this->model->doAssetDetail($this->param, $this->user_info);
        return json_data($result, LISTS);
    }

    /**
     * ios,获取充值信息
     *
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deposit_info()
    {
        $result = $this->model->doDepositInfo($this->user_info);
        return json_data($result);
    }

    /**
     * 冻结DGV
     *
     * @return Json
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public function frozen_dgv()
    {
        $result = $this->model->doFrozenDgv($this->param, $this->user_info);
        return json_data($result);
    }

}