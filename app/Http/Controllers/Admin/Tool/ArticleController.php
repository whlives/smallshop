<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\Tool;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Tool\Article;
use App\Models\Tool\ArticleCategory;
use Illuminate\Http\Request;
use Validator;

class ArticleController extends BaseController
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
        $title = $request->input('title');
        $category_id = (int)$request->input('category_id');
        if ($title) $where[] = ['title', 'like', '%' . $title . '%'];
        if ($category_id) $where[] = ['category_id', $category_id];
        $query = Article::select('id', 'title', 'image', 'position', 'category_id', 'status', 'created_at')
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
            $category = ArticleCategory::whereIn('id', array_unique($category_ids))->pluck('title', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
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
     * 根据id获取信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $data = Article::find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        $data['content'] = $data->content()->value('content');
        return $this->success($data);
    }

    /**
     * 添加编辑
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function save(Request $request)
    {
        $id = (int)$request->input('id');
        //验证规则
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'category_id' => 'required|numeric',
        ], [
            'title.required' => '标题不能为空',
            'category_id.required' => '分类不能为空',
            'category_id.numeric' => '分类只能是数字',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title', 'image', 'category_id', 'content']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $save_data['content'] = remove_xss($save_data['content']);
        $res = Article::saveData($id, $save_data);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
    }

    /**
     * 修改状态
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function status(Request $request)
    {
        $ids = $this->checkBatchId();
        $status = (int)$request->input('status');
        if (!isset(Article::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Article::whereIn('id', $ids)->update(['status' => $status]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.fail'));
        }
    }

    /**
     * 删除数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function delete(Request $request)
    {
        $ids = $this->checkBatchId();
        $res = Article::deleteData($ids);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 修改单个字段值
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function fieldUpdate(Request $request)
    {
        $id = (int)$request->input('id');
        $field = $request->input('field');
        $field_value = $request->input('field_value');
        $field_arr = ['position'];//支持修改的字段
        if ($field == 'position') $field_value = (int)$field_value;
        if (!in_array($field, $field_arr) || !$id || !$field || !$field_value) {
            api_error(__('admin.invalid_params'));
        }
        $res = Article::where('id', $id)->update([$field => $field_value]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.fail'));
        }
    }

}
