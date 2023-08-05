<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/1
 * Time: 4:30 PM
 */

namespace App\Models\Member;

use App\Models\BaseModel;

/**
 * 会员三方登录
 */
class MemberAuth extends BaseModel
{
    protected $table = 'member_auth';
    protected $guarded = ['id'];
    
    //类型
    const TYPE_WECHAT = 1;
    const TYPE_WEIBO = 2;
    const TYPE_QQ = 3;
    const TYPE_DESC = [
        self::TYPE_WECHAT => '微信',
        self::TYPE_WEIBO => '微博',
        self::TYPE_QQ => 'qq',
    ];

}
