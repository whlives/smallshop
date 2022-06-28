<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/20
 * Time: 21:14 PM
 */

namespace App\Services;

use App\Libs\Delivery;
use App\Models\Financial\TradeRefund;
use App\Models\Order\Order;
use App\Models\Order\OrderGoods;
use App\Models\Order\Refund;
use App\Models\Order\RefundDelivery;
use App\Models\Order\RefundImage;
use App\Models\Order\RefundLog;
use Illuminate\Support\Facades\DB;

class RefundService
{
    /**
     * 生成售后单号
     * @return string
     */
    public static function getRefundNo(): string
    {
        return date('ymdHis', time()) . rand(100000, 999999);
    }

    /**
     * 售后是否可以取消(用户)
     * @param array $refund
     * @return bool
     */
    public static function isCancel(array $refund)
    {
        //待审核、审核拒绝、待退货、商家拒绝售后都可以取消
        if (isset($refund['status']) && in_array($refund['status'], [Refund::STATUS_WAIT_APPROVE, Refund::STATUS_REFUSED_APPROVE, Refund::STATUS_WAIT_DELIVERY, Refund::STATUS_REFUSED_RECEIVED])) {
            return true;
        }
        return false;
    }

    /**
     * 售后是否可以修改(用户)
     * @param array $refund
     * @return bool
     */
    public static function isUpdate(array $refund)
    {
        if (isset($refund['status']) && in_array($refund['status'], [Refund::STATUS_WAIT_APPROVE, Refund::STATUS_REFUSED_APPROVE, Refund::STATUS_REFUSED_RECEIVED])) {
            return true;
        }
        return false;
    }

    /**
     * 用户是否可以删除售后(用户)
     * @param array $refund
     * @return bool
     */
    public static function isUserDelete(array $refund)
    {
        if (isset($refund['status']) && in_array($refund['status'], [Refund::STATUS_DONE, Refund::STATUS_CUSTOMER_CANCEL])) {
            return true;
        }
        return false;
    }

    /**
     * 售后是否可以退回发货(用户)
     * @param array $refund
     * @return bool
     */
    public static function isDelivery(array $refund)
    {
        if (isset($refund['status']) && ($refund['status'] == Refund::STATUS_WAIT_DELIVERY)) {
            return true;
        }
        return false;
    }

    /**
     * 售后是否可以确认收货(用户)
     * @param array $refund
     * @return bool
     */
    public static function isConfirm(array $refund)
    {
        if (isset($refund['status']) && ($refund['status'] == Refund::STATUS_WAIT_CONFIRM_DELIVERY)) {
            return true;
        }
        return false;
    }

    /**
     * 售后是否可以审核拒绝(管理员)
     * @param array $refund
     * @return bool
     */
    public static function isRefused(array $refund)
    {
        if (isset($refund['status']) && ($refund['status'] == Refund::STATUS_WAIT_APPROVE)) {
            return true;
        }
        return false;
    }

    /**
     * 售后是否可以审核(管理员)
     * @param array $refund
     * @return bool
     */
    public static function isAudit(array $refund)
    {
        if (isset($refund['status']) && ($refund['status'] == Refund::STATUS_WAIT_APPROVE)) {
            return true;
        }
        return false;
    }


    /**
     * 售后是否可以确认收货(管理员)
     * @param array $refund
     * @return bool
     */
    public static function isConfirmGoods(array $refund)
    {
        if (isset($refund['status']) && ($refund['status'] == Refund::STATUS_RECEIVED)) {
            return true;
        }
        return false;
    }

    /**
     * 售后是否可以拒绝收货(管理员)
     * @param array $refund
     * @return bool
     */
    public static function isRefusedGoods(array $refund)
    {
        if (isset($refund['status']) && ($refund['status'] == Refund::STATUS_RECEIVED)) {
            return true;
        }
        return false;
    }

    /**
     * 售后是否可以发货(管理员)
     * @param array $refund
     * @return bool
     */
    public static function isSend(array $refund)
    {
        if (isset($refund['status']) && ($refund['status'] == Refund::STATUS_WAIT_SELLER_DELIVERY)) {
            return true;
        }
        return false;
    }

