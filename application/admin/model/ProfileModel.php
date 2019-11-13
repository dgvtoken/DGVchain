<?php

namespace app\admin\model;

use app\common\model\BaseCommonModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Db;
use app\common\exception\AppException;
use think\exception\DbException;
use think\exception\PDOException;

class ProfileModel extends BaseCommonModel
{

    /**
     * 配置信息
     *
     * @param $param
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function doIndex($param)
    {
        $profile = Db::name('profile')->order('create_time desc')->select();
        $result['list'] = $profile;
        return $result;
    }

    /**
     * 更新配置
     *
     * @param $param
     * @return int
     * @throws Exception
     * @throws PDOException
     */
    public function doUpdateProfile($param)
    {
        $update_data['profile_value'] = $param['profile_value'];
        $update_data['update_time'] = msectime();
        Db::name('profile')->where('id', $param["id"])->update($update_data);
        return 1;
    }
}