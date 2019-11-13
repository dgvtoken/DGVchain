<?php

namespace app\index\controller;

use app\common\exception\AppException;
use app\index\model\IndexModel;
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
     * 发送验证码
     *
     * @return Json
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function send_sms()
    {
        $param = $this->param;
        $header = $this->header;
        $result = $this->model->doSendSms($param, $header);
        return json_data($result);
    }

    /**
     * 用户手机号注册
     *
     * @return Json
     * @throws AppException
     * @throws Exception
     */
    public function sign_up()
    {
        $param = $this->param;
        $result = $this->model->doRegister($param);
        return json_data($result);
    }

    /**
     * 用户手机号注册
     *
     * @return Json
     * @throws Exception
     */
    public function test()
    {
//        $invite_info['user_code_invite'] = 'E0HUNNs';
        $invite_info['code_invite'] = 'I0TZD3';
        $invite_info['user_id'] = 9;
        $result = $this->model->get_invite_users($invite_info);
        array_unshift($result, $invite_info);
        return json_data($result);
    }

    /**
     * 用户手机号登录
     *
     * @return Json
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function sign_in()
    {
        $param = $this->param;
        $result = $this->model->doSignIn($param);
        return json_data($result);
    }

    /**
     * 邀请好友(我的邀请)
     * @return Json
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    public function invite_user()
    {
        $param = $this->param;
        $result = $this->model->doInviteUser($param, $this->user_info);
        return json_data($result);
    }

    /**
     * 联系我们
     * @return Json
     * @throws AppException
     */
    public function contact_us()
    {
        $param = $this->param;
        $result = $this->model->doContactUs($param, $this->user_info);
        return json_data($result);
    }

    /**
     * 常见问题/消息推送
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function problem_message()
    {
        $param = $this->param;
        $result = $this->model->doProblemMessage($param);
        return json_data($result, LISTS);
    }

    /**
     * 检测app升级接口
     *
     * @return Json
     * @throws AppException
     */
    public function upgrade_check()
    {
        $param = $this->param;
        $header = $this->header;
        $result = $this->model->doUpgradeCheck($param, $header['version']);
        return json_data($result);
    }
}
