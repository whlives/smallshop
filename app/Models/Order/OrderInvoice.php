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
 * 订单发票
 */
class OrderInvoice extends BaseModel
{
    protected $table = 'order_invoice';
    protected $guarded = ['id'];

    public $timestamps = false;
    
    //类型
    const TYPE_PERSONAL = 1;
    const TYPE_ENTERPRISE = 2;
    const TYPE_DESC = [
        self::TYPE_PERSONAL => '个人',
        self::TYPE_ENTERPRISE => '企业'
    ];

}
