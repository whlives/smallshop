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
 * 订单商品
 */
class OrderGoods extends BaseModel
{
    protected $table = 'order_goods';
    protected $guarded = ['id'];

    //售后状态
    const REFUND_NO = 0;//没有售后
    const REFUND_APPLY = 1;//待审核
    const REFUND_ONGOING = 2;//售后中
    const REFUND_DONE = 3;//售后完成
    const REFUND_CLOSE = 5;//售后关闭
    const REFUND_DESC = [
        self::REFUND_NO => '没有售后',
        self::REFUND_APPLY => '待审核',
        self::REFUND_ONGOING => '售后中',
        self::REFUND_DONE => '售后完成',
        self::REFUND_CLOSE => '售后关闭'
    ];

    //发货状态
    const DELIVERY_OFF = 0;
    const DELIVERY_ON = 1;
    const DELIVERY_DESC = [
        self::DELIVERY_OFF => '待发货',
        self::DELIVERY_ON => '已发货'
    ];

    /**
     * 根据订单id获取订单商品信息
     * @param array $order_id
     * @param bool $is_group 是否需要根据order_id分组
     * @return array
     */
    public static function getGoodsForOrderId(array $order_id, bool $is_group = false)
    {
        //获取订单商品
        $goods = [];
        $goods_res = OrderGoods::select('id', 'order_id', 'goods_id', 'goods_title', 'sku_id', 'image', 'sku_code', 'sell_price', 'market_price', 'buy_qty', 'spec_value', 'delivery', 'refund')
            ->whereIn('order_id', $order_id)
            ->orderBy('id', 'desc')
            ->get();
        if ($goods_res->isEmpty()) {
            return $goods;
        }
        foreach ($goods_res->toArray() as $value) {
            $_item = $value;
            $_item['sell_price'] = $value['sell_price'];
            $_item['market_price'] = $value['market_price'];
            $_item['delivery_text'] = OrderGoods::DELIVERY_DESC[$value['delivery']];
            $_item['refund'] = OrderGoods::REFUND_DESC[$value['refund']];
            if ($is_group) {
                $goods[$value['order_id']][] = $_item;
            } else {
                $goods[] = $_item;
            }
        }
        return $goods;
    }

    /**
     * 根据订单商品id获取订单商品信息
     * @param array $order_goods_id
     * @param bool $is_group 是否需要根据id分组
     * @return array
     */
    public static function getGoodsForId(array $order_goods_id, bool $is_group = false)
    {
        //获取订单商品
        $goods = [];
        $goods_res = OrderGoods::select('id', 'order_id', 'goods_title', 'image', 'sku_code', 'sell_price', 'market_price', 'buy_qty', 'spec_value', 'delivery', 'refund')
            ->whereIn('id', $order_goods_id)
            ->orderBy('id', 'desc')
            ->get();
        if ($goods_res->isEmpty()) {
            return $goods;
        }
        foreach ($goods_res->toArray() as $value) {
            $_item = $value;
            $_item['sell_price'] = $value['sell_price'];
            $_item['market_price'] = $value['market_price'];
            $_item['delivery_text'] = OrderGoods::DELIVERY_DESC[$value['delivery']];
            $_item['refund'] = OrderGoods::REFUND_DESC[$value['refund']];
            if ($is_group) {
                $goods[$value['id']] = $_item;
            } else {
                $goods[] = $_item;
            }
        }
        return $goods;
    }

}
