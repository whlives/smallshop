<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 3:39 PM
 */

namespace App\Http\Controllers\V1;

use App\Exceptions\ApiError;
use App\Models\Goods\Category;
use App\Models\Goods\Comment;
use App\Models\Goods\CommentUrl;
use App\Models\Goods\Goods;
use App\Models\Market\PromoGroup;
use App\Models\Market\PromoSeckill;
use App\Models\Member\Favorite;
use App\Models\Member\Member;
use App\Models\Tool\Adv;
use App\Services\GoodsSearchService;
use App\Services\GoodsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GoodsController extends BaseController
{
    /**
     * 商城首页
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $banner = Adv::getAdv(100);
        $where = [
            'is_rem' => Goods::REM_ON
        ];
        $goods_data = GoodsSearchService::search($where, $limit, $offset);
        $return = [
            'banner' => $banner,
            'goods' => $goods_data
        ];
        return $this->success($return);
    }

    /**
     * 商品搜素
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function search(Request $request)
    {
        [$limit, $offset, $page] = get_page_params();
        $keyword = $request->input('keyword');
        $category_id = $request->input('category_id');
        $seller_category_id = $request->input('seller_category_id');
        $seller_id = $request->input('seller_id');
        $brand_id = $request->input('brand_id');
        $min_price = (int)$request->input('min_price');
        $max_price = (int)$request->input('max_price');
        $order_by = $request->input('order_by');
        $attribute = $request->input('attribute');
        $is_rem = $request->input('is_rem');
        if ($page > 100) {
            api_error(__('api.search_goods_max_page'));
        }
        //关键字、分类、必须有一个
        /*if (!$keyword && !$category_id) {
            api_error(__('api.search_key_and_category_error'));
        }*/
        //属性组装
        $where_attr = [];
        if ($attribute) {
            $_attribute = explode(';', $attribute);
            foreach ($_attribute as $value) {
                if ($value) {
                    $_value = explode(':', $value);
                    if ($_value[0] && $_value[1]) {
                        $where_attr[$_value[0]] = explode(',', $_value[1]);
                    }
                }
            }
        }
        //排序组装
        $order_by_data = [];
        if ($order_by) {
            switch ($order_by) {
                case 'sale':
                    $order_by_data['sale'] = 'desc';
                    break;
                case 'price_desc':
                    $order_by_data['sell_price'] = 'desc';
                    break;
                case 'price_asc':
                    $order_by_data['sell_price'] = 'asc';
                    break;
            }
        }
        $search_where = [
            'keyword' => $keyword,
            'category_id' => format_number($category_id),
            'seller_category_id' => format_number($seller_category_id),
            'seller_id' => format_number($seller_id),
            'brand_id' => format_number($brand_id),
            'min_price' => $min_price,
            'max_price' => $max_price,
            'attribute' => $where_attr,
            'is_rem' => $is_rem,
            'order_by' => $order_by_data
        ];
        $is_screening = ($page == 1);//只有第一页才出现筛选项
        $return = GoodsSearchService::search($search_where, $limit, $offset, $is_screening);
        if (!$return['total']) {
            api_error(__('api.content_is_empty'));
        }
        return $this->success($return);
    }

    /**
     * 商品详情
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function detail(Request $request)
    {
        $m_id = $this->getOnlyUserId();
        $id = (int)$request->route('id');
        if (!$id) {
            api_error(__('api.missing_params'));
        }
        $goods = Goods::getGoodsDetail($id);
        if (!$goods) {
            api_error(__('api.content_is_empty'));
        }
        //获取用户组价格
        $user_group = get_user_group();
        $goods_sku = $goods['sku'];
        foreach ($goods_sku as $key => $val) {
            $goods_sku[$key] = GoodsService::getVipPrice($val, $user_group);//获取折扣价格
        }
        $goods['sku'] = $goods_sku;
        //获取商品价格
        $goods['show_price'] = min(array_column($goods['sku'], 'show_price'));
        $goods['line_price'] = min(array_column($goods['sku'], 'line_price'));
        //是否收藏
        if ($m_id) {
            $goods['favorite'] = Favorite::getFavorite($m_id, Favorite::TYPE_GOODS, $goods['id']);
        }
        $error = '';
        //获取活动商品信息
        if ($goods['promo_type'] == Goods::PROMO_TYPE_SECKILL) {
            //查询秒杀信息
            $seckill = PromoSeckill::checkSeckill($id);
            [$pct, $stock, $remaining_stock] = Goods::getRedisStock($id);
            $seckill['pct'] = $pct;//秒杀进度
            $goods['seckill'] = $seckill;
            //秒杀库存读取redis
            $goods['stock'] = $remaining_stock;
            $promo_goods_sku = $goods['sku'];
            foreach ($promo_goods_sku as $key => $val) {
                $promo_goods_sku[$key]['stock'] = $stock[$val['id']] ?? 0;
            }
            $goods['sku'] = $promo_goods_sku;
        } elseif ($goods['promo_type'] == Goods::PROMO_TYPE_GROUP) {
            //查询拼团信息
            $group = PromoGroup::checkGroup($id);
            $goods['group'] = $group;
        } elseif ($goods['type'] == Goods::TYPE_COUPONS) {
            //有价优惠券
            [$pct, $stock, $remaining_stock] = Goods::getRedisStock($id);
            //秒杀库存读取redis
            $goods['stock'] = $remaining_stock;
            $promo_goods_sku = $goods['sku'];
            foreach ($promo_goods_sku as $key => $val) {
                $promo_goods_sku[$key]['stock'] = $stock[$val['id']] ?? 0;
            }
            $goods['sku'] = $promo_goods_sku;
        }
        $goods['error'] = substr($error, 6);
        //按钮显示
        $goods['button'] = Goods::button($goods->toArray());
        return $this->success($goods);
    }

    /**
     * 分类列表
     * @param Request $request
     * @return JsonResponse
     */
    public function category(Request $request)
    {
        $parent_id = (int)$request->route('parent_id', 0);
        $category = Category::getSelect($parent_id);
        return $this->success($category);
    }

    /**
     * 所有分类
     * @param Request $request
     * @return JsonResponse
     */
    public function categoryAll(Request $request)
    {
        $cache_key = get_cache_key('goods_category_all');
        $category = Cache::get($cache_key);
        if (!$category) {
            $category = Category::getSelect(0, true);
            Cache::put($cache_key, $category, get_custom_config('cache_time'));
        }
        return $this->success($category);
    }

    /**
     * 商品评价
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function comment(Request $request)
    {
        $id = (int)$request->route('id');
        if (!$id) {
            api_error(__('api.missing_params'));
        }
        [$limit, $offset] = get_page_params();
        $cache_key = get_cache_key('goods_comment', [$id, $limit, $offset]);
        $return = Cache::get($cache_key);
        if (!$return) {
            $where = [
                'goods_id' => $id,
                'status' => Comment::STATUS_ON
            ];
            $query = Comment::query()->select('id', 'm_id', 'spec_value', 'content', 'created_at')
                ->where($where);
            $total = $query->count();//总条数
            $res_list = $query->orderBy('id', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();
            $data_list = [];
            if (!$res_list->isEmpty()) {
                $res_list = $res_list->toArray();
                $m_ids = array_column($res_list, 'm_id');
                $comment_ids = array_column($res_list, 'id');
                //查询用户信息
                $res_member = Member::query()->whereIn('id', $m_ids)->select('id', 'nickname', 'headimg')->get();
                $member = array_column($res_member->toArray(), null, 'id');
                //查询图片、视频信息
                $image_url = $video_url = [];
                $url_res = CommentUrl::query()->select('comment_id', 'url', 'type')->whereIn('comment_id', array_unique($comment_ids))->get();
                if (!$url_res->isEmpty()) {
                    foreach ($url_res as $value) {
                        if ($value['type'] == CommentUrl::TYPE_IMAGE) {
                            $image_url[$value['comment_id']][] = ['url' => $value['url']];
                        } elseif ($value['type'] == CommentUrl::TYPE_VIDEO) {
                            $video_url[$value['comment_id']][] = ['url' => $value['url']];
                        }
                    }
                }
                foreach ($res_list as $value) {
                    $_item = $value;
                    $_item['nickname'] = $member[$value['m_id']]['nickname'] ?? '';
                    $_item['headimg'] = $member[$value['m_id']]['headimg'] ?? '';
                    $_item['image'] = $image_url[$value['id']] ?? [];
                    $_item['video'] = $video_url[$value['id']] ?? [];
                    $data_list[] = $_item;
                }
            }
            $return = [
                'lists' => $data_list,
                'total' => $total,
            ];
            Cache::put($cache_key, $return, get_custom_config('cache_time'));
        }
        return $this->success($return);
    }

}
