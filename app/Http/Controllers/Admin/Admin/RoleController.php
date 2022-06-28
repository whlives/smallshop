<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\Admin;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Admin\AdminRole;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class RoleController extends BaseController
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
        $query = AdminRole::select('id', 'title', 'created_at', 'status')
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
        $data = AdminRole::find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        $data['right'] = json_decode($data['right'], true);
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
            'title' => [
                'required',
                Rule::unique('admin_role')->ignore($id)
            ],
            'right' => 'required',
        ], [
            'title.required' => '角色名称不能为空',
            'title.unique' => '角色名称已经存在',
            'right.required' => '权限不能为空'
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $right = $request->input('right');
        foreach ($right as $key => $value) {
            if ($value) {
                foreach ($value as $k => $v) {
                    $right[$key][$k] = array_values($v);
                }
            }
        }
        $save_data['right'] = json_encode($right);
        if ($id) {
            $res = AdminRole::where('id', $id)->update($save_data);
        } else {
            $res = AdminRole::create($save_data);
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
        if (!isset(AdminRole::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = AdminRole::whereIn('id', $ids)->update(['status' => $status]);
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
        if (in_array(1, $ids)) {
            api_error(__('admin.admin_role_no_del'));
        }
        $res = AdminRole::whereIn('id', $ids)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 获取下拉列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function select(Request $request)
    {
        $res_list = AdminRole::select('id', 'title')->where('status', AdminRole::STATUS_ON)->get();
        return $this->success($res_list);
    }
}
