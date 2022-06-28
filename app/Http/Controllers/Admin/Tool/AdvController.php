<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\Tool;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Tool\Adv;
use Illuminate\Http\Request;
use Validator;

class AdvController extends BaseController
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
        if ($title) $where[] = ['title', 'like', '%' . $title . '%'];
        $query = Adv::select('id', 'title', 'image', 'target_type', 'target_value', 'position', 'start_at', 'end_at', 'status', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['target_type'] = Adv::TARGET_TYPE_DESC[$value['target_type']];
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
        $data = Adv::find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
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
            'group_id' => 'required|numeric',
            'target_type' => 'required',
            'target_value' => 'required',
            'position' => 'required|numeric',
            'start_at' => 'required|date_format:"Y-m-d H:i:s"',
            'end_at' => 'required|date_format:"Y-m-d H:i:s"',
        ], [
            'title.required' => '名称不能为空',
            'group_id.required' => '广告组不能为空',
            'group_id.numeric' => '广告组只能是数字',
            'target_type.required' => '跳转类型不能为空',
            'target_value.required' => '跳转url或id不能为空',
            'position.required' => '排序不能为空',
            'position.numeric' => '排序只能是数字',
            'start_at.required' => '开始时间不能为空',
            'start_at.date_format' => '开始时间格式错误',
            'end_at.required' => '结束时间不能为空',
            'end_at.date_format' => '结束时间格式错误',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title', 'group_id', 'image', 'target_type', 'target_value', 'position', 'start_at', 'end_at']) as $key => $value) {
            $save_data[$key] = $value;
        }
        if ($id) {
            $res = Adv::where('id', $id)->update($save_data);
        } else {
            $res = Adv::create($save_data);
        }
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
        if (!isset(Adv::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Adv::whereIn('id', $ids)->update(['status' => $status]);
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
        $res = Adv::whereIn('id', $ids)->delete();
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
        $res = Adv::where('id', $id)->update([$field => $field_value]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.fail'));
        }
    }

    /**
     * 获取跳转类型
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function targetType(Request $request)
    {
        return $this->success(Adv::TARGET_TYPE_DESC);
    }

}
