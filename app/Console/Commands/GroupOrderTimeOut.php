<?php

namespace App\Console\Commands;

use App\Models\Market\PromoGroup;
use App\Models\Market\PromoGroupOrder;
use App\Models\Order\Order;
use App\Models\Order\OrderLog;
use App\Services\LogService;
use App\Services\OrderService;
use Illuminate\Console\Command;

class GroupOrderTimeOut extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:group_order_time_out';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '到期的拼团订单关闭';

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
        LogService::putLog('crontab', '到期的拼团订单关闭');
        $user_data = [
            'id' => 0,
            'username' => 'system'
        ];
        $where = [
            ['end_at', '<', get_date()]
        ];
        $page = 1;
        $limit = 10;
        while (true) {
            $offset = ($page - 1) * $limit;
            $res_list = PromoGroupOrder::select('id', 'order_id')
                ->where($where)
                ->whereIn('status', [PromoGroupOrder::STATUS_WAIT_PAY, PromoGroupOrder::STATUS_WAIT_SUCCESS])
                ->offset($offset)
                ->limit($limit)
                ->orderBy('id', 'asc')
                ->get();
            if ($res_list->isEmpty()) {
                break;
            } else {
                $page++;
                $res_list = $res_list->toArray();
                $ids = array_column($res_list, 'id');
                $order_ids = array_column($res_list, 'order_id');
                $res_order = Order::select('id', 'trade_id', 'order_no', 'subtotal', 'promo_type', 'status', 'coupons_id')->whereIn('id', $order_ids)->get();
                if ($res_order->isEmpty()) {
                    continue;
                }
                $res_order = array_column($res_order->toArray(), null, 'id');
                $res = PromoGroupOrder::timeOut($ids);
                if ($res) {
                    foreach ($res_list as $val) {
                        OrderService::groupOrderCancel($res_order[$val['order_id']], $user_data, OrderLog::USER_TYPE_SYSTEM, '拼团失败取消订单');
                    }
                }
                sleep(1);
            }
        }
    }
}
