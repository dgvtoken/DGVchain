<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
/**
 * 公共常量
 */

use think\response\Json;

define("LISTS", "list"); // 返回list
define('SIGN_KEY', 'sdfs<,.(8&5$@@1(P}{dgv');
define('SIGN_TIME_STAMP_EXPIRE', 60 * 1000); // 60s
define("JWT_KEY", "HPfkfEK8a88*d0(o,sYfdgv");
define("JWT_EXPIRE_TIME", 10 * 24 * 60 * 60); // 10day
define("LIMIT_EXPIRE_TIME", 24 * 60 * 60); // 1day
define('DOMAIN', 'http://www.dgvtoken.com');
define('EOS', 'EOS');
define('KOK', 'KOK');
define('DG', 'DG');
define('DGV', 'DGV');
define("HTTP", 'http://');
define("RELEASE_RATE", 'release_rate');
define("DGV_TO_DG_RATE", 'dgv_to_dg_rate');
define("DG_TO_DGV_RATE", 'dg_to_dgv_rate');
define("FRIENDS_DG_AMOUNT", 150000); // 余额超过15w

define('MIN_AMOUNT_CHANGE', 0.0001);
define("NOT_GROUP_REWARD_RATE", 0.1); // 不是群主直推奖励

define("GROUP_REWARD_RATE", 0.3); // 群主奖励
define("TWO_FRIEND", 0.15); // 二级好友奖励比例
define("THREE_FRIEND", 0.075); // 三级级好友奖励比例
define("FOUR_FRIEND", 0.00375); // 四级级好友奖励比例
define("FIVE_FRIEND", 0.001875); // 五级级好友奖励比例
define("SIX_FRIEND", 0.0009375); // 六级级好友奖励比例


define("SUCCESS", 100200);
define("PARAM_ERROR", 100300);
define("REFRESH_TOKEN", 100201);

define("PLEASE_LOGIN", 100109);
define("API_SIGN_ERROR", 100301);
define("API_SIGN_EXPIRE", 100302);


define("MOBILE_NOT_LEGAL", 100401);
define("MOBILE_NOT_REGISTER", 100412);
define("UPLOAD_AVATAR_ERROR", 100416);
define("ASSET_CODE_NOT_SUPPORT_TRANSFER", 100417);
define("USER_NOT_EXITS", 100418);
/**
 * json data
 *
 * @param $data
 * @param string $type 空data map,其他=list
 * @return Json
 */
function json_data($data, $type = 'map')
{
    $result['code'] = SUCCESS;
    $tmp_data[$type] = $data;

    $result['data'] = $tmp_data;
    $result['msg'] = lang('Success');
    $result['time'] = msectime();

    return json($result);
}

//返回当前的毫秒时间戳
function msectime()
{
    list($msec, $sec) = explode(' ', microtime());
    $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectime;
}

// 应用公共文件
function parse_name($name, $type = 0, $ucfirst = true)
{
    if ($type) {
        $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name);
        return $ucfirst ? ucfirst($name) : lcfirst($name);
    } else {
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

/**
 * curl get
 * @param $url
 * @return mixed
 */
function get_curl($url)
{
    $curl = curl_init();
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);

    return $data;
}

/**
 * curl post
 * @param string $url
 * @param string $param
 * @return mixed
 */
function post_curl($url = '', $param = '')
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $info = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Errno' . curl_error($ch);
    }

    curl_close($ch);

    return $info;
}

// 二维数组根据某一个字段 排序
function sortArrByField(&$array, $field, $desc = false)
{
    $fieldArr = array();
    foreach ($array as $k => $v) {
        $fieldArr[$k] = $v[$field];
    }
    $sort = $desc == false ? SORT_DESC : SORT_ASC;
    array_multisort($fieldArr, $sort, $array);
    return $array;
}

//uuid生成方法（可以指定前缀）
function create_uuid($prefix = "")
{
    $str = md5(uniqid(mt_rand(), true));
    $uuid = substr($str, 0, 8);
    $uuid .= substr($str, 8, 4);
    $uuid .= substr($str, 12, 4);
    $uuid .= substr($str, 16, 4);
    $uuid .= substr($str, 20, 12);
    return $prefix . $uuid;
}