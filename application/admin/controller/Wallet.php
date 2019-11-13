<?php

namespace app\admin\controller;

use app\admin\model\WalletModel;
use app\common\exception\AppException;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException as DbExceptionAlias;
use think\exception\PDOException as PDOExceptionAlias;
use think\response\Json;

class Wallet extends Common
{
    protected $model;

    public function __construct()
    {
        $this->model = new WalletModel();
        parent::__construct();
    }

    /**
     * 充值列表
     *
     * @return mixed
     * @throws DbExceptionAlias
     * @throws Exception
     */
    public function deposit()
    {
        $result = $this->model->doDeposit($this->param);
        $this->assign('result', $result);
        $this->assign('num', $result['num']);
        $this->assign('pages', $result['pages']);
        $this->assign('keyword', $result['keyword']);
        return $this->fetch();
    }

    /**
     * 提现请求
     *
     * @return mixed
     * @throws DbExceptionAlias
     * @throws Exception
     */
    public function withdraw()
    {
        $result = $this->model->doWithdraw($this->param);
        $this->assign('result', $result);
        $this->assign('num', $result['num']);
        $this->assign('pages', $result['pages']);
        $this->assign('keyword', $result['keyword']);
        return $this->fetch();
    }

    /**
     * 提现记录
     *
     * @return mixed
     * @throws DbExceptionAlias
     * @throws Exception
     */
    public function withdraw_record()
    {
        $result = $this->model->doWithdrawRecord($this->param);
        $this->assign('result', $result);
        $this->assign('num', $result['num']);
        $this->assign('pages', $result['pages']);
        $this->assign('keyword', $result['keyword']);
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
    public function update_dg()
    {
        $user_account = Db::name('user_account')->where('id', $this->param['id'])->find();
        $this->assign('user_account', $user_account);
        $this->assign('asset_code', $this->param['asset_code']);
        return $this->fetch();
    }

    /**
     * 更新dg冻结额度
     *
     * @return Json
     * @throws Exception
     */
    public function do_update_dg()
    {
        $param = $this->param;
        $result = $this->model->doUpdateDg($param);
        return json_data($result);
    }
}