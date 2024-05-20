<?php

namespace App\Console\Commands;

use App\Models\Order\Refund;
use App\Models\Order\RefundLog;
use App\Services\LogService;
use App\Services\RefundService;
use Illuminate\Console\Command;

class RefundConfirm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:refund_confirm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '换货的发货后用户一直没有确认的';

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
     * @return false|void
     */
    public function handle()
    {
        $custom_config = get_custom_config_all();
        //高访问的时候关闭
        $high_qps = $custom_config['high_qps'];
        if ($high_qps) return false;
        $refund_confirm_time = $custom_config['refund_confirm_time'];
        if (!$refund_confirm_time) return false;
        LogService::putLog('crontab', '用户换货超时未确认的售后');
        $user_data = [
            'id' => 0,
            'username' => 'system'
        ];
        $where = [
            ['status', Refund::STATUS_WAIT_CONFIRM_DELIVERY],
            ['delivery_at', '<', get_date(time() - $refund_confirm_time)]
        ];
        $page = 1;
        $limit = 10;
        while (true) {
            $offset = ($page - 1) * $limit;
            $res_list = Refund::query()->select('id', 'order_goods_id', 'status')
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
                    RefundService::confirm($value, $user_data, RefundLog::USER_TYPE_SYSTEM, '用户换货超时未确认自动确认');
                }
                sleep(1);
            }
        }
    }
}
