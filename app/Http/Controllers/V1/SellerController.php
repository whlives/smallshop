<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/27
 * Time: 11:19 AM
 */

namespace App\Http\Controllers\V1;

use App\Models\Areas;
use App\Models\Seller\Seller;
use App\Models\Seller\SellerCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SellerController extends BaseController
{
    /**
     * 详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $seller_id = (int)$request->route('seller_id');
        if (!$seller_id) {
            api_error(__('api.missing_params'));
        }
        $seller = Seller::query()->select('id', 'title', 'image')->where('id', $seller_id)->first();
        $profile = $seller->profile;
        $area_name = Areas::getAreaName([$profile['prov_id'], $profile['city_id'], $profile['area_id']]);
        $return = [
            'id' => $seller['id'],
            'title' => $seller['title'],
            'image' => $seller['image'],
            'tel' => $profile['tel'],
            'prov_name' => $area_name[$profile['prov_id']] ?? '',
            'city_name' => $area_name[$profile['city_id']] ?? '',
            'area_name' => $area_name[$profile['area_id']] ?? '',
            'address' => $profile['address'],
            'content' => $profile['content'],
        ];
        return $this->success($return);
    }

    /**
     * 分类列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function category(Request $request)
    {
        $seller_id = (int)$request->route('seller_id');
        $parent_id = (int)$request->route('parent_id', 0);
        if (!$seller_id) {
            api_error(__('api.missing_params'));
        }
        $category = SellerCategory::getSelect($seller_id, $parent_id);
        return $this->success($category);
    }

    /**
     * 所有分类
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function categoryAll(Request $request)
    {
        $seller_id = (int)$request->route('seller_id');
        if (!$seller_id) {
            api_error(__('api.missing_params'));
        }
        $cache_key = get_cache_key('seller_goods_category_all', $seller_id);
        $category = Cache::get($cache_key);
        if (!$category) {
            $category = SellerCategory::getSelect($seller_id, 0, true);
            Cache::put($cache_key, $category, get_custom_config('cache_time'));
        }
        return $this->success($category);
    }

}
