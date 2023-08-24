<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\Member;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Member\Member;
use App\Models\Member\MemberAuth;
use App\Models\Member\MemberGroup;
use App\Models\Member\MemberLoginLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class MemberController extends BaseController
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
        $id = (int)$request->input('id');
        $nickname = $request->input('nickname');
        $full_name = $request->input('full_name');
        $group_id = (int)$request->input('group_id');
        if ($username) $where[] = ['username', $username];
        if ($id) $where[] = ['id', $id];
        if ($nickname) $where[] = ['nickname', 'like', '%' . $nickname . '%'];
        if ($full_name) $where[] = ['full_name', 'like', '%' . $full_name . '%'];
        if ($group_id) $where[] = ['group_id', $group_id];
        $query = Member::query()->select('id', 'username', 'nickname', 'headimg', 'full_name', 'group_id', 'status', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $res_list = $res_list->toArray();
        $group_ids = array_column($res_list, 'group_id');
        if ($group_ids) {
            $group = MemberGroup::query()->whereIn('id', array_unique($group_ids))->pluck('title', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['group_name'] = $group[$value['group_id']] ?? '';
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
        $data = Member::query()->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        $data = array_merge($data->toArray(), $data->profile->toArray());
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
                Rule::unique('member')->ignore($id)
            ],
            'nickname' => 'required',
            'group_id' => 'required|numeric',
            'email' => 'nullable|email',
            'sex' => 'numeric',
            'prov_id' => 'numeric',
            'city_id' => 'numeric',
            'area_id' => 'numeric'
        ], [
            'username.required' => '用户名不能为空',
            'username.unique' => '用户已经存在',
            'nickname.required' => '昵称不能为空',
            'group_id.required' => '用户组不能为空',
            'group_id.numeric' => '用户组只能是数字',
            'email.email' => 'email格式错误',
            'sex.numeric' => '性别只能是数字',
            'prov_id.numeric' => '省份只能是数字',
            'city_id.numeric' => '城市只能是数字',
            'area_id.numeric' => '地区只能是数字',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $member_data = [];
        foreach ($request->only(['username', 'group_id', 'nickname', 'headimg', 'full_name']) as $key => $value) {
            $member_data[$key] = $value;
        }
        $profile_data = [];
        foreach ($request->only(['email', 'sex', 'prov_id', 'city_id', 'area_id']) as $key => $value) {
            $profile_data[$key] = $value;
        }
        //判断密码是否有了
        $password = $request->input('password');
        if (!$id && !$password) {
            api_error(__('admin.admin_password_empty'));
        }
        if ($password) {
            $member_data['password'] = md5($password);
        }
        $res = Member::saveData($member_data, $profile_data, $id);
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
        if (!isset(Member::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Member::query()->whereIn('id', $ids)->update(['status' => $status]);
        if ($res) {
            if ($status == Member::STATUS_OFF) {
                //被禁用的账号踢出登录
                MemberLoginLog::removeLoginStatus($ids);
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
        $res = Member::query()->whereIn('id', $ids)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 删除绑定数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function unBind(Request $request)
    {
        $id = (int)$request->input('id');
        $type = (int)$request->input('type');
        if (!$id || !$type) {
            api_error(__('admin.missing_params'));
        }
        $res = MemberAuth::query()->where(['m_id' => $id, 'type' => $type])->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

}
