<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:47 PM
 */

namespace App\Models\Member;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Redis;

/**
 * 收藏
 */
class Favorite extends BaseModel
{
    protected $table = 'favorite';
    protected $guarded = ['id'];

    //类型
    const TYPE_GOODS = 1;
    const TYPE_SELLER = 2;
    const TYPE_ARTICLE = 3;
    const TYPE_DESC = [
        self::TYPE_GOODS => '商品',
        self::TYPE_SELLER => '商家',
        self::TYPE_ARTICLE => '文章'
    ];

    /**
     * redis key
     * @param int $live_id
     * @return string
     */
    public static function redisKey(int $type, $m_id)
    {
        return 'favorite:' . $type . ':' . ($m_id % 1000);
    }

    /**
     * 获取收藏状态
     * @param int $live_id
     * @param int $m_id
     * @param int $goods_id
     * @return mixed
     */
    public static function getFavorite(int $m_id, int $type, int $object_id)
    {
        $redis_key = self::redisKey($type, $m_id);
        return Redis::sismember($redis_key, $m_id . '_' . $object_id);
    }

    /**
     * 设置收藏状态
     * @param int $live_id
     * @param int $m_id
     * @param int $goods_id
     * @return bool
     */
    public static function setFavorite(int $m_id, int $type, int $object_id)
    {
        $redis_key = self::redisKey($type, $m_id);
        Redis::sadd($redis_key, $m_id . '_' . $object_id);
        return true;
    }

    /**
     * 删除收藏状态
     * @param int $live_id
     * @param int $m_id
     * @param int $goods_id
     * @return bool
     */
    public static function delFavorite(int $m_id, int $type, int $object_id)
    {
        $redis_key = self::redisKey($type, $m_id);
        Redis::srem($redis_key, $m_id . '_' . $object_id);
        return true;
    }
}
