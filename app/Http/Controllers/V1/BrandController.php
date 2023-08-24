<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 3:39 PM
 */

namespace App\Http\Controllers\V1;

use App\Models\System\Brand;
use Illuminate\Http\Request;

class BrandController extends BaseController
{
    /**
     * 列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $where = [
            'status' => Brand::STATUS_ON
        ];
        $query = Brand::query()->select('id', 'title', 'image')
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
        $detail = Brand::query()->select('id', 'title', 'image', 'content')->where('id', $id)->first();
        return $this->success($detail);
    }

}
