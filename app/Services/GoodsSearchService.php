<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/20
 * Time: 21:14 PM
 */

namespace App\Services;


use App\Models\Goods\Attribute;
use App\Models\Goods\AttributeValue;
use App\Models\Goods\Category;
use App\Models\Goods\Goods;
use App\Models\Goods\GoodsAttribute;
use App\Models\Goods\GoodsSellerCategory;
use App\Models\Seller\Seller;
use App\Models\System\Brand;
use Illuminate\Support\Facades\Cache;

class GoodsSearchService
{
    /**
     * 搜索商品
     * @param array $where_data 搜索条件
     * @param int $limit 查询条数
     * @param int $offset 偏移量
     * @param bool $is_screening 是否返回筛选项
     * @return array|mixed
     */
    public static function search(array $where_data = [], int $limit = 20, int $offset = 0, bool $is_screening = false)
    {
        //获取缓存信息
        $cache_key = 'goods_search:' . md5(json_encode($where_data) . 'limit' . $limit . 'offset' . $offset);
        $return = Cache::get($cache_key);
        if ($return) {
            return $return;
        }
        //排序默认
        $order_by_data = ['is_rem' => 'desc'];
        if (isset($where_data['order_by'])) {
            $order_by_data = array_merge($order_by_data, $where_data['order_by']);
        }
        $order_by_data['shelves_at'] = 'desc';
        $order_by_data['updated_at'] = 'desc';
        $where_data['order_by'] = $order_by_data;
        $goods_search_es = get_custom_config('goods_search_es');
        if ($goods_search_es) {
            //使用es搜索
            //$es_goods = new EsGoods();
            //$search_res = $es_goods->search($where_data, $limit, $offset);
        } else {
            //数据库搜索
            $search_res = self::getSearch($where_data, $limit, $offset);
        }
        [$goods_data, $total] = $search_res;
        $return = [
            'lists' => [],
            'total' => 0
        ];
        $data_list = $screening = [];
        $user_group = get_user_group();//获取用户组信息
        if ($goods_data) {
            foreach ($goods_data as $value) {
                $_item = [
                    'id' => $value['id'],
                    'title' => $value['title'],
                    'subtitle' => $value['subtitle'],
                    'image' => resize_images($value['image'], 500),
                    'sell_price' => $value['sell_price'],
                    'market_price' => $value['market_price'],
                    'seller_id' => $value['seller_id'],
                    'sale' => $value['sale']
                ];
                if ($is_screening) {
                    $screening['goods_id'][] = $value['id'];
                    $screening['brand_id'][] = $value['brand_id'];
                    $screening['seller_id'][] = $value['seller_id'];
                    $screening['category_id'][] = $value['category_id'];
                }
                $data_list[] = GoodsService::getVipPrice($_item, $user_group);
            }
            $return = [
                'lists' => $data_list,
                'total' => $total
            ];
            if ($is_screening) {
                $return['screening'] = self::screening($screening);
            }
            Cache::put($cache_key, $return, get_custom_config('cache_time'));
        }
        return $return;
    }

