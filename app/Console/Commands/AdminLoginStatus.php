<?php

namespace App\Console\Commands;

use App\Models\Admin\AdminLoginLog;
use App\Services\LogService;
use App\Services\TokenService;
use Illuminate\Console\Command;

class AdminLoginStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:admin_login_status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检测过期的登录状态';

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
        LogService::putLog('crontab', '清除后台登录账号');
        $token_service = new TokenService();
        $where = [
            ['status', AdminLoginLog::STATUS_ON],
            ['created_at', '<', get_date(time() - (4 * 3600))]
        ];
        $page = 1;
        $limit = 10;
        while (true) {
            $offset = ($page - 1) * $limit;
            $res_list = AdminLoginLog::select('id', 'token')
                ->where($where)
                ->offset($offset)
                ->limit($limit)
                ->orderBy('id', 'asc')
                ->get();
            if ($res_list->isEmpty()) {
                break;
            } else {
                $page++;
                $ids = [];
                foreach ($res_list as $value) {
                    $token = $token_service->getToken($value['token']);
                    if (!$token) {
                        $ids[] = $value['id'];
                        $token_service->delToken($value);
                    }
                }
                if ($ids) AdminLoginLog::whereIn('id', $ids)->update(['status' => AdminLoginLog::STATUS_OFF]);
                sleep(1);
            }
        }
    }
}
