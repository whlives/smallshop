<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/1
 * Time: 4:31 PM
 */

namespace App\Models\Member;

use App\Models\BaseModel;

/**
 * 会员资料
 */
class MemberProfile extends BaseModel
{
    //性别
    const SEX_UNKNOWN = 0;
    const SEX_BOY = 1;
    const SEX_GIRL = 2;
    const SEX_DESC = [
        self::SEX_UNKNOWN => '未知',
        self::SEX_BOY => '男',
        self::SEX_GIRL => '女',
    ];

    protected $table = 'member_profile';
    protected $guarded = [];

    public $timestamps = false;
}