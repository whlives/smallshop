<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 9:45 PM
 */

namespace App\Models\System;

use App\Models\BaseModel;

/**
 * 后台菜单
 */
class Menu extends BaseModel
{
    protected $table = 'menu';
    protected $guarded = ['id'];
    
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    const MAX_HIERARCHY = 2;//最大层级

    /**
     * 获取指定上级id下的所有菜单，按上下级排列(获取管理菜单)
     * @param int $parent_id 上级id
     * @return array
     */
    public static function getMenu(int $parent_id = 0): array
    {
        $where = [
            'parent_id' => $parent_id,
            'status' => self::STATUS_ON,
        ];
        $result = self::select('id', 'title', 'icon', 'url', 'parameter')
            ->where($where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        $return_list = [];
        if (!$result->isEmpty()) {
            foreach ($result as $value) {
                $_item = $value;
                $child = self::getMenu($value['id']);
                if ($child) {
                    $_item['children'] = $child;
                }
                $return_list[] = $_item;
            }
        }
        return $return_list;
    }

    /**
     * 获取指定上级id下的所有菜单，按上下级排列（后台管理）
     * @param int $parent_id 上级id
     * @param int $hierarchy 层级 默认1
     * @return array
     */
    public static function getAll(int $parent_id = 0, int $hierarchy = 1): array
    {
        $where = [
            'parent_id' => $parent_id,
        ];
        $result = self::select('id', 'title', 'parent_id', 'url', 'parameter', 'position', 'status')
            ->where($where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        $return_list = [];
        if (!$result->isEmpty()) {
            foreach ($result->toArray() as $value) {
                $_item = $value;
                $_item['is_child'] = $hierarchy < self::MAX_HIERARCHY ? 1 : 0;
                if ($hierarchy < self::MAX_HIERARCHY) {
                    $child = self::getAll($value['id'], ($hierarchy + 1));
                    if ($child) {
                        $_item['children'] = $child;
                    }
                }
                $return_list[] = $_item;
            }
        }
        return $return_list;
    }

    /**
     * 获取指定上级id下的所有分类，按上下级排列（下拉框用）
     * @param int $parent_id 上级id
     * @param bool $is_children 是否需要下级
     * @return array
     */
    public static function getSelect(int $parent_id = 0, bool $is_children = false): array
    {
        $where = [
            'status' => self::STATUS_ON,
            'parent_id' => $parent_id,
        ];
        $result = self::select('id', 'title')
            ->where($where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        $return_list = [];
        if (!$result->isEmpty()) {
            foreach ($result->toArray() as $value) {
                $_item = $value;
                if ($is_children) {
                    $child = self::getSelect($value['id'], $is_children);
                    if ($child) {
                        $_item['children'] = $child;
                    }
                }
                $return_list[] = $_item;
            }
        }
        return $return_list;
    }
}
