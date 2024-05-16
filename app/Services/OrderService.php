<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/20
 * Time: 21:14 PM
 */

namespace App\Services;

use App\Jobs\OrderPayAfter;
use App\Jobs\OrderReward;
use App\Libs\Delivery;
use App\Libs\Weixin\MiniProgram;
use App\Models\Financial\Balance;
use App\Models\Financial\BalanceDetail;
use App\Models\Financial\SellerBalance;
use App\Models\Financial\SellerBalanceDetail;
use App\Models\Financial\Trade;
use App\Models\Financial\TradeRefund;
use App\Models\Goods\Comment;
use App\Models\Goods\CommentUrl;
use App\Models\Goods\Goods;
use App\Models\Goods\GoodsNum;
use App\Models\Goods\GoodsSku;
use App\Models\Market\CouponsDetail;
use App\Models\Market\PromoGroupOrder;
use App\Models\Market\PromoSeckill;
use App\Models\Member\Member;
use App\Models\Order\Order;
use App\Models\Order\OrderDelivery;
use App\Models\Order\OrderDeliveryTemplate;
use App\Models\Order\OrderGoods;
use App\Models\Order\OrderLog;
use App\Models\Order\Refund;
use App\Models\Seller\Seller;
use App\Models\System\ExpressCompany;
use App\Models\System\Payment;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\DB;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class OrderService
{
    /**
     * 生成订单号
     * @return string
     */
    public static function getOrderNo(): string
    {
        return date('ymdHis', time()) . rand(100000, 999999);
    }

    /**
     * 订单是否可以取消
     * @param array $order
     * @return bool
     */
    public static function isCancel(array $order)
    {
        if (isset($order['status']) && ($order['status'] == Order::STATUS_WAIT_PAY || $order['status'] == Order::STATUS_PAID)) {
            return true;
        }
        return false;
    }

    /**
     * 订单是否可以支付
     * @param array $order
     * @return bool
     */
    public static function isPay(array $order)
    {
        if (isset($order['status']) && $order['status'] == Order::STATUS_WAIT_PAY) {
            return true;
        }
        return false;
    }

    /**
     * 订单是否可以确认
     * @param array $order
     * @return bool
     */
    public static function isConfirm(array $order)
    {
        if (isset($order['status']) && $order['status'] == Order::STATUS_SHIPMENT) {
            return true;
        }
        return false;
    }

    /**
     * 订单是否可以完成
     * @param array $order
     * @return bool
     */
    public static function isComplete(array $order)
    {
        if (isset($order['status']) && ($order['status'] == Order::STATUS_DONE || $order['status'] == Order::STATUS_REFUND_COMPLETE) && $order['is_settlement'] == Order::IS_SETTLEMENT_NO) {
            return true;
        }
        return false;
    }

    /**
     * 订单是否可以发货
     * @param array $order
     * @return bool
     */
    public static function isDelivery(array $order)
    {
        if (isset($order['status']) && ($order['status'] == Order::STATUS_PART_SHIPMENT || $order['status'] == Order::STATUS_PAID)) {
            return true;
        }
        return false;
    }

    /**
     * 订单是否可以撤销发货
     * @param array $order
     * @return bool
     */
    public static function isUnDelivery(array $order)
    {
        if (isset($order['status']) && ($order['status'] == Order::STATUS_PART_SHIPMENT || $order['status'] == Order::STATUS_SHIPMENT)) {
            return true;
        }
        return false;
    }

    /**
     * 订单是否可以修改地址
     * @param array $order
     * @return bool
     */
    public static function isUpdateAddress(array $order)
    {
        if (isset($order['status']) && ($order['status'] == Order::STATUS_WAIT_PAY || $order['status'] == Order::STATUS_PAID)) {
            return true;
        }
        return false;
    }

    /**
     * 订单是否可以评价
     * @param array $order
     * @return bool
     */
    public static function isComment(array $order)
    {
        if (isset($order['status']) && ($order['status'] == Order::STATUS_DONE || $order['status'] == Order::STATUS_COMPLETE) && $order['comment_at'] == null) {
            return true;
        }
        return false;
    }

    /**
     * 用户是否可以删除订单
     * @param array $order
     * @return bool
     */
    public static function isUserDelete(array $order)
    {
        if (isset($order['status']) && in_array($order['status'], [Order::STATUS_CANCEL, Order::STATUS_SYSTEM_CANCEL, Order::STATUS_DONE, Order::STATUS_COMPLETE])) {
            return true;
        }
        return false;
    }

    /**
     * 是否显示售后按钮
     * @param array $order
     * @return bool
     */
    public static function showRefundButton(array $order)
    {
        if (in_array($order['status'], [Order::STATUS_PAID, Order::STATUS_SHIPMENT, Order::STATUS_PART_SHIPMENT, Order::STATUS_DONE, Order::STATUS_REFUND_COMPLETE])) {
            return true;
        }
        return false;
    }

    /**
     * 组装订单信息
     * @param int $m_id
     * @param array $seller_goods
     * @param array $param
     * @return array|bool|null
     */
    public static function formatOrder(int $m_id, array $seller_goods, array $param)
    {
        //三级分销推荐人
        [$level_one_m_id, $level_two_m_id] = Member::getLevelParentId($m_id);
        //开始组装订单信息
        $order_info = $order_no_arr = [];
        $subtotal = 0;
        foreach ($seller_goods as $value) {
            $seller_id = $value['seller']['id'];
            $order_no = self::getOrderNo();
            $order_no_arr[] = $order_no;
            $subtotal += $value['price']['subtotal'];
            $_order = [
                'm_id' => $m_id,
                'seller_id' => $seller_id,
                'order_no' => $order_no,
                'status' => Order::STATUS_WAIT_PAY,
                'promo_type' => current($value['goods'])['promo_type'],
                'goods_type' => current($value['goods'])['type'],
                'product_num' => $value['all_buy_qty'],
                'sell_price_total' => $value['price']['sell_price'],
                'market_price_total' => $value['price']['market_price'],
                'delivery_type' => $param['delivery'][$seller_id]['delivery_type'] ?? 1,
                'delivery_time' => $param['delivery'][$seller_id]['delivery_time'] ?? '',
                'delivery_price' => $value['delivery']['delivery_price'],
                'delivery_price_real' => $value['delivery']['delivery_price_real'],
                'promotion_price' => $value['price']['promotion_price'],
                'promotion_text' => isset($value['promotion']) ? json_encode($value['promotion'], JSON_UNESCAPED_UNICODE) : '',
                'subtotal' => $value['price']['subtotal'],
                'coupons_id' => $value['coupons_id'] ?? 0,
                'platform' => get_platform(),
                'full_name' => $param['address']['full_name'],
                'tel' => $param['address']['tel'],
                'prov' => $param['address']['prov_name'],
                'city' => $param['address']['city_name'],
                'area' => $param['address']['area_name'],
                'address' => $param['address']['address'],
                'note' => $param['note'][$seller_id] ?? '',
                'level_one_m_id' => $level_one_m_id,
                'level_two_m_id' => $level_two_m_id
            ];
            //发票信息
            if ($param['invoice'] && isset($param['invoice'][$seller_id]['type'])) {
                $_order['invoice'] = [
                    'type' => $param['invoice'][$seller_id]['type'] ?? '',
                    'title' => $param['invoice'][$seller_id]['title'] ?? '',
                    'tax_no' => $param['invoice'][$seller_id]['tax_no'] ?? ''
                ];
            }
            $order_goods = [];
            foreach ($value['goods'] as $goods) {
                $_order_goods = [
                    'm_id' => $m_id,
                    'goods_id' => $goods['goods_id'],
                    'goods_title' => $goods['title'],
                    'sku_id' => $goods['sku_id'],
                    'sku_code' => $goods['sku_code'],
                    'image' => $goods['image'],
                    'sell_price' => $goods['show_price'],
                    'market_price' => $goods['line_price'],
                    'promotion_price' => $goods['promotion_price'],
                    'buy_qty' => $goods['buy_qty'],
                    'weight' => $goods['weight'] * $goods['buy_qty'],
                    'spec_value' => $goods['spec_value'],
                    'seller_id' => $seller_id,
                    'level_one_pct' => $goods['level_one_pct'],
                    'level_two_pct' => $goods['level_two_pct']
                ];
                $order_goods[] = $_order_goods;
            }
            $_order['goods'] = $order_goods;
            $order_info[] = $_order;
        }
        return [$order_info, $order_no_arr, $subtotal];
    }

    /**
     * 减少库存
     * @param array $cart skuid和buy_qty数组
     * @return false|void
     */
    public static function stockDecr(array $cart)
    {
        if (!$cart) return false;
        foreach ($cart as $val) {
            GoodsSku::query()->where('id', $val['sku_id'])->decrement('stock', $val['buy_qty']);
            GoodsNum::query()->where('goods_id', $val['goods_id'])->increment('sale', $val['buy_qty']);//增加销量
        }
    }

    /**
     * 还原库存
     * @param array $cart skuid和buy_qty数组
     * @return false|void
     */
    public static function stockIncr(array $cart)
    {
        if (!$cart) return false;
        foreach ($cart as $val) {
            GoodsSku::query()->where('id', $val['sku_id'])->increment('stock', $val['buy_qty']);
            //GoodsNum::query()->where('goods_id', $val['goods_id'])->decrement('sale', $val['buy_qty']);//减少销量
        }
    }

    /**
     * 根据订单还原库存
     * @param array $order
     * @return void
     */
    public static function orderStockIncr(array $order)
    {
        $order_goods = OrderGoods::query()->select('buy_qty', 'sku_id', 'goods_id')->where('order_id', $order['id'])->get();
        if (!$order_goods->isEmpty()) {
            $order_goods = $order_goods->toArray();
            self::stockIncr($order_goods);
            //秒杀的需要还原redis库存
            if ($order['promo_type'] == Goods::PROMO_TYPE_SECKILL) {
                foreach ($order_goods as $value) {
                    PromoSeckill::stockIncr($value);
                }
            }
        }
    }

    /**
     * 用户订单支付完成修改订单状态
     * @param array $notify_data 支付回调信息
     * @return bool
     */
    public static function updatePayOrder(array $notify_data)
    {
        //修改订单状态
        $update_order = [
            'trade_id' => $notify_data['trade_id'],
            'status' => Order::STATUS_PAID,
            'payment_id' => $notify_data['payment_id'],
            'payment_no' => $notify_data['payment_no'],
            'flag' => $notify_data['flag'],
            'pay_at' => get_date()
        ];
        $res = Order::query()->where('status', Order::STATUS_WAIT_PAY)->whereIn('order_no', $notify_data['order_no'])->update($update_order);
        if ($res) {
            dispatch(new OrderPayAfter($notify_data['order_no']));//将订单加入队列处理后续的
            return true;
        }
        return false;
    }

    /**
     * 订单操作按钮
     * @param array $order 订单信息
     * @return int[]
     */
    public static function orderButton(array $order)
    {
        $button = [
            'cancel' => 0,//取消订单
            'payment' => 0,//支付
            'confirm' => 0,//确认
            'delete' => 0,//删除
            'comment' => 0,//评价
            'delivery' => 0//物流
        ];
        if (self::isCancel($order)) {
            $button['cancel'] = 1;
        }
        if (self::isPay($order)) {
            $button['payment'] = 1;
        }
        if (self::isConfirm($order)) {
            $button['confirm'] = 1;
        }
        if (self::isUserDelete($order)) {
            $button['delete'] = 1;
        }
        if (self::isComment($order)) {
            $button['comment'] = 1;
        }
        if (in_array($order['status'], [Order::STATUS_SHIPMENT, Order::STATUS_PART_SHIPMENT, Order::STATUS_DONE])) {
            $button['delivery'] = 1;
        }
        return $button;
    }

    /**
     * 取消拼团订单
     * @param array $order
     * @param array $member_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|Application|Translator|string|null
     */
    public static function groupOrderCancel(array $order, array $member_data, int $user_type = 0, string|null $note = '')
    {
        if ($order['status'] != Order::STATUS_WAIT_PAY && $order['status'] != Order::STATUS_WAIT_GROUP) {
            return __('api.order_status_error');
        }
        $res = self::cancelOrder($order, $member_data, $user_type, $note);
        return $res;
    }

    /**
     * 取消订单
     * @param array $order
     * @param array $member_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|Application|Translator|string|null
     */
    public static function cancel(array $order, array $member_data, int $user_type = 0, string|null $note = '')
    {
        if (!self::isCancel($order)) {
            return __('api.order_status_error');
        }
        try {
            $res = self::cancelOrder($order, $member_data, $user_type, $note);
            if ($res === true) {
                //如果是拼团订单需要处理拼团信息
                if ($order['promo_type'] == Goods::PROMO_TYPE_GROUP) PromoGroupOrder::cancel($order['id']);
            }
            return $res;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 取消订单公共
     * @param array $order
     * @param array $member_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|Application|Translator|string|null
     */
    public static function cancelOrder(array $order, array $member_data, int $user_type = 0, string|null $note = '')
    {
        $order_log = [
            'order_id' => $order['id'],
            'user_type' => $user_type,
            'user_id' => $member_data['id'],
            'username' => $member_data['username'],
            'action' => OrderLog::ACTION_CANCEL,
            'note' => $note
        ];
        $status = $user_type == OrderLog::USER_TYPE_SYSTEM ? Order::STATUS_SYSTEM_CANCEL : Order::STATUS_CANCEL;
        if ($order['status'] == Order::STATUS_PAID || $order['status'] == Order::STATUS_WAIT_GROUP) {
            $status = Order::STATUS_REFUND_COMPLETE;//已经支付的状态修改为全部退款
            //已经支付的需要查询是否已经有部分订单在申请退款
            $refund = OrderGoods::query()->where(['order_id' => $order['id']])->whereNotIn('refund', [OrderGoods::REFUND_NO, OrderGoods::REFUND_CLOSE])->count();
            if ($refund > 0) {
                return __('api.order_refund_ing_not_cancel');
            }
        }
        try {
            DB::transaction(function () use ($order, $order_log, $status) {
                Order::query()->where(['id' => $order['id']])->update(['status' => $status, 'close_at' => get_date()]);
                OrderLog::query()->create($order_log);//添加订单日志
                if ($order['coupons_id']) {
                    //存在优惠券的时候需要返还
                    CouponsDetail::query()->where('id', $order['coupons_id'])->update(['is_use' => CouponsDetail::USE_OFF]);
                }
            });
            //还原库存
            self::orderStockIncr($order);
            //已经支付的需要做退款操作
            if (($order['status'] == Order::STATUS_PAID || $order['status'] == Order::STATUS_WAIT_GROUP) && $order['trade_id']) {
                $refund_res = TradeService::tradeRefund($order['trade_id'], $order['order_no'], $order['subtotal'], TradeRefund::TYPE_ORDER, '订单取消');
                if ($refund_res !== true) {
                    return __($refund_res);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 后台支付订单支付
     * @param array $order
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|Application|Translator|string|null
     */
    public static function pay(array $order, array $user_data, int $user_type, string|null $note = '')
    {
        if (!self::isPay($order)) {
            return __('admin.order_status_error');
        }
        $order_log = [
            'order_id' => $order['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => OrderLog::ACTION_PAY,
            'note' => $note
        ];
        try {
            DB::transaction(function () use ($order, $order_log) {
                Order::query()->where('id', $order['id'])->update(['status' => Order::STATUS_PAID, 'pay_at' => get_date()]);
                OrderLog::query()->create($order_log);//添加订单日志
            });
            dispatch(new OrderPayAfter([$order['order_no']]));//将订单加入队列处理后续的
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 发货
     * @param array $order
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @param array $param 其他参数，order_goods_id 发货订单商品，company_id快递公司id，code快递单号
     * @return array|bool|Application|Translator|string|null
     */
    public static function delivery(array $order, array $user_data, int $user_type, string|null $note, array $param)
    {
        if (!self::isDelivery($order)) {
            return __('admin.order_status_error');
        }
        $express_company = ExpressCompany::query()->select('title', 'code', 'type', 'weixin_code')->where('id', $param['company_id'])->first();
        if ($express_company['type'] == ExpressCompany::TYPE_EXPRESS && !$express_company['code']) {
            return __('admin.delivery_code_error');
        }
        $delivery_data = [
            'order_id' => $order['id'],
            'order_goods_id' => json_encode($param['order_goods_id']),
            'company_code' => $express_company['code'],
            'company_name' => $express_company['title'],
            'code' => $param['code'],
            'note' => $note
        ];
        $order_log = [
            'order_id' => $order['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => OrderLog::ACTION_SEND,
            'note' => $note
        ];
        try {
            DB::transaction(function () use ($order, $order_log, $delivery_data, $param) {
                //修改商品发货状态
                OrderGoods::query()->where('order_id', $order['id'])->whereIn('id', $param['order_goods_id'])->update(['delivery' => OrderGoods::DELIVERY_ON]);
                $order_status = Order::STATUS_PART_SHIPMENT;
                if (OrderGoods::checkOrderDelivery($order['id'])) {
                    $order_status = Order::STATUS_SHIPMENT;//全部发货后修改订单状态
                }
                Order::query()->where(['id' => $order['id']])->update(['status' => $order_status, 'send_at' => get_date()]);
                OrderLog::query()->create($order_log);//添加订单日志
                OrderDelivery::query()->create($delivery_data);//添加发货信息
            });
            //订阅物流消息
            if ($param['code']) {
                $delivery = new Delivery();
                $delivery->subscribe($express_company['code'], $param['code']);
            }
            //微信类的需要发货后推送
            if ($order['payment_id'] == Payment::PAYMENT_WECHAT) {
                $miniprogram = new MiniProgram();
                $miniprogram->uploadShippingInfo($order, $express_company->toArray(), $param['code']);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 撤销发货
     * @param array $order
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|Application|Translator|string|null
     */
    public static function unDelivery(array $order, array $user_data, int $user_type = 0, string|null $note = '')
    {
        if (!self::isUnDelivery($order)) {
            return __('admin.order_status_error');
        }
        $order_log = [
            'order_id' => $order['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => OrderLog::ACTION_UN_SEND,
            'note' => $note
        ];
        try {
            DB::transaction(function () use ($order, $order_log) {
                //修改商品发货状态
                OrderGoods::query()->where('order_id', $order['id'])->update(['delivery' => OrderGoods::DELIVERY_OFF]);
                Order::query()->where('id', $order['id'])->update(['status' => Order::STATUS_PAID, 'send_at' => null]);
                OrderDelivery::query()->where(['order_id' => $order['id']])->delete();//删除物流信息
                OrderLog::query()->create($order_log);//添加订单日志
            });
            //微信订单撤销发货后修改交易单发货状态
            if ($order['payment_id'] == Payment::PAYMENT_WECHAT) {
                Trade::query()->where('id', $order['trade_id'])->update(['send_at' => null]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 确认订单
     * @param array $order
     * @param array $member_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|Application|Translator|string|null
     */
    public static function confirm(array $order, array $member_data, int $user_type = 0, string|null $note = '')
    {
        if (!self::isConfirm($order)) {
            return __('api.order_status_error');
        }
        $order_log = [
            'order_id' => $order['id'],
            'user_type' => $user_type,
            'user_id' => $member_data['id'],
            'username' => $member_data['username'],
            'action' => OrderLog::ACTION_CONFIRM,
            'note' => $note
        ];
        try {
            DB::transaction(function () use ($order, $order_log) {
                Order::query()->where(['id' => $order['id']])->update(['status' => Order::STATUS_DONE, 'done_at' => get_date()]);
                OrderLog::query()->create($order_log);//添加订单日志
            });
            //微信订单发送确认收货提醒
            if ($order['payment_id'] == Payment::PAYMENT_WECHAT) {
                $miniprogram = new MiniProgram();
                $miniprogram->confirmShippingInfo($order);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 完成订单
     * @param array $order
     * @param array $member_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|Application|Translator|string|null
     */
    public static function complete(array $order, array $member_data, int $user_type = 0, string|null $note = '')
    {
        $order_ids = array_column($order, 'id');
        $seller_ids = array_column($order, 'seller_id');
        try {
            //查询正在售后的订单
            $refund_order_id = OrderGoods::query()->whereIn('order_id', array_unique($order_ids))->whereIn('refund', [OrderGoods::REFUND_APPLY, OrderGoods::REFUND_ONGOING])->pluck('order_id')->toArray();
            $new_order_ids = array_diff($order_ids, $refund_order_id);//获取没有售后的订单
            //查询商家结算信息
            $seller = Seller::query()->whereIn('id', array_unique($seller_ids))->pluck('pct', 'id');
            //查询订单下的商品
            $order_goods_res = OrderGoods::query()->select('id', 'order_id', 'sell_price', 'promotion_price', 'buy_qty', 'level_one_pct', 'level_two_pct')->whereIn('order_id', $new_order_ids)->get();
            if ($order_goods_res->isEmpty()) {
                return __('api.order_goods_error');
            }
            $order_goods = [];
            foreach ($order_goods_res as $_goods) {
                $order_goods[$_goods['order_id']][] = $_goods;
            }
            //查询已经完成的售后单
            $refund_res = Refund::query()->select('order_id', 'order_goods_id', 'amount')->whereIn('order_id', $new_order_ids)->where('status', Refund::STATUS_DONE)->get();
            $refund_data = [];
            if (!$refund_res->isEmpty()) {
                foreach ($refund_res as $_refund) {
                    $refund_data[$_refund['order_id']][$_refund['order_goods_id']] = $_refund;
                }
            }
            //这里开始结算
            foreach ($order as $_order) {
                if (!self::isComplete($_order)) {
                    continue;//状态或者已经结算的不处理
                }
                if (!in_array($_order['id'], $new_order_ids) || !isset($order_goods[$_order['id']])) {
                    continue;//只有没有售后和有订单商品的才处理
                }
                //修改订单状态
                $order_update = [
                    'is_settlement' => Order::IS_SETTLEMENT_YES,
                    'complete_at' => get_date()
                ];
                if ($_order['status'] == Order::STATUS_DONE) {
                    $order_update['status'] = Order::STATUS_COMPLETE;//全部退款的不修改订单状态
                }
                $_res = Order::query()->where(['id' => $_order['id'], 'is_settlement' => Order::IS_SETTLEMENT_NO])->update($order_update);
                if (!$_res) return false;//订单状态修改失败
                //计算订单金额
                $refund_amount = $level_one_amount = $level_two_amount = 0;
                foreach ($order_goods[$_order['id']] as $_goods) {
                    $_refund_amount = $refund_data[$_order['id']][$_goods['id']]['amount'] ?? 0;//单个商品售后金额
                    $_goods_amount = ($_goods['sell_price'] * $_goods['buy_qty']) - $_goods['promotion_price'] - $_refund_amount;//商品最终金额
                    $refund_amount = $refund_amount + $_refund_amount;//累计售后金额
                    if ($_order['level_one_m_id']) {
                        //只有存在一级的时候才计算
                        $level_one_amount = $level_one_amount + format_price($_goods_amount * $_goods['level_one_pct'] / 100);//累计一级推荐提成
                    }
                    if ($_order['level_two_m_id']) {
                        //只有存在二级的时候才计算
                        $level_two_amount = $level_two_amount + format_price($_goods_amount * $_goods['level_two_pct'] / 100);//累计二级推荐提成
                    }
                }
                //给商家结算
                $pct = $seller[$_order['seller_id']] ?? 0;//商家结算手续费比例
                $amount = format_price($_order['subtotal'] - $refund_amount - $level_one_amount - $level_two_amount);//待结算金额（这里需要减去推荐佣金）
                $poundage = format_price($amount * ($pct / 100));//手续费
                SellerBalance::updateAmount($_order['seller_id'], $amount, SellerBalanceDetail::EVENT_ORDER, $_order['order_no'], '订单完成结算');
                if ($poundage) {
                    SellerBalance::updateAmount($_order['seller_id'], -$poundage, SellerBalanceDetail::EVENT_POUNDAGE, $_order['order_no'], '订单结算手续费');
                }
                //提成结算
                if ($level_one_amount) {
                    Balance::updateAmount($_order['level_one_m_id'], $level_one_amount, BalanceDetail::EVENT_RECOMMEND_ORDER, $_order['order_no'], '推荐订单收益');
                }
                if ($level_two_amount) {
                    Balance::updateAmount($_order['level_two_m_id'], $level_two_amount, BalanceDetail::EVENT_RECOMMEND_ORDER, $_order['order_no'], '推荐订单收益');
                }
                OrderReward::dispatch($_order['id']);//将订单奖励加入队列处理后续的
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 订单商品评价
     * @param array $order
     * @param array $comment
     * @return array|bool|Application|Translator|string|null
     */
    public static function commentPut(array $order, array $comment)
    {
        if (!self::isComment($order)) {
            return __('api.order_status_error');
        }
        try {
            DB::transaction(function () use ($order, $comment) {
                Order::query()->where(['id' => $order['id']])->update(['comment_at' => get_date()]);
                foreach ($comment as $data) {
                    $image = $data['image'] ?? [];
                    $video = $data['video'] ?? [];
                    unset($data['image'], $data['video']);
                    $result = Comment::query()->create($data);
                    $comment_id = $result->id;
                    $ulr_data = [];
                    if ($image) {
                        foreach ($image as $value) {
                            if ($value) {
                                $ulr_data[] = [
                                    'comment_id' => $comment_id,
                                    'type' => CommentUrl::TYPE_IMAGE,
                                    'url' => $value,
                                ];
                            }
                        }
                    }
                    if ($video) {
                        foreach ($video as $value) {
                            if ($value) {
                                $ulr_data[] = [
                                    'comment_id' => $comment_id,
                                    'type' => CommentUrl::TYPE_VIDEO,
                                    'url' => $value,
                                ];
                            }
                        }

                    }
                    if ($ulr_data) CommentUrl::query()->insert($ulr_data);
                }
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 修改价格
     * @param array $order
     * @param float $discount_price
     * @param float $delivery_price_real
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|Application|Translator|string|null
     */
    public static function updatePrice(array $order, float $discount_price, float $delivery_price_real, array $user_data, int $user_type, string|null $note = '')
    {
        if (!self::isPay($order)) {
            return __('admin.order_status_error');
        }
        $promotion_after_price = $order['sell_price_total'] - $order['promotion_price'];
        if ($delivery_price_real < 0) {
            return __('admin.order_delivery_price_real_error');
        } elseif (($promotion_after_price + $discount_price) < 0) {
            return '改价优惠金额不能大于' . $promotion_after_price . '元';
        }
        $subtotal = $promotion_after_price + $discount_price + $delivery_price_real;
        $update_data = [
            'discount_price' => $discount_price,
            'delivery_price_real' => $delivery_price_real,
            'subtotal' => $subtotal
        ];
        $order_log = [
            'order_id' => $order['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => OrderLog::ACTION_EDIT,
            'note' => '改价金额' . $discount_price . ',修改运费金额' . $delivery_price_real . $note
        ];
        try {
            DB::transaction(function () use ($order, $update_data, $order_log) {
                Order::query()->where('id', $order['id'])->update($update_data);
                OrderLog::query()->create($order_log);//添加订单日志
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 电子面单发货
     * @param array $order
     * @param array $user_data
     * @param int $user_type
     * @param array $express_company
     * @param array $api_delivery_data
     * @return bool
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function apiDelivery(array $order, array $user_data, int $user_type, array $express_company, array $api_delivery_data)
    {
        $order_goods_id = OrderGoods::query()->where('order_id', $order['id'])->pluck('id')->toArray();
        $delivery_data = [
            'order_id' => $order['id'],
            'order_goods_id' => json_encode($order_goods_id),
            'company_code' => $express_company['code'],
            'company_name' => $express_company['title'],
            'code' => $api_delivery_data['code'],
            'note' => ''
        ];
        $order_log = [
            'order_id' => $order['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => OrderLog::ACTION_SEND,
            'note' => ''
        ];
        $template = [
            'order_id' => $order['id'],
            'seller_id' => $order['seller_id'],
            'url' => $api_delivery_data['template_url'],
            'content' => $api_delivery_data['template']
        ];
        try {
            DB::transaction(function () use ($order, $order_log, $delivery_data, $template) {
                //修改商品发货状态
                OrderGoods::query()->where('order_id', $order['id'])->update(['delivery' => OrderGoods::DELIVERY_ON]);
                Order::query()->where('id', $order['id'])->update(['status' => Order::STATUS_SHIPMENT, 'send_at' => get_date()]);
                $delivery_res = OrderDelivery::query()->create($delivery_data);//添加发货信息
                $delivery_id = $delivery_res->id;
                OrderLog::query()->create($order_log);//添加订单日志
                $template['order_delivery_id'] = $delivery_id;
                OrderDeliveryTemplate::query()->create($template);
            });
            //微信类的需要发货后推送
            if ($order['payment_id'] == Payment::PAYMENT_WECHAT) {
                $miniprogram = new MiniProgram();
                $miniprogram->uploadShippingInfo($order, $express_company, $api_delivery_data['code']);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 修改验证部分发货的订单状态
     * @param int $order_id
     * @return array|Application|Translator|string|true|null
     */
    public static function updatePartShipment(int $order_id)
    {
        //验证部分发货的是否全部发货
        $order = Order::where('id', $order_id)->first();
        if (!$order || $order['status'] != Order::STATUS_PART_SHIPMENT) {
            return __('api.order_status_error');
        }
        $res = OrderGoods::checkOrderDelivery($order_id);
        if ($res) {
            Order::where('id', $order_id)->update(['status' => Order::STATUS_SHIPMENT]);
        }
        return $res;
    }
}
