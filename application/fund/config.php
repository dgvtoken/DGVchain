<?php
define('DEPOSIT_ADDRESS', 'dgvgoinggo11');
define('WITHDRAW_AMOUNT_LIMIT', 1000);
define('OPEN_GOLD_VIP_EOS_AMOUNT', 80); // 开通黄金vip消耗eos金额
define('OPEN_PLATINUM_VIP_EOS_AMOUNT', 120); // 开通白金vip消耗eos金额
define('OPEN_GOLD_VIP_KOK_AMOUNT', 30000); // 开通黄金vip,奖励kok
define('OPEN_PLATINUM_VIP_KOK_AMOUNT', 54000); // 开通白金vip,奖励kok
define('OPEN_GOLD_VIP_KOK_AMOUNT_RATE', 0); // 开通黄金vip,奖励kok的比例
define('OPEN_PLATINUM_VIP_KOK_AMOUNT_RATE', 0.2); // 开通白金vip,奖励kok的比例
define('USER_TASK_COUNT_LIMIT', 8); // 每天做任务的个数

define('OPEN_VIP_NO_SUBSCRIBE_EOS_AMOUNT', 68); // 没有认购的用户开通会员需要68个eos
define('OPEN_VIP_YES_SUBSCRIBE_EOS_AMOUNT', 38); // 认购的用户开通会员需要38个eos
define('OPEN_VIP_SEND_KOK_AMOUNT', 28880); // 开通vip,送的KOK
define('OPEN_VIP_SEND_ONE_FRIENDS_KOK_AMOUNT', 1000); // 开通vip,送一级好友的KOK
define('OPEN_VIP_SEND_TWO_FRIENDS_KOK_AMOUNT', 500); // 开通vip,送二级好友的KOK
define('PUBLISH_AD_KOK_AMOUNT', 188); // 发布广告需要的KOK
define('PUBLISH_AD_EXPIRE_TIME', 129600 * 1000); // 发布广告过期时间36h
define('PUBLISH_AD_CONTENT_LENGTH', 50); // 发布广告最长字符串
define('USER_TASK_REWARD_KOK_AMOUNT', 30); // 做任务奖励kok
define('OPEN_VIP_NO_SUBSCRIBE_TIME', 60 * 1000 * 86400); // 开通会员过期时间
define('OPEN_VIP_YES_SUBSCRIBE_TIME', 90 * 1000 * 86400); // 开通会员过期时间

define("RELEASE_LEAST_AMOUNT", 100); // 每天每个用户最小释放的金额

// dgv
define("WITHDRAW_MIN_FEE", 0.1); // 提现最小手续费
define("WITHDRAW_MAX_FEE", 1); // 提现最大手续费
define("WITHDRAW_FEE_RATE", 0.002); // 提现手续费汇率
define('DGV_TO_DG', 30);

define("AMOUNT_FORMAT_ERROR", 100501);
define("AMOUNT_FORMAT_OVER_FOUR", 100502);
define("SAFETY_PASSWORD_ERROR", 100503);
define("USER_ACCOUNT_NOT_ENOUGH", 100504);
define("ACCOUNT_TRANSFER_ERROR", 100505);
define("USER_NOT_TRANSFER_MYSELF", 100506);
define("SUBSCRIBE_TYPE_NOT_EXITS", 100507);
define("SUBSCRIBE_ERROR", 100508);
define("USER_ACCOUNT_FROZEN", 100509);
define("EOS_ACCOUNT_NOT_LEGAL", 100510);
define("WITHDRAW_ERROR", 100511);
define("USER_ACCOUNT_ASSET_CODE_ERROR", 100512);
define("USER_HAS_MEMBER", 100513);
define("OPEN_VIP_ERROR", 100514);
define("PUBLISH_AD_CONTENT_TOO_LONG", 100515);
define("PUBLISH_AD_ERROR", 100516);
define("PERMISSION_DEND", 100517);
define("USER_TASK_TOTAL_LIMIT", 100518);
define("USER_TASK_NOT_EXITS", 100519);
define("USER_TASK_FAIL", 100520);
define("USER_TASK_HAS_DO", 100521);
define("SUBSCRIBE_OVER", 100522);
define("SEND_EOS_ERROR", 100523);
define("USER_NOT_AUTH", 100524);
define("DGV_TO_DG_ERROR", 100525);
define("FROZEN_DGV_ERROR", 100526);


return [
    // 默认输出类型
    'no_sign' => [
//        "fund/user/accounts",
//        "fund/user/transfer",
//        "fund/index/coin_subscribe",
//        "fund/user/withdraw",
//        "fund/user/assets_details",
//        "fund/index/open_vip",
//        "fund/user/publish_ad",
//        "fund/user/publish_list",
//        "fund/index/task_list",
//        "fund/index/user_task",
//        "fund/user/news_list",
//        "fund/index/get_rate",
//        "fund/user/frozen_dgv",
    ],

    'no_token' => [

    ],
];