    /**
     * 从数据库搜索商品
     * @param array $where_data 搜索条件
     * @param int $limit 查询条数
     * @param int $offset 偏移量
     * @return array
     */
    public static function getSearch(array $where_data = [], int $limit = 20, int $offset = 0)
    {
        $where = $where_in = $where_not_in = $goods_ids = [];
        $where[] = ['status', Goods::STATUS_ON];//已审核
        $where[] = ['shelves_status', Goods::SHELVES_STATUS_ON];//上架
        //关键字
        if (isset($where_data['keyword']) && $where_data['keyword']) {
            $where[] = ['title', 'like', '%' . $where_data['keyword'] . '%'];
        }
        //推荐
        if (isset($where_data['is_rem']) && is_numeric($where_data['is_rem'])) {
            $where[] = ['is_rem', $where_data['is_rem']];
        }
        //条件过滤
        $where_data_arr = ['category_id', 'seller_id', 'brand_id', 'goods_id'];
        foreach ($where_data_arr as $type_id) {
            $_where_type = $where_data[$type_id] ?? '';
            if ($_where_type) {
                if (is_array($_where_type)) {
                    $where_in[$type_id] = $_where_type;
                } else {
                    $where[] = [$type_id, $_where_type];
                }
            }
        }
        $where_not_data_arr = ['not_category_id', 'not_seller_id', 'not_brand_id', 'not_goods_id'];
        foreach ($where_not_data_arr as $type_id) {
            $_where_type = $where_data[$type_id] ?? '';
            $_type_id = str_replace('not_', '', $type_id);
            if ($_where_type) {
                if (is_array($_where_type)) {
                    $where_not_in[$_type_id] = $_where_type;
                } else {
                    $where[] = [$_type_id, '<>', $_where_type];
                }
            }
        }
        //最小价格
        if (isset($where_data['min_price']) && $where_data['min_price']) {
            $where[] = ['sell_price', '>=', $where_data['min_price']];
        }
        //最大价格
        if (isset($where_data['max_price']) && $where_data['max_price']) {
            $where[] = ['sell_price', '<=', $where_data['max_price']];
        }
        //商家分类
        if (isset($where_data['seller_category_id']) && $where_data['seller_category_id']) {
            if (is_array($where_data['seller_category_id'])) {
                $goods_id = GoodsSellerCategory::query()->whereIn('category_id', $where_data['seller_category_id'])->pluck('goods_id')->toArray();
            } else {
                $goods_id = GoodsSellerCategory::query()->where('category_id', $where_data['seller_category_id'])->pluck('goods_id')->toArray();
            }
            if ($goods_id) $goods_ids = array_merge($goods_ids, $goods_id);
        }
        //属性
        if (isset($where_data['attribute']) && $where_data['attribute']) {
            $goods_attr_query = GoodsAttribute::query()->select('goods_id');
            foreach ($where_data['attribute'] as $attr_id => $attr_value) {
                if ($attr_id && $attr_value) {
                    $goods_attr_query->orWhere(function ($query) use ($attr_id, $attr_value) {
                        $query->where('attribute_id', $attr_id)->whereIn('value', $attr_value);
                    });
                }
            }
            $goods_id = $goods_attr_query->pluck('goods_id')->toArray();
            if ($goods_id) $goods_ids = array_merge($goods_ids, $goods_id);
        }
        if ($goods_ids) {
            $where_in['id'] = $goods_ids;
        }
        //开始查询数据
        $goods_query = Goods::query()->select('id', 'title', 'subtitle', 'image', 'sell_price', 'market_price', 'seller_id', 'sale', 'brand_id', 'seller_id', 'category_id')
            ->where($where);
        if ($where_in) {
            foreach ($where_in as $key => $value) {
                $goods_query->whereIn($key, $value);
            }
        }
        if ($where_not_in) {
            foreach ($where_not_in as $key => $value) {
                $goods_query->whereNotIn($key, $value);
            }
        }
        foreach ($where_data['order_by'] as $key => $value) {
            $goods_query->orderBy($key, $value);
        }
        $goods_query->join('goods_num', 'goods.id', '=', 'goods_num.goods_id');
        $total = $goods_query->count();
        $goods_data = $goods_query->offset($offset)
            ->limit($limit)
            ->get();
        if (!$goods_data->isEmpty()) {
            $goods_data = $goods_data->toArray();
        }
        return [$goods_data, $total];
    }

    /**
     * 获取筛选信息
     * @param array $screening 筛选的条件
     * @return array
     */
    static function screening(array $screening = [])
    {
        $return = [];
        if (isset($screening['brand_id']) && $screening['brand_id']) {
            $return['brand'] = Brand::query()->select('id', 'title')->whereIn('id', array_unique($screening['brand_id']))->get();
        }
        if (isset($screening['seller_id']) && $screening['seller_id']) {
            $return['seller'] = Seller::query()->select('id', 'title')->whereIn('id', array_unique($screening['seller_id']))->get();
        }
        if (isset($screening['category_id']) && $screening['category_id']) {
            $return['category'] = Category::query()->select('id', 'title')->whereIn('id', array_unique($screening['category_id']))->get();
        }
        //获取筛选属性
        if (isset($screening['goods_id']) && $screening['goods_id']) {
            $goods_attr_res = GoodsAttribute::query()->select('attribute_id', 'value')->whereIn('goods_id', $screening['goods_id'])->get();
            if (!$goods_attr_res->isEmpty()) {
                $attribute_ids = array_column($goods_attr_res->toArray(), 'attribute_id');
                if ($attribute_ids) {
                    $attribute = Attribute::query()->whereIn('id', array_unique($attribute_ids))->select('id', 'title', 'input_type')->get();
                    $attribute = array_column($attribute->toArray(), null, 'id');
                }
                //获取属性id
                $attr_value_ids = [];
                foreach ($goods_attr_res as $value) {
                    if (isset($attribute[$value['attribute_id']]) && $attribute[$value['attribute_id']]['input_type'] != 'text') {
                        $attr_value_ids[] = $value['value'];
                    }
                }
                //获取属性值
                if ($attr_value_ids) {
                    $attr_value = AttributeValue::query()->whereIn('id', array_unique($attr_value_ids))->pluck('value', 'id')->toArray();
                }
                //组装属性
                $goods_attr = [];
                foreach ($goods_attr_res as $value) {
                    $_attribute = $attribute[$value['attribute_id']] ?? [];
                    if ($_attribute && $_attribute['input_type'] != 'text') {
                        $_item = [
                            'id' => $value['value'],
                            'value' => $attr_value[$value['value']] ?? ''
                        ];
                        $goods_attr[$value['attribute_id']]['id'] = $value['attribute_id'];
                        $goods_attr[$value['attribute_id']]['name'] = $_attribute['title'];
                        $goods_attr[$value['attribute_id']]['value'][] = $_item;
                    }
                }
                $return['attribute'] = array_values($goods_attr);
            }
        }
        return $return;
    }
}
