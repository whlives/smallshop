<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/3
 * Time: 4:45 PM
 */

namespace App\Models\Market;

use App\Models\BaseModel;
use App\Models\Goods\GoodsSku;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * 秒杀
 */
class PromoSeckill extends BaseModel
{
    use SoftDeletes;

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    protected $table = 'promo_seckill';
    protected $guarded = ['id'];

    protected $dates = ['deleted_at'];

    /**
     * 验证秒杀信息
     * @param int $goods_id
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function checkSeckill(int $goods_id)
    {
        $cache_key = 'seckill:' . $goods_id;
        $seckill = Cache::get($cache_key);
        if (!$seckill) {
            $seckill = self::select('start_at', 'end_at', 'end_at', 'status')->where('goods_id', $goods_id)->first();
            if ($seckill) $seckill = $seckill->toArray();
            Cache::put($cache_key, $seckill, get_custom_config('cache_time'));
        }
        if (!$seckill) {
            api_error(__('api.seckill_error'));
        } elseif ($seckill['start_at'] > get_date()) {
            api_error(__('api.seckill_not_start'));
        } elseif ($seckill['end_at'] < get_date()) {
            api_error(__('api.seckill_is_end'));
        } elseif ($seckill['status'] != self::STATUS_ON) {
            api_error(__('api.seckill_status_error'));
        }
        return $seckill;
    }
}