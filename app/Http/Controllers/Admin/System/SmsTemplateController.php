<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\BaseController;
use App\Models\System\SmsTemplate;
use Illuminate\Http\Request;
use Validator;

class SmsTemplateController extends BaseController
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
        $query = SmsTemplate::select('id', 'title', 'type', 'content', 'created_at')
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
        $data = SmsTemplate::find($id);
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
            'type' => 'required',
            'content' => 'required',
        ], [
            'title.required' => '标题不能为空',
            'type.required' => '类型不能为空',
            'content.numeric' => '内容不能为空',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title', 'type', 'content']) as $key => $value) {
            $save_data[$key] = $value;
        }
        if ($id) {
            $res = SmsTemplate::where('id', $id)->update($save_data);
        } else {
            $res = SmsTemplate::create($save_data);
        }
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
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
        $res = SmsTemplate::whereIn('id', $ids)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

}
