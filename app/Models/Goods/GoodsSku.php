<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:33 PM
 */

namespace App\Models\Goods;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Cache;

/**
 * 商品sku
 */
class GoodsSku extends BaseModel
{

    //状态
    const STATUS_DEL = 99;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_DEL => '删除',
        self::STATUS_ON => '正常',
    ];

    protected $table = 'goods_sku';
    protected $guarded = ['id'];

    public $timestamps = false;

    /**
     * 获取商品sku缓存信息
     * @param int $id
     * @return mixed
     */
    public static function getGoodsSku(int $id)
    {
        $cache_key = 'goods_sku:' . $id;
        $goods_sku = Cache::get($cache_key);
        if (!$goods_sku) {
            $goods_sku = self::select('id', 'goods_id', 'stock', 'min_buy', 'max_buy')->find($id);
            if ($goods_sku) $goods_sku = $goods_sku->toArray();
            Cache::put($cache_key, $goods_sku, get_custom_config('cache_time'));
        }
        return $goods_sku;
    }

}
