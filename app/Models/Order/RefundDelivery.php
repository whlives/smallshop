<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/14
 * Time: 2:45 PM
 */

namespace App\Models\Order;

use App\Models\BaseModel;

/**
 * 售后物流
 */
class RefundDelivery extends BaseModel
{

    //状态
    const TYPE_MEMBER = 1;
    const TYPE_SELLER = 2;

    const TYPE_DESC = [
        self::TYPE_MEMBER => '用户',
        self::TYPE_SELLER => '商家',
    ];

    protected $table = 'refund_delivery';
    protected $guarded = ['id'];

}