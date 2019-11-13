<?php
namespace app\common\controller;

use app\common\exception\AppException;
use app\common\model\BaseCommonModel;
use think\Controller;
use think\Db;
use think\Log;
use think\Request;

class BaseCommon extends Controller
{
    protected $request;
    protected $param;
    protected $header;

    protected $module;
    protected $controller;
    protected $action;
    protected $url;

    protected $models;

    protected $user_info;


    public function __construct()
    {
        $this->request = Request::instance();
        $this->param = $this->request->param();
        $this->header = $this->request->header();
        $this->module = $this->request->module();
        $this->controller = $this->request->controller();
        $this->action = $this->request->action();

        $this->url = parse_name($this->module) .
            '/' . parse_name($this->controller) .
            "/" . parse_name($this->action);


        $this->models = new BaseCommonModel();
        parent::__construct();
    }

    /**
     * 判断是否为合法请求
     */
    public function _initialize()
    {
        // 不需要token和不要验证加密的需要跳过
        if (is_array(config('no_sign')) && in_array($this->url, config('no_sign'))) {
            return true;
        }

        // 验证加密字符
        $this->checkSign();

        // 不需要token的需要跳过
        if (is_array(config('no_token')) && in_array($this->url, config('no_token'))) {
            return true;
        }

        // 验证用户token
        $this->models->check_params($this->header, ['token']);

        $user_info = $this->models->parseJwt($this->header['token']);
        $user_info = json_decode(json_encode($user_info), true);
        $user_info = $user_info['user_info'];

        $this->user_info = $user_info;

        return true;
    }


    /**
     * 判断加密参数是否正确
     * @throws AppException
     */
    private function checkSign()
    {
        $params = $this->param;
        $header = $this->header;
        if (!isset($header['sign']) || !isset($header['timestamp']) || !isset($header['version'])) {
            Log::write("sign没有传过来,请稍后重试", 'error');
            throw new AppException(API_SIGN_ERROR, lang('Sign error'));
        }

        $params['version'] = $header['version'];
        if (isset($header['token']) && $header['token'] != '') {
            $params['token'] = $header['token'];
        }

        $sign = $header['sign'];
        $time_stamp = $header['timestamp'];
        $params['timestamp'] = $header['timestamp'];
        sort($params, SORT_STRING);
        $paramString = implode('', $params);
        $signature = md5($paramString . SIGN_KEY);

        if ($sign != $signature) {
            Log::write("sign不正确,请稍后重试", 'error');
            throw new AppException(API_SIGN_ERROR, lang('Sign error'));
        }

        if (msectime() - $time_stamp > SIGN_TIME_STAMP_EXPIRE) {
            Log::write("sign过期,请稍后重试", 'error');
            throw new AppException(API_SIGN_EXPIRE, lang('Sign expire'));
        }
    }
}