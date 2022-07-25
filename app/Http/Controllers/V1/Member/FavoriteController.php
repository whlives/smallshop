<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:41 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Http\Controllers\V1\BaseController;
use App\Models\Goods\Goods;
use App\Models\Member\Favorite;
use App\Models\Seller\Seller;
use App\Models\Tool\Article;
use Illuminate\Http\Request;
use function Symfony\Component\String\b;

class FavoriteController extends BaseController
{
    public int $m_id;

    public function __construct()
    {
        $this->m_id = $this->getUserId();
    }

    /**
     * 商品收藏
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function goods(Request $request)
    {
        return self::favorite(Favorite::TYPE_GOODS);
    }

    /**
     * 商家收藏
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function seller(Request $request)
    {
        return self::favorite(Favorite::TYPE_SELLER);
    }

    /**
     * 文章收藏
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function article(Request $request)
    {
        return self::favorite(Favorite::TYPE_ARTICLE);
    }

    /**
     * 收藏列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    private function favorite($type)
    {
        [$limit, $offset] = get_page_params();
        $where = [
            'm_id' => $this->m_id,
            'type' => $type
        ];
        $query = Favorite::where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->pluck('object_id');
        if (!$res_list) {
            api_error(__('api.content_is_empty'));
        }
        switch ($type) {
            case Favorite::TYPE_GOODS:
                $object_res = Goods::select('id', 'title', 'image')->whereIn('id', $res_list->toArray())->get();
                break;
            case Favorite::TYPE_SELLER:
                $object_res = Seller::select('id', 'title', 'image')->whereIn('id', $res_list->toArray())->get();
                break;
            case Favorite::TYPE_ARTICLE:
                $object_res = Article::select('id', 'title', 'image')->whereIn('id', $res_list->toArray())->get();
                break;
        }
        if ($object_res->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $object_res = array_column($object_res->toArray(), null, 'id');
        $data_list = [];
        foreach ($res_list as $value) {
            if (isset($object_res[$value])) {
                $data_list[] = $object_res[$value];
            }
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 添加/取消收藏
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function set(Request $request)
    {
        $id = (int)$request->post('id');
        $type = (int)$request->post('type');
        if (!$id) {
            api_error(__('api.missing_params'));
        } elseif (!isset(Favorite::TYPE_DESC[$type])) {
            api_error(__('api.invalid_params'));
        }
        $data = [
            'm_id' => $this->m_id,
            'type' => $type,
            'object_id' => $id,
        ];
        if (Favorite::where($data)->exists()) {
            //已经存在就取消
            $res = Favorite::where($data)->delete();
            $action = 'del';
        } else {
            $res = Favorite::create($data);
            $action = 'add';
        }
        if ($res) {
            return $this->success(['action' => $action]);
        } else {
            api_error(__('api.fail'));
        }
    }
}