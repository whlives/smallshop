<?php

namespace App\Jobs;

use App\Models\Market\Promotion;
use App\Models\Order\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 订单完成奖励
 */
class OrderReward implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $order_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $order_id)
    {
        $this->order_id = $order_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $order = Order::where(['id' => $this->order_id, 'status' => Order::STATUS_COMPLETE])->first();
        if (!$order) return false;
        Promotion::order($order->toArray());//订单奖励
    }

}
