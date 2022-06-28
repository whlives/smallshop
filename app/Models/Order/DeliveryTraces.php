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
 * 物流轨迹
 */
class DeliveryTraces extends BaseModel
{

    protected $table = 'delivery_traces';
    protected $guarded = ['id'];
}