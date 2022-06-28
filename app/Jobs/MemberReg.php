<?php

namespace App\Jobs;

use App\Models\Market\Promotion;
use App\Models\Member\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 用户注册后续处理
 */
class MemberReg implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $username;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $username)
    {
        $this->username = $username;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $member_data = Member::where('username', $this->username)->first();
        if (!$member_data) return false;
        $member_data = $member_data->toArray();
        Promotion::reg($member_data);//注册活动
        //其他活动在这里都可以添加
    }


}
