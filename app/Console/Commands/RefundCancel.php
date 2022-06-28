<?php

namespace App\Console\Commands;

use App\Models\Order\Refund;
use App\Models\Order\RefundLog;
use App\Services\LogService;
use App\Services\RefundService;
use Illuminate\Console\Command;

class RefundCancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:refund_cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '拒绝了用户没有修改的，同意退货了一直没有寄出的，商家拒绝收货的';

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
        $refund_cancel_time = get_custom_config('refund_cancel_time');
        if (!$refund_cancel_time) return false;
        LogService::putLog('crontab', '取消超时处理的售后');
        $user_data = [
            'id' => 0,
            'username' => 'system'
        ];
        $where = [
            ['updated_at', '<', get_date(time() - $refund_cancel_time)]
        ];
        $page = 1;
        $pagesize = 10;
        while (true) {
            $offset = ($page - 1) * $pagesize;
            $res_list = Refund::select('id', 'order_goods_id', 'status')
                ->where($where)
                ->whereIn('status', [Refund::STATUS_REFUSED_APPROVE, Refund::STATUS_WAIT_DELIVERY, Refund::STATUS_REFUSED_RECEIVED])
                ->offset($offset)
                ->limit($pagesize)
                ->orderBy('id', 'asc')
                ->get();
            if ($res_list->isEmpty()) {
                break;
            } else {
                $page++;
                foreach ($res_list->toArray() as $value) {
                    RefundService::cancel($value, $user_data, RefundLog::USER_TYPE_SYSTEM, '用户超时未处理自动取消');
                }
                sleep(1);
            }
        }
    }
}
