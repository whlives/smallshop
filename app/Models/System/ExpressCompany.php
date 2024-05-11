<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 9:45 PM
 */

namespace App\Models\System;

use App\Models\BaseModel;

/**
 * 快递公司
 */
class ExpressCompany extends BaseModel
{
    protected $table = 'express_company';
    protected $guarded = ['id'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    //快递类型
    const TYPE_EXPRESS = 1;
    const TYPE_LOCAL = 2;
    const TYPE_VIRTUAL = 3;
    const TYPE_SELF_PICKUP = 4;
    const TYPE_DESC = [
        self::TYPE_EXPRESS => '物流',
        self::TYPE_LOCAL => '同城',
        self::TYPE_VIRTUAL => '虚拟发货',
        self::TYPE_SELF_PICKUP => '自提',
    ];
    
}