    /**
     * 售后是否可以打款(管理员)
     * @param array $refund
     * @return bool
     */
    public static function isPay(array $refund)
    {
        if (isset($refund['status']) && ($refund['status'] == Refund::STATUS_WAIT_PAY)) {
            return true;
        }
        return false;
    }

    /**
     * 验证售后信息
     * @param int $order_goods_id
     * @param int $m_id
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public static function checkRefund(int $order_goods_id, int $m_id)
    {
        //获取订单商品信息
        $order_goods = OrderGoods::where(['id' => $order_goods_id, 'm_id' => $m_id])->first();
        if (!$order_goods) {
            api_error(__('api.order_goods_error'));
        }
        //获取订单信息
        $order = Order::where('id', $order_goods['order_id'])->first();
        //只有订单是已付款、待收货、已经确认和已经完成的并且订单商品是没有售后或者售后关闭的才可以申请
        if (!in_array($order['status'], [Order::STATUS_PAID, Order::STATUS_SHIPMENT, Order::STATUS_PART_SHIPMENT, Order::STATUS_DONE, Order::STATUS_COMPLETE])) {
            api_error(__('api.refund_time_out'));
        }
        //查询是否已经申请
        $refund = Refund::where('order_goods_id', $order_goods_id)->first();
        //已经售后完成
        if (isset($refund['status']) && $refund['status'] == Refund::STATUS_DONE) {
            api_error(__('api.refund_complete'));
        }
        //在等待寄回商品、寄回商品、待退款的时候不允许修改
        if (isset($refund['status']) && in_array($refund['status'], [Refund::STATUS_WAIT_DELIVERY, Refund::STATUS_RECEIVED, Refund::STATUS_WAIT_PAY])) {
            api_error(__('api.refund_wait_audit'));
        }
        $all_refund = 0;//是否全部退款，如果是最后一个需要加上运费
        //查询订单下没有售后的商品，如果是最后一个就是全部退款
        $wiat_refund_id = OrderGoods::where('order_id', $order['id'])->whereIn('refund', [OrderGoods::REFUND_NO, OrderGoods::REFUND_CLOSE])->pluck('id')->toArray();
        if (count($wiat_refund_id) == 1 && $wiat_refund_id[0] == $order_goods_id) {
            $all_refund = 1;
        }
        //获取最大退款价格
        $delivery_price = 0;
        if ($all_refund == 1) {
            $delivery_price = $order['delivery_price_real'];
        }
        $max_amount = $order_goods['sell_price'] * $order_goods['buy_qty'] - $order_goods['promotion_price'] + $delivery_price;//最大退款需要计算运费
        if ($refund) {
            $refund = $refund->toArray();
            //修改时候直接读取原来的
            $max_amount = $refund['max_amount'];
            $delivery_price = $refund['delivery_price'];
        } else {
            $refund = [];
        }
        return [$order_goods, $order, $refund, $max_amount, $delivery_price];
    }

    /**
     * 提交售后信息
     * @param array $refund
     * @param array $apply_data
     * @param array $refund_log
     * @param array $image
     * @return bool
     */
    public static function putRefund(array $refund, array $apply_data, array $refund_log, array $image)
    {
        try {
            DB::transaction(function () use ($refund, $apply_data, $refund_log, $image) {
                if ($refund) {
                    Refund::where('id', $refund['id'])->update($apply_data);
                    $id = $refund['id'];
                } else {
                    $result = Refund::create($apply_data);
                    $id = $result->id;
                }
                //修改订单商品售后状态
                OrderGoods::where('id', $apply_data['order_goods_id'])->update(['refund' => OrderGoods::REFUND_APPLY]);
                //日志信息
                $refund_log['refund_id'] = $id;
                $log_res = RefundLog::create($refund_log);
                $log_id = $log_res->id;
                //日志图片
                $image_data = [];
                foreach ($image as $value) {
                    $image_data[] = [
                        'log_id' => $log_id,
                        'image' => $value
                    ];
                }
                if ($image_data) RefundImage::insert($image_data);
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 售后用户发货
     * @param array $refund
     * @param array $member_data
     * @param int $user_type
     * @param string|null $note
     * @param array $param
     * @return bool
     * @throws \App\Exceptions\ApiError
     */
    public static function delivery(array $refund, array $member_data, int $user_type, string|null $note, array $param)
    {
        if (!self::isDelivery($refund)) {
            api_error(__('api.refund_status_error'));
        }
        $express_company = $param['express_company'];
        $log_note = [
            [
                'title' => '物流公司',
                'info' => $express_company['title']
            ],
            [
                'title' => '物流单号',
                'info' => $param['code']
            ]
        ];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        //售后日志信息
        $refund_log = [
            'refund_id' => $refund['id'],
            'user_type' => $user_type,
            'user_id' => $member_data['id'],
            'username' => $member_data['username'],
            'action' => RefundLog::ACTION_MEMBER_SEND,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : '',
        ];
        $delivery_data = [
            'refund_id' => $refund['id'],
            'type' => RefundDelivery::TYPE_MEMBER,
            'company_code' => $express_company['code'],
            'company_name' => $express_company['title'],
            'code' => $param['code']
        ];
        try {
            DB::transaction(function () use ($refund, $delivery_data, $refund_log) {
                RefundDelivery::create($delivery_data);
                RefundLog::create($refund_log);
                Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_RECEIVED]);
            });
            //订阅物流消息
            $delivery = new Delivery();
            $delivery->subscribe($express_company['code'], $param['code']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 取消售后
     * @param array $refund
     * @param array $member_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public static function cancel(array $refund, array $member_data, int $user_type, string|null $note = '')
    {
        if (!self::isCancel($refund)) {
            return __('api.refund_status_error');
        }
        $log_note = [];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        //售后日志信息
        $refund_log = [
            'refund_id' => $refund['id'],
            'user_type' => $user_type,
            'user_id' => $member_data['id'],
            'username' => $member_data['username'],
            'action' => RefundLog::ACTION_CANCEL,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : '',
        ];
        try {
            DB::transaction(function () use ($refund, $refund_log) {
                RefundLog::create($refund_log);
                //修改订单商品售后状态
                OrderGoods::where('id', $refund['order_goods_id'])->update(['refund' => OrderGoods::REFUND_CLOSE]);
                Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_CUSTOMER_CANCEL, 'done_at' => get_date()]);
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 用户确认收货
     * @param array $refund
     * @param array $member_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public static function confirm(array $refund, array $member_data, int $user_type, string|null $note = '')
    {
        if (!self::isConfirm($refund)) {
            return __('api.refund_status_error');
        }
        $log_note = [
            [
                'title' => '确认收货',
                'info' => '用户确认收货'
            ]
        ];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        //售后日志信息
        $refund_log = [
            'refund_id' => $refund['id'],
            'user_type' => $user_type,
            'user_id' => $member_data['id'],
            'username' => $member_data['username'],
            'action' => RefundLog::ACTION_COMPLETE,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : '',
        ];
        try {
            DB::transaction(function () use ($refund, $refund_log) {
                RefundLog::create($refund_log);
                //修改订单商品售后状态
                OrderGoods::where('id', $refund['order_goods_id'])->update(['refund' => OrderGoods::REFUND_DONE]);
                Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_DONE, 'done_at' => get_date()]);
                //判断订单下的商品是否全部退款,全部退款修改订单状态
                $refund_order_count = OrderGoods::where([['order_id', $refund['order_id']], ['refund', '!=', OrderGoods::REFUND_DONE]])->count();
                if ($refund_order_count == 0) {
                    Order::where('id', $refund['order_id'])->update(['status' => Order::STATUS_REFUND_COMPLETE, 'done_at' => get_date()]);
                }
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 售后操作按钮
     * @param array $refund 售后单信息
     * @return int[]
     */
    public static function refundButton(array $refund)
    {
        $button = [
            'delete' => 0,//删除订单
            'cancel' => 0,//取消订单
            'update' => 0,//修改
            'delivery' => 0,//物流
            'confirm' => 0,//确认售后
        ];
        if (self::isUserDelete($refund)) {
            $button['delete'] = 1;
        }
        if (self::isCancel($refund)) {
            $button['cancel'] = 1;
        }
        if (self::isUpdate($refund)) {
            $button['update'] = 1;
        }
        if (self::isDelivery($refund)) {
            $button['delivery'] = 1;
        }
        if (self::isConfirm($refund)) {
            $button['confirm'] = 1;
        }
        return $button;
    }

    /**
     * 售后同意状态到待打款
     * @param array $refund
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @return bool
     */
    public static function sellerWaitPay(array $refund, array $user_data, int $user_type, string|null $note = '')
    {
        if (($refund['status'] == Refund::STATUS_WAIT_APPROVE && $refund['refund_type'] == Refund::REFUND_TYPE_MONEY) || ($refund['status'] == Refund::STATUS_RECEIVED && $refund['refund_type'] == Refund::REFUND_TYPE_GOODS)) {
            $log_note = [];
            if ($note) {
                $log_note[] = ['title' => '备注', 'info' => $note];
            }
            $refund_log = [
                'refund_id' => $refund['id'],
                'user_type' => $user_type,
                'user_id' => $user_data['id'],
                'username' => $user_data['username'],
                'action' => RefundLog::ACTION_AGREE,
                'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : ''
            ];
            try {
                DB::transaction(function () use ($refund, $refund_log) {
                    //修改订单商品售后状态
                    OrderGoods::where('id', $refund['order_goods_id'])->update(['refund' => OrderGoods::REFUND_ONGOING]);
                    RefundLog::create($refund_log);
                    Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_WAIT_PAY, 'approve_at' => get_date()]);
                });
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            return __('api.refund_status_error');
        }
    }

    /**
     * 商家同意退回货物
     * @param array $refund
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @param array $address
     * @return bool
     */
    public static function sellerAgreeDelivery(array $refund, array $user_data, int $user_type, string|null $note = '', array $address)
    {
        if (!self::isAudit($refund)) {
            api_error(__('api.refund_status_error'));
        }
        $log_note[] = ['title' => '收货人', 'info' => $address['full_name']];
        $log_note[] = ['title' => '电话', 'info' => $address['tel']];
        $log_note[] = ['title' => '地址', 'info' => $address['prov_name'] . $address['city_name'] . $address['area_name'] . $address['address']];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        $refund_log = [
            'refund_id' => $refund['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => RefundLog::ACTION_AGREE,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : ''
        ];
        try {
            DB::transaction(function () use ($refund, $refund_log) {
                OrderGoods::where('id', $refund['order_goods_id'])->update(['refund' => OrderGoods::REFUND_ONGOING]);
                RefundLog::create($refund_log);
                Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_WAIT_DELIVERY, 'approve_at' => get_date()]);
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 审核拒绝
     * @param array $refund
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @return bool
     */
    public static function sellerRefused(array $refund, array $user_data, int $user_type, string|null $note = '')
    {
        if (!self::isRefused($refund)) {
            return __('api.refund_status_error');
        }
        $log_note = [];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        $refund_log = [
            'refund_id' => $refund['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => RefundLog::ACTION_REFUSED,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : ''
        ];
        try {
            DB::transaction(function () use ($refund, $refund_log) {
                RefundLog::create($refund_log);
                Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_REFUSED_APPROVE, 'refused_at' => get_date()]);
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 商家同意收货
     * @param array $refund
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public static function sellerConfirmGoods(array $refund, array $user_data, int $user_type, string|null $note = '')
    {
        if (!self::isConfirmGoods($refund)) {
            return __('api.refund_status_error');
        }
        $status = '';
        if ($refund['refund_type'] == Refund::REFUND_TYPE_GOODS) {
            $status = Refund::STATUS_WAIT_PAY;
        } elseif ($refund['refund_type'] == Refund::REFUND_TYPE_REPLACE) {
            $status = Refund::STATUS_WAIT_SELLER_DELIVERY;
        }
        $log_note = [];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        $refund_log = [
            'refund_id' => $refund['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => RefundLog::ACTION_AGREE,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : ''
        ];
        try {
            DB::transaction(function () use ($refund, $refund_log, $status) {
                RefundLog::create($refund_log);
                Refund::where('id', $refund['id'])->update(['status' => $status]);
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 商家拒绝收货
     * @param array $refund
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public static function sellerRefusedGoods(array $refund, array $user_data, int $user_type, string|null $note = '')
    {
        if (!self::isRefusedGoods($refund)) {
            return __('api.refund_status_error');
        }
        $log_note = [];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        $refund_log = [
            'refund_id' => $refund['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => RefundLog::ACTION_REFUSED,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : ''
        ];
        try {
            DB::transaction(function () use ($refund, $refund_log) {
                RefundLog::create($refund_log);
                Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_REFUSED_RECEIVED, 'refused_at' => get_date()]);
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 商家发货
     * @param array $refund
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @param array $param
     * @return bool
     * @throws \App\Exceptions\ApiError
     */
    public static function sellerSend(array $refund, array $user_data, int $user_type, string|null $note, array $param)
    {
        if (!self::isSend($refund)) {
            api_error(__('api.refund_status_error'));
        }
        $express_company = $param['express_company'];
        $log_note = [
            [
                'title' => '物流公司',
                'info' => $express_company['title']
            ],
            [
                'title' => '物流单号',
                'info' => $param['code']
            ]
        ];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        //售后日志信息
        $refund_log = [
            'refund_id' => $refund['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => RefundLog::ACTION_SELEER_SEND,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : '',
        ];
        $delivery_data = [
            'refund_id' => $refund['id'],
            'type' => RefundDelivery::TYPE_SELLER,
            'company_code' => $express_company['code'],
            'company_name' => $express_company['title'],
            'code' => $param['code']
        ];
        try {
            DB::transaction(function () use ($refund, $delivery_data, $refund_log) {
                RefundDelivery::create($delivery_data);
                RefundLog::create($refund_log);
                Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_WAIT_CONFIRM_DELIVERY]);
            });
            //订阅物流消息
            $delivery = new Delivery();
            $delivery->subscribe($express_company['code'], $param['code']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * 确认打款
     * @param array $refund
     * @param array $user_data
     * @param int $user_type
     * @param string|null $note
     * @param bool $original_road 是否原路退回
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public static function sellerPay(array $refund, array $user_data, int $user_type, string|null $note, bool $original_road = true)
    {
        if (!self::isPay($refund)) {
            return __('api.refund_status_error');
        }
        $log_note = [
            ['title' => '确认退款', 'info' => $original_road ? '原路退回' : '线下打款']
        ];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        $refund_log = [
            'refund_id' => $refund['id'],
            'user_type' => $user_type,
            'user_id' => $user_data['id'],
            'username' => $user_data['username'],
            'action' => RefundLog::ACTION_COMPLETE,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : ''
        ];
        $order = Order::find($refund['order_id']);
        try {
            DB::transaction(function () use ($refund, $refund_log) {
                RefundLog::create($refund_log);
                Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_DONE, 'done_at' => get_date()]);
            });
            $res = true;
        } catch (\Exception $e) {
            $res = false;
        }
        //是否需要原路退回资金
        if ($res && $original_road) {
            $refund_res = TradeService::tradeRefund($order['trade_id'], $refund['refund_no'], $refund['amount'], TradeRefund::TYPE_REFUND, '售后退款');
            if ($refund_res !== true) {
                //这里如果退款失败需要回滚数据
                $refund_log['action'] = RefundLog::ACTION_CANCEL;
                $refund_log['note'] = json_encode(['title' => '退款失败', 'info' => $refund_res], JSON_UNESCAPED_UNICODE);
                try {
                    DB::transaction(function () use ($refund, $refund_log) {
                        RefundLog::create($refund_log);
                        Refund::where('id', $refund['id'])->update(['status' => Refund::STATUS_WAIT_PAY, 'done_at' => null]);
                    });
                } catch (\Exception $e) {
                }
                return __($refund_res);
            }
        }
        //判断订单下的商品是否全部退款,全部退款修改订单状态
        try {
            DB::transaction(function () use ($refund, $order) {
                OrderGoods::where('id', $refund['order_goods_id'])->update(['refund' => OrderGoods::REFUND_DONE]);
                $refund_order_count = OrderGoods::where([['order_id', $order['id']], ['refund', '!=', OrderGoods::REFUND_DONE]])->count();
                if ($refund_order_count == 0) {
                    Order::where('id', $order['id'])->update(['status' => Order::STATUS_REFUND_COMPLETE, 'done_at' => get_date()]);
                }
            });
        } catch (\Exception $e) {
        }
        return $res;
    }


}