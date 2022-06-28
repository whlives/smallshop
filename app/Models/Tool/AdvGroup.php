<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 2:14 PM
 */

namespace App\Models\Tool;

use App\Models\BaseModel;

/**
 * 广告组
 */
class AdvGroup extends BaseModel
{
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    protected $table = 'adv_group';
    protected $guarded = ['id'];

}