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
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    const NOT_DELIVERY = 1;//无需物流的快递id
    protected $table = 'express_company';
    protected $guarded = ['id'];

}
