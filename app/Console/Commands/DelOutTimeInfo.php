<?php

namespace App\Console\Commands;

use App\Models\Member\MemberLoginLog;
use App\Models\Order\DeliveryTraces;
use App\Models\Order\OrderDeliveryTemplate;
use App\Services\LogService;
use Illuminate\Console\Command;

class DelOutTimeInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:del_out_time_info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '删除超时的信息';

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
        LogService::putLog('crontab', '删除30天以上的物流信息');
        //删除30天以上的物流信息
        $traces_date_time = get_date(time() - 30 * 24 * 3600);
        DeliveryTraces::query()->where([['created_at', '<', $traces_date_time]])->delete();

        //删除30天以上的快递模板信息
        $tmp_date_time = get_date(time() - 30 * 24 * 3600);
        OrderDeliveryTemplate::query()->where([['created_at', '<', $tmp_date_time]])->delete();

        //删除60天以上的登录记录
        $member_login_log_date_time = get_date(time() - 60 * 24 * 3600);
        MemberLoginLog::query()->where([['created_at', '<', $member_login_log_date_time]])->delete();
    }
}
