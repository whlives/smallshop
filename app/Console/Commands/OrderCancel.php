<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Order\OrderLog;
use App\Services\LogService;
use App\Services\OrderService;
use Illuminate\Console\Command;

class OrderCancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:order_cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '取消超时未支付的订单';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $order_cancel_time = get_custom_config('order_cancel_time');
        if (!$order_cancel_time) return false;
        LogService::putLog('crontab', '取消超时未支付的订单');
        $user_data = [
            'id' => 0,
            'username' => 'system'
        ];
        $where = [
            ['status', Order::STATUS_WAIT_PAY],
            ['created_at', '<', get_date(time() - $order_cancel_time)]
        ];
        $page = 1;
        $limit = 10;
        while (true) {
            $offset = ($page - 1) * $limit;
            $res_list = Order::select('id', 'trade_id', 'order_no', 'subtotal', 'promo_type', 'status', 'coupons_id')
                ->where($where)
                ->offset($offset)
                ->limit($limit)
                ->orderBy('id', 'asc')
                ->get();
            if ($res_list->isEmpty()) {
                break;
            } else {
                $page++;
                foreach ($res_list->toArray() as $value) {
                    OrderService::cancel($value, $user_data, OrderLog::USER_TYPE_SYSTEM, '超时系统取消');
                }
                sleep(1);
            }
        }
    }
}
