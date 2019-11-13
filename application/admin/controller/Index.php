<?php
namespace app\admin\controller;

use app\admin\model\IndexModel;
use app\common\exception\AppException;
use think\Controller;
use think\Db;
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
     * 登录页面
     *
     * @return mixed
     */
    public function login()
    {
        return $this->fetch();
    }

    /**
     * 欢迎页面
     *
     * @return mixed
     * @throws Exception
     */
    public function welcome()
    {
        $data['all_user'] = Db::name('user')->count('user_id');
        $lingchen = strtotime(date('Y-m-d')) * 1000; // 获取当天凌晨的时间戳
        $lingchen_jia_1 = strtotime(date('Y-m-d', strtotime('+1 day')))  * 1000;
        $condition['create_time'] = array('between', array($lingchen, $lingchen_jia_1));
        $data['today_user'] = Db::name('user')->where($condition)->count('user_id');
        // 新增dg币
        $where['asset_code'] = DG;
        $whereIn = [5, 6, 7, 8, 15, 16, 17, 18, 19]; //推荐奖励+注册奖励
        $recommend_dg = Db::name('account_record')->where($where)->where($condition)->whereIn('operation_type', $whereIn)->sum('amount');
        // dgv兑换dg
        $where['operation_type'] = 10;
        $dg_to = Db::name('account_record')->where($where)->where($condition)->sum('amount');
        $data['dg_amount'] = $recommend_dg + $dg_to; // 今日新增 dg

        // 今日释放dg
        $where['operation_type'] = 11;
        $data['release_amount'] = Db::name('account_record')->where($where)->where($condition)->sum('amount');

        // 今日新增dgv
        $where1['asset_code'] = DGV;
        $where1['operation_type'] = 13;
        $dgv_to = Db::name('account_record')->where($where1)->where($condition)->sum('amount');
        // 充值
        $deposit_dgv = Db::name('deposit_record')->where($condition)->sum('amount');
        $data['dgv_amount'] = $dgv_to + $deposit_dgv; // 今日新增 dgv

        // 今天提出dgv
        $data['withdraw_dgv_amount'] = Db::name('withdraw_record')->where($condition)->sum('amount');
        // 当前释放DG总量
        $data['release_all_amount'] = Db::name('account_record')->where($where)->sum('amount');
        // 当前锁仓dg总量
        $data['frozen_dg_amount'] = Db::name('user_account')->where('asset_code', DG)->sum('amount_frozen');

        // 当前dgv总量
        $dgv_amount_frozen= Db::name('user_account')->where('asset_code', DGV)->sum('amount_frozen');
        $dgv_amount_available = Db::name('user_account')->where('asset_code', DGV)->sum('amount_available');
        $data['dgv_all_amount'] = $dgv_amount_frozen + $dgv_amount_available;
        $data['dgv_amount_frozen'] = $dgv_amount_frozen;
        $data['dgv_amount_available'] = $dgv_amount_available;

        $this->assign('data', $data);

        return $this->fetch();
    }


    /**
     * 登录操作
     *
     * @return Json
     * @throws AppException
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function do_login()
    {
        $param = $this->param;
        $result = $this->model->doLogin($param);
        return json_data($result);
    }

    /**
     * 首页
     *
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function index()
    {
        if (session('admin_id') == null) {
            $this->redirect('admin/index/login');
        }
        $result = $this->model->doIndex(session('admin_id'));
        $this->assign('result', $result);
        return $this->fetch();
    }

    /**
     * 登录操作
     *
     * @return void
     */
    public function login_out()
    {
        session('admin_id', null); // 清空当前的session
        $this->redirect('admin/index/login');
    }


}