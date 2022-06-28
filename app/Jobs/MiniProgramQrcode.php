<?php

namespace App\Jobs;

use App\Libs\Weixin\MiniProgram;
use App\Models\Goods\Goods;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 生成小程序码
 */
class MiniProgramQrcode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $type;
    public array $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $mini_program = new MiniProgram();
        $auto_create_mini_program_qrcode = get_custom_config('auto_create_mini_program_qrcode');
        if ($auto_create_mini_program_qrcode) {
            switch ($this->type) {
                case 'goods':
                    if (isset($this->data['id']) && $this->data['id']) {
                        Goods::createQrcode($this->data['id']);
                    }
                    break;
                default:
                    return false;
            }
        }
    }
}
