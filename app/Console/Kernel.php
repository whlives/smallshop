<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        Commands\AdminLoginStatus::class,
        Commands\OrderCancel::class,
        Commands\OrderConfirm::class,
        Commands\OrderComplete::class,
        Commands\OrderComment::class,
        Commands\RefundCancel::class,
        Commands\DelOutTimeInfo::class,

    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('command:admin_login_status')->everyThirtyMinutes();//检测过期的登录状态
        $schedule->command('command:order_cancel')->everyMinute();//取消订单
        $schedule->command('command:order_confirm')->everyTenMinutes();//确认订单收货
        $schedule->command('command:order_complete')->everyTenMinutes();//订单交易完成
        $schedule->command('command:order_comment')->everyTenMinutes();//订单自动评价
        $schedule->command('command:refund_cancel')->everyTenMinutes();//售后订单超时取消
        $schedule->command('command:del_out_time_info')->dailyAt('4:00');//删除指定信息
        $schedule->command('command:group_order_time_out')->everyTenMinutes();//拼团订单超时取消
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
