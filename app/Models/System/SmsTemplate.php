<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 2:14 PM
 */

namespace App\Models\System;

use App\Models\BaseModel;

/**
 * 短信模板
 */
class SmsTemplate extends BaseModel
{
    //短息类型
    const TYPE_LOGIN = 'login';
    const TYPE_REGISTER = 'register';
    const TYPE_FIND_PASSWORD = 'find_password';
    const TYPE_RESET_PASSWORD = 'reset_password';
    const TYPE_ADMIN_LOGIN = 'admin_login';
    const TYPE_ADMIN_LOGIN_NOTICE = 'admin_login_notice';
    const TYPE_SELLER_LOGIN = 'seller_login';
    const TYPE_DESC = [
        self::TYPE_LOGIN => '登录',
        self::TYPE_REGISTER => '注册',
        self::TYPE_FIND_PASSWORD => '找回密码',
        self::TYPE_RESET_PASSWORD => '重置密码',
        self::TYPE_ADMIN_LOGIN => '管理员登录',
        self::TYPE_ADMIN_LOGIN_NOTICE => '管理员登录提醒',
        self::TYPE_SELLER_LOGIN => '商户登录'
    ];
    protected $table = 'sms_template';
    protected $guarded = ['id'];
}