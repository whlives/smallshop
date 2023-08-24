<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\Admin;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminLoginLog;
use App\Models\Admin\AdminRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Validator;

class AdminController extends BaseController
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
        $username = $request->input('username');
        $role_id = (int)$request->input('role_id');
        $status = $request->input('status');
        if ($username) $where[] = ['username', 'like', '%' . $username . '%'];
        if (is_numeric($status)) $where[] = ['status', $status];
        $query = Admin::query()->select('id', 'username', 'role_id', 'tel', 'email', 'status', 'created_at')->where($where);
        if ($role_id) $query->whereRaw("find_in_set('$role_id', role_id)");
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
            $_item['role_title'] = AdminRole::getRoleTitle(explode(',', $value['role_id']), true);
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 当前用户信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function info(Request $request)
    {
        $admin_data = $this->getUserInfo();
        return $this->success($admin_data);
    }

    /**
     * 修改信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function infoUpdate(Request $request)
    {
        //验证规则
        $validator = Validator::make($request->all(), [
            'tel' => 'required',
        ], [
            'tel.required' => '电话不能为空'
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['tel', 'email']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $password = $request->input('password');
        if ($password) $save_data['password'] = Hash::make(md5($password));

        $id = $this->getUserId();
        $res = Admin::query()->where('id', $id)->update($save_data);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
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
        $data = Admin::query()->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        $data['role_id'] = explode(',', $data['role_id']);
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
            'username' => [
                'required',
                Rule::unique('admin')->ignore($request->post('id'))
            ],
            'role_id' => 'required|array',
            'tel' => 'required'
        ], [
            'username.required' => '用户名不能为空',
            'username.unique' => '用户已经存在',
            'role_id.required' => '角色不能为空',
            'tel.required' => '电话不能为空',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['username', 'role_id', 'tel', 'email']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $save_data['role_id'] = join(',', $save_data['role_id']);
        $password = $request->input('password');
        if ($password) $save_data['password'] = Hash::make(md5($password));
        if ($id) {
            $res = Admin::query()->where('id', $id)->update($save_data);
        } else {
            if (!$password) {
                api_error(__('admin.admin_password_empty'));
            }
            $res = Admin::query()->create($save_data);
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
        if (!isset(Admin::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Admin::query()->whereIn('id', $ids)->update(['status' => $status]);
        if ($res) {
            if ($status == Admin::STATUS_OFF) {
                AdminLoginLog::removeLoginStatus($ids);//锁定的时候清除已经登录的账号
            }
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
        $res = Admin::query()->whereIn('id', $ids)->delete();
        if ($res) {
            AdminLoginLog::removeLoginStatus($ids);//清除已经登录的账号
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

}
