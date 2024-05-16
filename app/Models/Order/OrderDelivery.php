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
 * 订单物流
 */
class OrderDelivery extends BaseModel
{
    protected $table = 'order_delivery';
    protected $guarded = ['id'];

    /**
     * 获取订单物流信息
     * @param array $order_info
     * @return string[]
     */
    public static function deliveryInfo(array $order_info)
    {
        //查询物流
        $delivery = [
            'title' => '',
            'accept_time' => ''
        ];
        if ($order_info['status'] == Order::STATUS_PART_SHIPMENT || $order_info['status'] == Order::STATUS_SHIPMENT || $order_info['status'] == Order::STATUS_DONE) {
            $delivery_res = OrderDelivery::select('code', 'company_code')->where('order_id', $order_info['id'])->orderBy('id', 'desc')->first();
            if ($delivery_res) {
                $delivery_traces = DeliveryTraces::where(['company_code' => $delivery_res['company_code'], 'code' => $delivery_res['code']])->orderBy('id', 'desc')->first();
                if ($delivery_traces) {
                    $delivery['title'] = $delivery_traces['info'];
                    $delivery['accept_time'] = $delivery_traces['accept_time'];
                }
            }
        }
        return $delivery;
    }
}
