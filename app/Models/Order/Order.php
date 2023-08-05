<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/14
 * Time: 2:45 PM
 */

namespace App\Models\Order;

use App\Models\BaseModel;
use App\Models\Market\CouponsDetail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * 订单
 */
class Order extends BaseModel
{
    use SoftDeletes;

    protected $table = 'order';
    protected $guarded = ['id'];

    //状态
    const STATUS_WAIT_PAY = 0;
    const STATUS_PAID = 1;
    const STATUS_SHIPMENT = 2;
    const STATUS_PART_SHIPMENT = 7;
    const STATUS_DONE = 3;
    const STATUS_COMPLETE = 5;
    const STATUS_REFUND_COMPLETE = 6;
    const STATUS_WAIT_GROUP = 8;
    const STATUS_CANCEL = 10;
    const STATUS_SYSTEM_CANCEL = 11;
    //后台展示状态
    const STATUS_DESC = [
        self::STATUS_WAIT_PAY => '待支付',
        self::STATUS_PAID => '已支付',
        self::STATUS_SHIPMENT => '待收货',
        self::STATUS_PART_SHIPMENT => '部分发货',
        self::STATUS_DONE => '已收货',
        self::STATUS_COMPLETE => '订单完成',
        self::STATUS_REFUND_COMPLETE => '已退款',
        self::STATUS_WAIT_GROUP => '待成团',
        self::STATUS_CANCEL => '已取消',
        self::STATUS_SYSTEM_CANCEL => '系统取消'
    ];

    //用户展示状态
    const STATUS_MEMBER_DESC = [
        self::STATUS_WAIT_PAY => '待支付',
        self::STATUS_PAID => '待发货',
        self::STATUS_SHIPMENT => '待收货',
        self::STATUS_PART_SHIPMENT => '部分发货',
        self::STATUS_DONE => '已收货',
        self::STATUS_COMPLETE => '交易成功',
        self::STATUS_REFUND_COMPLETE => '已退款',
        self::STATUS_WAIT_GROUP => '待成团',
        self::STATUS_CANCEL => '已取消',
        self::STATUS_SYSTEM_CANCEL => '已取消'
    ];

    //配送方式
    const DELIVERY_COURIER = 1;
    const DELIVERY_SINCE = 2;
    const DELIVERY_DESC = [
        self::DELIVERY_COURIER => '快递',
        self::DELIVERY_SINCE => '自提'
    ];

    //风险订单提示
    const FLAG_NO = 0;
    const FLAG_YES = 1;
    const FLAG_DESC = [
        self::FLAG_NO => '正常',
        self::FLAG_YES => '风险'
    ];

    //用户是否删除
    const IS_DELETE_NO = 0;
    const IS_DELETE_YES = 1;
    const IS_DELETE_DESC = [
        self::IS_DELETE_NO => '否',
        self::IS_DELETE_YES => '是',
    ];

    //商家是否已经结算
    const IS_SETTLEMENT_NO = 0;
    const IS_SETTLEMENT_YES = 1;
    const IS_SETTLEMENT_DESC = [
        self::IS_SETTLEMENT_NO => '否',
        self::IS_SETTLEMENT_YES => '是',
    ];

    /**
     * 获取商品
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function goods()
    {
        return $this->hasMany('App\Models\Order\OrderGoods');
    }

    /**
     * 获取发货信息
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function delivery()
    {
        return $this->hasMany('App\Models\Order\OrderDelivery');
    }

    /**
     * 获取订单日志
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function log()
    {
        return $this->hasMany('App\Models\Order\OrderLog');
    }

    /**
     * 获取发票信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function invoice()
    {
        return $this->hasOne('App\Models\Order\OrderInvoice');
    }

    /**
     * 获取订单信息
     * @param string $order_no
     * @param int $m_id
     * @return mixed
     * @throws \App\Exceptions\ApiError
     */
    public static function getInfo(string $order_no, int $m_id)
    {
        $order = self::where(['m_id' => $m_id, 'order_no' => $order_no])->first();
        if (!$order) {
            api_error(__('api.order_error'));
        }
        return $order->toArray();
    }

    /**
     * 提交订单
     * @param array $order_data
     * @return bool
     */
    public static function submitOrder(array $order_data)
    {
        try {
            DB::transaction(function () use ($order_data) {
                foreach ($order_data as $order) {
                    //商品信息
                    $order_goods = $order['goods'] ?? [];
                    if (!$order_goods) return false;
                    unset($order['goods']);
                    //发票信息
                    $invoice = [];
                    if (isset($order['invoice'])) {
                        $invoice = $order['invoice'];
                        unset($order['invoice']);
                    }
                    //添加订单
                    $order_res = self::create($order);
                    $order_id = $order_res->id;
                    //更新优惠券使用状态
                    if ($order['coupons_id']) {
                        CouponsDetail::where('id', $order['coupons_id'])->update(['is_use' => CouponsDetail::USE_ON, 'use_at' => get_date()]);
                    }
                    //添加订单商品
                    foreach ($order_goods as $goods) {
                        $goods['order_id'] = $order_id;
                        OrderGoods::create($goods);
                    }
                    //发票信息
                    if ($invoice) {
                        $invoice['order_id'] = $order_id;
                        OrderInvoice::create($invoice);
                    }
                }
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
