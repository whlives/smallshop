<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Order\OrderGoods;
use App\Services\LogService;
use App\Services\OrderService;
use Illuminate\Console\Command;

class OrderComment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:order_comment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '订单自动评价';

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
        LogService::putLog('crontab', '订单自动评价');
        $page = 1;
        $pagesize = 10;
        while (true) {
            $offset = ($page - 1) * $pagesize;
            $res_list = Order::select('id', 'm_id', 'status', 'comment_at')
                ->whereNull('comment_at')
                ->wherein('status', [Order::STATUS_DONE, Order::STATUS_COMPLETE])
                ->offset($offset)
                ->limit($pagesize)
                ->orderBy('id', 'asc')->get();
            if ($res_list->isEmpty()) {
                break;
            } else {
                $page++;
                foreach ($res_list->toArray() as $value) {
                    $order_goods = OrderGoods::getGoodsForOrderId([$value['id']]);
                    $comment = [];
                    foreach ($order_goods as $val) {
                        $_item = [
                            'm_id' => $value['m_id'],
                            'goods_id' => $val['goods_id'],
                            'sku_id' => $val['sku_id'],
                            'spec_value' => $val['spec_value'],
                            'level' => 5,
                            'content' => '好评',
                            'image' => [],
                            'video' => []
                        ];
                        $comment[] = $_item;
                    }
                    OrderService::commentPut($value, $comment);
                }
                sleep(1);
            }
        }
    }
}
