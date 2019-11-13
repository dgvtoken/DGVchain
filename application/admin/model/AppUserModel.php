<?php

namespace app\admin\model;

use app\common\model\BaseCommonModel;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Db;
use app\common\exception\AppException;
use think\exception\DbException;

class AppUserModel extends BaseCommonModel
{

    /**
     * app用户列表
     *
     * @param $param
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    public function doIndex($param)
    {
        $num = isset($param['num']) ? $param['num'] : 20;
        $keyword = isset($param['keyword']) ? $param['keyword'] : '';

        // 所有用户
        $app_user_count = Db::name('user')->whereLike('mobile|nickname', "%" . $keyword . "%")->count('user_id');
        $result['user_count'] = $app_user_count;
        $result['keyword'] = $keyword;
        $result['num'] = $num;
        if ($app_user_count == 0) {
            $result['user_list'] = [];
            $result['pages'] = 0;
            return $result;
        }

        $app_user = Db::name('user')->whereLike('mobile|nickname', "%" . $keyword . "%")->order('invite_num desc, create_time desc')->paginate($num);
        $pages = $app_user->render();
        $user_list = [];
        foreach ($app_user as $key => $item) {
            // 获取上级邀请人
            $up_user = Db::name('user')->field('mobile, nickname, user_code_invite')->where('user_code_invite', $item['code_invite'])->find();
            $user_list[$key]['up_user'] = "";
            $user_list[$key]['up_user_code_invite'] = "";
            if (!empty($up_user)) {
                $user_list[$key]['up_user'] = $up_user['nickname'] . '(' . $up_user['mobile'] . ')';
                $user_list[$key]['up_user_code_invite'] = $up_user['user_code_invite'];
            }

            $user_list[$key]['user_id'] = $item['user_id'];
            $user_list[$key]['nickname'] = $item['nickname'] ? $item['nickname'] : "--暂无--";
            $user_list[$key]['mobile'] = $item['mobile'];
            $user_list[$key]['invite_num'] = $item['invite_num'];
            $user_list[$key]['auth_level'] = $item['auth_level'];
            $user_list[$key]['create_time'] = $item['create_time'];
            $user_list[$key]['create_time'] = $item['create_time'];
            $user_list[$key]['user_status'] = $item['user_status'];
            $user_list[$key]['user_code_invite'] = $item['user_code_invite'];
        }
        $result['user_list'] = $user_list;
        $result['pages'] = $pages;
        return $result;
    }

    /**
     * app下某个用户下的邀请列表
     *
     * @param $param
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    public function doUpUserIndex($param)
    {
        $num = isset($param['num']) ? $param['num'] : 20;
        $keyword = isset($param['keyword']) ? $param['keyword'] : '';

        $user_code_invite = $param['user_code_invite'];

        // 所有用户
        $app_user_count = Db::name('user')->where('code_invite', $user_code_invite)->whereLike('mobile|nickname', "%" . $keyword . "%")->count('user_id');
        $result['user_count'] = $app_user_count;
        $result['keyword'] = $keyword;
        $result['num'] = $num;
        if ($app_user_count == 0) {
            $result['user_list'] = [];
            $result['pages'] = 0;
            return $result;
        }

        $app_user = Db::name('user')->where('code_invite', $user_code_invite)->whereLike('mobile|nickname', "%" . $keyword . "%")->order('invite_num desc, create_time desc')->paginate($num);
        $pages = $app_user->render();
        $user_list = [];
        foreach ($app_user as $key => $item) {
            $user_list[$key]['user_status'] = $item['user_status'];
            $user_list[$key]['user_id'] = $item['user_id'];
            $user_list[$key]['nickname'] = $item['nickname'] ? $item['nickname'] : "--暂无--";
            $user_list[$key]['mobile'] = $item['mobile'];
            $user_list[$key]['invite_num'] = $item['invite_num'];
            $user_list[$key]['auth_level'] = $item['auth_level'];
            $user_list[$key]['create_time'] = $item['create_time'];
            $user_list[$key]['user_code_invite'] = $item['user_code_invite'];
        }
        $result['user_list'] = $user_list;
        $result['pages'] = $pages;
        return $result;
    }

    /**
     * 获取新增用户曲线
     *
     * @param $param
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doNewUser($param)
    {
        $date = isset($param['date']) ? $param['date'] : date("Y-m-d");
        $lingchen = strtotime($date); // 获取当天凌晨的时间戳
        $lingchen_1 = $lingchen + 24 * 60 * 60;

        $where_ren['create_time'] = array('between', array($lingchen * 1000, $lingchen_1 * 1000));
        $zongrenshu = Db::name('user')->field('create_time')->where($where_ren)->select();

        $count = $this->getRenshu($zongrenshu, $lingchen * 1000);

        $time_array = [];
        if (!empty($count)) {
            for ($i = 0; $i < 24; $i++) {
                if (!isset($count[$i])) {
                    $time_array[$i] = 0;
                } else {
                    $time_array[$i] = $count[$i];
                }
            }
        }

        $today = array_sum($time_array);
        $renshu = implode(",", $time_array);
        $str = str_replace(",", ", ", $renshu);

        $result['date'] = $date;
        $result['today'] = $today;
        $result['renshu_str'] = $str;
        return $result;
    }

    // 获取人数
    public function getRenshu($result, $time)
    {
        if (empty($result))
            return [];

        $key_tmp = [];
        foreach ($result as $key => $value) {
            $time_tmp = $value['create_time'] / 1000;
            $h = date("H", $time_tmp);
            if ($h != 0) {
                $h = ltrim($h, 0);
            } else {
                $h = 0;
            }
            $key_tmp[$h][] = $value;
        }

        $tmp = [];
        foreach ($key_tmp as $key => $value) {
            $tmp[$key] = count($value);
        }
        return $tmp;
    }

    /**
     * 获取用户账户信息
     *
     * @param $param
     * @return false|\PDOStatement|string|Collection
     * @throws AppException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doUserAccount($param)
    {
        $this->check_params($param, ['user_id']);
        $user_id = $param['user_id'];
        // 获取用户账户余额信息
        $result = Db::name('user_account')->where('user_id', $user_id)->select();
        return $result;
    }

    /**
     * app用户DG锁仓排名
     *
     * @param $param
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    public function doUserDgIndex($param)
    {
        $num = isset($param['num']) ? $param['num'] : 20;
        $keyword = isset($param['keyword']) ? $param['keyword'] : '';

        // 查询DG锁仓
        $dg_account_count = Db::name('user_account')->alias('a')->join('user u', 'a.user_id = u.user_id', 'left')->where('asset_code', DG)->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->count('a.user_id');
        $result['user_count'] = $dg_account_count;
        $result['keyword'] = $keyword;
        $result['num'] = $num;
        if ($dg_account_count == 0) {
            $result['user_list'] = [];
            $result['pages'] = 0;
            return $result;
        }

        $dg_account = Db::name('user_account')->alias('a')->join('user u', 'a.user_id = u.user_id', 'left')->field(['a.*', 'u.mobile, u.nickname'])->where('asset_code', DG)->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->order('a.amount_frozen desc')->paginate($num);
        $pages = $dg_account->render();
        $user_list = [];
        foreach ($dg_account as $key => $item) {
            // 获取上级邀请
            $user_list[$key]['id'] = $item['id'];
            $user_list[$key]['nickname'] = $item['nickname'] ? $item['nickname'] : "--暂无--";
            $user_list[$key]['mobile'] = $item['mobile'];
            $user_list[$key]['amount_available'] = $item['amount_available'];
            $user_list[$key]['amount_frozen'] = $item['amount_frozen'];
            $user_list[$key]['account_status'] = $item['account_status'];
            $user_list[$key]['enough_money'] = $item['enough_money'];
            $user_list[$key]['update_time'] = $item['update_time'];
            $user_list[$key]['asset_code'] = $item['asset_code'];
        }
        $result['user_list'] = $user_list;
        $result['pages'] = $pages;
        return $result;
    }

    /**
     * app用户DGV总和排名
     *
     * @param $param
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    public function doUserDgvIndex($param)
    {
        $num = isset($param['num']) ? $param['num'] : 20;
        $keyword = isset($param['keyword']) ? $param['keyword'] : '';

        // 查询DG锁仓
        $dg_account_count = Db::name('user_account')->alias('a')->join('user u', 'a.user_id = u.user_id', 'left')->where('asset_code', DGV)->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->count('a.user_id');
        $result['user_count'] = $dg_account_count;
        $result['keyword'] = $keyword;
        $result['num'] = $num;
        if ($dg_account_count == 0) {
            $result['user_list'] = [];
            $result['pages'] = 0;
            return $result;
        }

        $dg_account = Db::name('user_account')->alias('a')->join('user u', 'a.user_id = u.user_id', 'left')->field(['a.*, a.amount_available + a.amount_frozen AS all_amount', 'u.mobile, u.nickname'])->where('asset_code', DGV)->whereLike('u.mobile|u.nickname', "%" . $keyword . "%")->order('all_amount desc')->paginate($num);
        $pages = $dg_account->render();
        $user_list = [];
        foreach ($dg_account as $key => $item) {
            // 获取上级邀请
            $user_list[$key]['id'] = $item['id'];
            $user_list[$key]['nickname'] = $item['nickname'] ? $item['nickname'] : "--暂无--";
            $user_list[$key]['mobile'] = $item['mobile'];
            $user_list[$key]['amount_available'] = $item['amount_available'];
            $user_list[$key]['amount_frozen'] = $item['amount_frozen'];
            $user_list[$key]['all_amount'] = $item['all_amount'];
            $user_list[$key]['account_status'] = $item['account_status'];
            $user_list[$key]['update_time'] = $item['update_time'];
            $user_list[$key]['asset_code'] = $item['asset_code'];
        }
        $result['user_list'] = $user_list;
        $result['pages'] = $pages;
        return $result;
    }
}