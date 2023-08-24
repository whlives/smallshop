<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 3:39 PM
 */

namespace App\Http\Controllers\V1;

use App\Models\Tool\Article;
use App\Models\Tool\ArticleCategory;
use Illuminate\Http\Request;

class ArticleController extends BaseController
{
    /**
     * 列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        $category_id = (int)$request->route('category_id');
        if (!$category_id) {
            api_error(__('api.missing_params'));
        }
        [$limit, $offset] = get_page_params();
        $where = [
            'category_id' => $category_id,
            'status' => Article::STATUS_ON
        ];
        $query = Article::query()->select('id', 'title', 'image', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('position', 'asc')
            ->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $return = [
            'lists' => $res_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $id = (int)$request->route('id');
        if (!$id) {
            api_error(__('api.missing_params'));
        }
        $detail = Article::query()->select('id', 'title', 'image', 'created_at')->where('id', $id)->first();
        if ($detail) {
            $detail['content'] = $detail->content()->value('content');
        }
        return $this->success($detail);
    }

    /**
     * 分类
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function category(Request $request)
    {
        $parent_id = (int)$request->route('parent_id', 0);
        $where = [
            'parent_id' => $parent_id,
            'status' => ArticleCategory::STATUS_ON
        ];
        $res_list = ArticleCategory::query()->select('id', 'title')
            ->where($where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        return $this->success($res_list);
    }

}
