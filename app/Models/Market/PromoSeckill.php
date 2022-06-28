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

    const STOCK_REDIS_KEY = 'promo_seckill_stock:';

    protected $table = 'promo_seckill';
    protected $guarded = ['id'];

    protected $dates = ['deleted_at'];

    /**
     * 验证秒杀信息
     * @param array $goods 商品信息
     * @param bool $stock_decr 是否扣减库存
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function checkSeckill(array $goods, bool $stock_decr = false)
    {
        $seckill = self::where('goods_id', $goods['goods_id'])->first();
        if (!$seckill) {
            api_error(__('api.seckill_error'));
        } elseif ($seckill['start_at'] > get_date()) {
            api_error(__('api.seckill_not_start'));
        } elseif ($seckill['end_at'] < get_date()) {
            api_error(__('api.seckill_is_end'));
        } elseif ($seckill['status'] != self::STATUS_ON) {
            api_error(__('api.seckill_status_error'));
        }
        //在这里可以处理redis库存什么的
        $stock_redis_key = self::STOCK_REDIS_KEY . $goods['goods_id'];
        $stock = Redis::hget($stock_redis_key, $goods['sku_id']);
        if ($stock < $goods['buy_qty']) {
            api_error(__('api.goods_stock_no_enough'));//秒杀库存不足
        }
        //开始减去库存
        if ($stock_decr) {
            Redis::hincrby($stock_redis_key, $goods['sku_id'], -$goods['buy_qty']);
        }
        return $seckill->toArray();
    }

    /**
     * 还原秒杀库存
     * @param array $goods
     * @return void
     */
    public static function stockIncr(array $goods)
    {
        $stock_redis_key = self::STOCK_REDIS_KEY . $goods['goods_id'];
        Redis::hincrby($stock_redis_key, $goods['sku_id'], $goods['buy_qty']);
    }

    /**
     * 同步秒杀库存
     * @param int $goods_id
     * @param string $end_time
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function syncStock(int $goods_id, string $end_time)
    {
        $sku_data = GoodsSku::where(['goods_id' => $goods_id])->pluck('stock', 'id')->toArray();
        $save_data = $sku_data;
        $save_data['all'] = array_sum($sku_data);
        if ($save_data['all'] <= 0) {
            api_error(__('admin.seckill_goods_stock_error'));
        }
        $stock_redis_key = self::STOCK_REDIS_KEY . $goods_id;
        Redis::del($stock_redis_key);
        Redis::hmset($stock_redis_key, $save_data);
        Redis::expire($stock_redis_key, strtotime($end_time) - time());
    }

    /**
     * 获取秒杀redis库存
     * @param int $goods_id
     * @return array|false
     */
    public static function getStock(int $goods_id)
    {
        $stock_redis_key = self::STOCK_REDIS_KEY . $goods_id;
        $stock = Redis::hgetall($stock_redis_key);
        if (!$stock) return false;
        $remaining_stock = array_sum($stock) - $stock['all'];//剩余库存
        $all_stock = $stock['all'];//总库存
        $pct = format_price(1 - ($remaining_stock / $all_stock), 2, false) * 100;//剩余库存比例
        //$sale = $stock['all'] - $remaining_stock;//已经销售的存库
        return [$pct, $stock, $remaining_stock];//已经销售比例，库存信息，剩余库存
    }
}