<?php

namespace App\Jobs;

use App\Models\Goods\Goods;
use App\Models\Goods\GoodsCoupons;
use App\Models\Market\CouponsDetail;
use App\Models\Market\PromoGroup;
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
 * 订单下单后续处理
 */
class OrderSubmitAfter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $order_no;
    public array $promo_data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $order_no, array $promo_data)
    {
        $this->order_no = $order_no;
        $this->promo_data = $promo_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->order_no) return false;
        $res_list = Order::select('id', 'm_id', 'promo_type')->whereIn('order_no', $this->order_no)->get();
        foreach ($res_list->toArray() as $value) {
            if ($value['promo_type'] == Goods::PROMO_TYPE_GROUP) {
                //拼团的订单
                PromoGroupOrder::submit($value, $this->promo_data);
            }
        }
    }
}
