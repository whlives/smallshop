<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/2
 * Time: 3:29 PM
 */

namespace App\Models\Seller;

use App\Models\BaseModel;

/**
 * 商家分类
 */
class SellerCategory extends BaseModel
{
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',

    ];

    protected $table = 'seller_category';
    protected $guarded = ['id'];

    /**
     * 获取指定上级id下的所有菜单，按上下级排列（后台管理）
     * @param int $seller_id 商家id
     * @param int $parent_id 上级id
     * @return array
     */
    public static function getAll(int $seller_id, int $parent_id = 0): array
    {
        $where = [
            'seller_id' => $seller_id,
            'parent_id' => $parent_id,
        ];
        $result = self::select('id', 'title', 'image', 'position', 'status')
            ->where($where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        $return_list = [];
        if (!$result->isEmpty()) {
            foreach ($result->toArray() as $value) {
                $_item = $value;
                $child = self::getAll($seller_id, $value['id']);
                if ($child) {
                    $_item['children'] = $child;
                }

                $return_list[] = $_item;
            }
        }
        return $return_list;
    }

    /**
     * 获取指定上级id下的所有分类，按上下级排列（下拉框用）
     * @param int $seller_id 商家id
     * @param int $parent_id 上级id
     * @param bool $is_children 是否需要下级
     * @return array
     */
    public static function getSelect(int $seller_id, int $parent_id = 0, bool $is_children = false): array
    {
        $where = [
            'seller_id' => $seller_id,
            'status' => self::STATUS_ON,
            'parent_id' => $parent_id,
        ];
        $result = self::select('id', 'title', 'id as value', 'title as name')
            ->where($where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        $return_list = [];
        if (!$result->isEmpty()) {
            foreach ($result->toArray() as $value) {
                $_item = $value;
                if ($is_children) {
                    $child = self::getSelect($seller_id, $value['id'], $is_children);
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