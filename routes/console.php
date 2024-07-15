<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cache:prune-stale-tags')->hourly()->onOneServer();//laravel系统级清理缓存;
Schedule::command('command:admin_login_status')->everyThirtyMinutes()->onOneServer();//检测过期的登录状态
Schedule::command('command:order_cancel')->everyMinute()->onOneServer();//取消订单
Schedule::command('command:order_confirm')->everyTenMinutes()->onOneServer();//确认订单收货
Schedule::command('command:order_complete')->everyTenMinutes()->onOneServer();//订单交易完成
Schedule::command('command:order_comment')->everyTenMinutes()->onOneServer();//订单自动评价
Schedule::command('command:refund_cancel')->everyTenMinutes()->onOneServer();//售后订单超时取消
Schedule::command('command:refund_confirm')->everyTenMinutes()->onOneServer();//售后订单超时取消
Schedule::command('command:del_out_time_info')->dailyAt('4:00')->onOneServer();//删除指定信息
Schedule::command('command:group_order_time_out')->everyTenMinutes()->onOneServer();//拼团订单超时取消
