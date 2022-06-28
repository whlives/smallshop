<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 2:14 PM
 */

namespace App\Models;

use Illuminate\Support\Facades\Cache;

/**
 * 地区
 */
class Areas extends BaseModel
{
    protected $table = 'areas';
    protected $guarded = ['id'];

    /**
     * 根据id获取名称
     * @param int|array $ids
     * @return mixed|string
     */
    public static function getAreaName(int|array $ids = 0)
    {
        $new_ids = $ids;
        if (!is_array($ids)) $new_ids = [$ids];
        if ($new_ids) {
            $area = self::whereIn('id', $new_ids)->pluck('name', 'id');
            if (!is_array($ids)) return $area[$ids] ?? '';
            return $area->toArray();
        }
        return '';
    }

    /**
     * 根据名称和上级id获取id
     * @param string $name
     * @param int $parent_id
     * @return int
     */
    public static function getAreaId(string $name, int $parent_id = 0): int
    {
        $id = 0;
        if ($name) {
            $area = self::where([['name', $name], ['parent_id', $parent_id]])->first();
            if ($area) $id = $area['id'];
        }
        return $id;
    }

    /**
     * 根据parent_id获取下级
     * @param int $parent_id 上级id
     * @return array
     */
    public static function getArea(int $parent_id = 0): array
    {
        return Cache::remember('area_select_' . $parent_id, get_custom_config('cache_time'), function () use ($parent_id) {
            $area = [];
            $area_res = self::where('parent_id', $parent_id)->select('id', 'name')->get();
            if (!$area_res->isEmpty()) {
                $area = $area_res->toArray();
            }
            return $area;
        });
    }
}