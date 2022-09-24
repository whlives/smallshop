<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Order\OrderLog;
use App\Services\LogService;
use App\Services\OrderService;
use Illuminate\Console\Command;

class OrderConfirm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:order_confirm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '确认收货超时的订单自动确认';

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
        //高访问的时候关闭
        $high_qps = get_custom_config('high_qps');
        if ($high_qps) return false;
        $order_confirm_time = get_custom_config('order_confirm_time');
        if (!$order_confirm_time) return false;
        LogService::putLog('crontab', '确认收货超时的订单自动确认');
        $user_data = [
            'id' => 0,
            'username' => 'system'
        ];
        $where = [
            ['status', Order::STATUS_SHIPMENT],
            ['send_at', '<', get_date(time() - $order_confirm_time)]
        ];
        $page = 1;
        $limit = 10;
        while (true) {
            $offset = ($page - 1) * $limit;
            $res_list = Order::select('id', 'status')
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
                    OrderService::confirm($value, $user_data, OrderLog::USER_TYPE_SYSTEM, '超时自动确认');
                }
                sleep(1);
            }
        }
    }
}
