<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\BaseController;
use App\Models\System\ExpressCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpressCompanyController extends BaseController
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
        $query = ExpressCompany::query()->select('id', 'title', 'code', 'position', 'created_at', 'status')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $return = [
            'lists' => $res_list,
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
        $data = ExpressCompany::query()->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        if ($data['param']) {
            $new_param = [];
            $param = json_decode($data['param'], true);
            foreach ($param as $key => $value) {
                $new_param[] = $key . ':' . $value;
            }
            $data['param'] = array_to_br_textarea($new_param);
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
            'code' => 'required',
            'param' => 'required',
            'position' => 'required|numeric',
        ], [
            'title.required' => '标题不能为空',
            'code.required' => '快递编码不能为空',
            'param.required' => '快递参数不能为空',
            'position.required' => '排序不能为空',
            'position.numeric' => '排序只能是数字',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title', 'code', 'position']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $param = $request->input('param');
        if ($param) {
            $param = textarea_br_to_array($param);
            $new_param = [];
            foreach ($param as $value) {
                $_value = explode(':', $value);
                $new_param[$_value[0]] = $_value[1];
            }
            $save_data['param'] = json_encode($new_param);
        }
        if ($id) {
            $res = ExpressCompany::query()->where('id', $id)->update($save_data);
        } else {
            $res = ExpressCompany::query()->create($save_data);
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
        if (!isset(ExpressCompany::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = ExpressCompany::query()->whereIn('id', $ids)->update(['status' => $status]);
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
        $res = ExpressCompany::query()->whereIn('id', $ids)->where([['id', '<>', ExpressCompany::NOT_DELIVERY]])->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 选择列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function select(Request $request)
    {
        $where = [
            'status' => ExpressCompany::STATUS_ON
        ];
        $res_list = ExpressCompany::query()->select('id', 'title')->where($where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'desc')
            ->get();
        return $this->success($res_list);
    }
}
