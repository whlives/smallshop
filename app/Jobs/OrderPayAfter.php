<?php

namespace App\Jobs;

use App\Models\Goods\Goods;
use App\Models\Goods\GoodsCoupons;
use App\Models\Market\CouponsDetail;
use App\Models\Market\PromoGroupOrder;
use App\Models\Order\Order;
use App\Models\Order\OrderGoods;
use App\Models\Order\OrderLog;
use App\Models\System\ExpressCompany;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 订单支付后续处理
 */
class OrderPayAfter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $order_no;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $order_no)
    {
        $this->order_no = $order_no;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //这里开始处理支付后的订单状态，还有优惠券、电子券类的发货订单
        //查询订单类型
        $res_list = Order::select('id', 'm_id', 'goods_type', 'promo_type', 'seller_id', 'status')->where(['status' => Order::STATUS_PAID])->whereIn('order_no', $this->order_no)->get();
        if ($res_list->isEmpty()) {
            return false;
        }
        foreach ($res_list->toArray() as $order) {
            if ($order['promo_type'] == Goods::PROMO_TYPE_GROUP) {
                //处理拼团的订单
                PromoGroupOrder::pay($order['id']);
            }
            if ($order['goods_type'] == Goods::TYPE_COUPONS) {
                //处理优惠券
                self::sendCoupons($order);
            } elseif ($order['goods_type'] == Goods::TYPE_POINT) {
                //处理积分
            } elseif ($order['goods_type'] == Goods::TYPE_TICKET) {
                //处理电子券
            }
        }
    }

    /**
     * 发放优惠券
     * @param array $order
     * @return false|void
     */
    private function sendCoupons(array $order)
    {
        $user_data = [
            'id' => 0,
            'username' => 'system'
        ];
        //查询优惠券信息
        $goods_data = OrderGoods::select('goods_id', 'buy_qty')->where('order_id', $order['id'])->first();
        if (!$goods_data) {
            return false;
        }
        //查询优惠券
        $coupon_id = GoodsCoupons::where('goods_id', $goods_data['goods_id'])->value('coupons_id');
        if (!$coupon_id) {
            return false;
        }
        $order_goods_id = OrderGoods::where('order_id', $order['id'])->pluck('id')->toArray();
        $res = CouponsDetail::generate($coupon_id, $order['m_id'], $goods_data['buy_qty']);
        if ($res) {
            $param = [
                'order_goods_id' => $order_goods_id,
                'company_id' => ExpressCompany::NOT_DELIVERY,
                'code' => ''
            ];
            OrderService::delivery($order, $user_data, OrderLog::USER_TYPE_SYSTEM, '系统自动发货', $param);
        }
    }
}
