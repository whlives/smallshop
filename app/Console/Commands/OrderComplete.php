<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Order\OrderLog;
use App\Services\LogService;
use App\Services\OrderService;
use Illuminate\Console\Command;

class OrderComplete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:order_complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '完成订单';

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
        $custom_config = get_custom_config_all();
        //高访问的时候关闭
        $high_qps = $custom_config['high_qps'];
        if ($high_qps) return false;
        $order_complete_time = $custom_config['order_complete_time'];
        if (!$order_complete_time) return false;
        LogService::putLog('crontab', '完成订单');
        $user_data = [
            'id' => 0,
            'username' => 'system'
        ];
        $where = [
            ['is_settlement', Order::IS_SETTLEMENT_NO],
            ['done_at', '<', get_date(time() - $order_complete_time)]
        ];
        $page = 1;
        $limit = 10;
        while (true) {
            $offset = ($page - 1) * $limit;
            $res_list = Order::query()->select('id', 'seller_id', 'order_no', 'subtotal', 'status', 'level_one_m_id', 'level_two_m_id', 'is_settlement')
                ->where($where)
                ->whereIn('status', [Order::STATUS_DONE, Order::STATUS_REFUND_COMPLETE])
                ->offset($offset)
                ->limit($limit)
                ->orderBy('id', 'asc')
                ->get();
            if ($res_list->isEmpty()) {
                break;
            } else {
                $page++;
                OrderService::complete($res_list->toArray(), $user_data, OrderLog::USER_TYPE_SYSTEM);
                sleep(1);
            }
        }
    }
}
