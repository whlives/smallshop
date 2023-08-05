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
 * 快递单打印模板
 */
class OrderDeliveryTemplate extends BaseModel
{
    protected $table = 'order_delivery_template';
    protected $guarded = ['id'];

}
