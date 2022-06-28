<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:36 PM
 */

namespace App\Http\Controllers\Admin\Goods;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Goods\Category;
use App\Models\Goods\Goods;
use Illuminate\Http\Request;

class RecycleController extends BaseController
{
    /**
     * 列表获取
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        //搜索
        $where = [];
        $id = (int)$request->input('id');
        $title = $request->input('title');
        $category_id = (int)$request->input('category_id');
        if ($id) $where[] = ['id', $id];
        if ($title) $where[] = ['title', 'like', '%' . $title . '%'];
        if ($category_id) $where[] = ['category_id', $category_id];
        $query = Goods::onlyTrashed()->select('id', 'title', 'image', 'sell_price', 'category_id', 'type', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $category_ids = array_column($res_list->toArray(), 'category_id');
        if ($category_ids) {
            $category = Category::whereIn('id', array_unique($category_ids))->pluck('title', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['type'] = Goods::TYPE_DESC[$value['type']];
            $_item['category_name'] = $category[$value['category_id']] ?? '';
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 还原数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function restore(Request $request)
    {
        $ids = $this->checkBatchId();
        $res = Goods::whereIn('id', $ids)->restore();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 彻底删除数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function delete(Request $request)
    {
        $ids = $this->checkBatchId();
        $res = Goods::completelyDelete($ids);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }


}