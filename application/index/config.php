<?php
define("REGISTER_REWARD_DGV_1W", 10000); // 注册默认送的DGV 10000个
define("REGISTER_REWARD_DGV_1K", 1000); // 注册默认送的DGV 1000个
define("REGISTER_REWARD_DGV", 0); // 注册默认送的DG
define("REWARD_DAY", '2019-09-30'); // 送1w的日期
define("UP_USER_LEVEL", 4); // 查询上级好友个数 5个,  5-1

define("VERSION_APP_IOS", 100); // 版本号
define("VERSION_APP_ANDROID", 100); // 版本号
define("APP_URL_ANDROID", 'http://www.dgvtoken.com/html/download/android/dgv_100.apk'); // app下载url
define("APP_URL_IOS", 'https://www.dgvtoken.com/html/download/index.html'); // app下载url
define("IS_UPDATE_ANDROID", 1); // app是否强制更新  1不是,2是
define("IS_UPDATE_IOS", 1); // app是否强制更新  1不是,2是
define("UPDATE_CONTENT", 100); // app更新内容

define("MOBILE_HAS_BIND", 100402);
define("SEND_CODE_REQUEST_TOO_MANY", 100403);
define("SEND_VERIFICATION_CODE_ERROR", 100404);

define("TWO_PASSWORDS_DIFFERENT", 100405);
define("TWO_SAFETY_PASSWORDS_DIFFERENT", 100406);
define("STR_LENGTH_NOT_LEGAL", 100407);
define("PASSWORD_SAFETY_PASSWORD_EQUALLY", 100408);
define("VERIFICATION_CODE_ERROR", 100409);
define("VERIFICATION_CODE_EXPIRE", 100410);
define("INVITE_CODE_NOT_EXIST", 100411);
define("PASSWORD_ERROR", 100413);
define("REGISTER_MOBILE_SEND_CODE_MOBILE_DIFFERENT", 100414);
define("LOGIN_PASSWORD_SAFETY_PASSWORD_EQUAL", 100415);
define("REGISTER_ERROR", 100417);
define("STATUS_NO_UPDATE", 100199);
define("UPLOAD_PIC_FORMAT_ERROR", 100420);
define("USER_NAME_HAS_EXITS", 100421);



return [
    // 默认输出类型
    'no_sign' => [
//        "index/index/send_sms",
//        "index/index/sign_up",
//        "index/index/sign_in",
//        "index/user/find_password",
//        "index/user/modify_password",
//        "index/user/my_index",
//        "index/user/update_user",
//        "index/user/invite_image",
//        "index/index/invite_user",
//        "index/index/problem_message",
//        "index/index/upgrade_check",
        "index/user/get_image", // 一直开着
        "index/index/test", // 一直开着
    ],

    'no_token' => [
        "index/index/send_sms",
        "index/index/sign_in",
        "index/index/sign_up",
        "index/user/find_password",
        "index/index/problem_message",
        "index/index/upgrade_check",
    ],
];
