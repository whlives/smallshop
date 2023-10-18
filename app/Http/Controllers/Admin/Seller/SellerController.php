<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\Seller;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Seller\Seller;
use App\Models\Seller\SellerLoginLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class SellerController extends BaseController
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
        $title = $request->input('title');
        if ($username) $where[] = ['username', $username];
        if ($title) $where[] = ['title', 'like', '%' . $title . '%'];
        $query = Seller::query()->select('id', 'username', 'title', 'image', 'level', 'status', 'created_at')
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
        $data = Seller::query()->find($id);
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
                Rule::unique('seller')->ignore($id)
            ],
            'title' => 'required',
            'image' => 'required',
            'invoice' => 'numeric|required',
            'pct' => 'required|numeric|between:0,100',
            'email' => 'nullable|email',
            'prov_id' => 'numeric',
            'city_id' => 'numeric',
            'area_id' => 'numeric'
        ], [
            'username.required' => '用户名不能为空',
            'username.unique' => '用户已经存在',
            'image.required' => 'logo不能为空',
            'invoice.numeric' => '发票只能是数字',
            'invoice.required' => '发票不能为空',
            'pct.required' => '手续费不能为空',
            'pct.numeric' => '手续费只能是数字',
            'pct.between' => '手续费只能是0-100的整数',
            'email.email' => 'email格式错误',
            'prov_id.numeric' => '省份只能是数字',
            'city_id.numeric' => '城市只能是数字',
            'area_id.numeric' => '地区只能是数字',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $member_data = [];
        foreach ($request->only(['username', 'title', 'image', 'invoice', 'pct']) as $key => $value) {
            $member_data[$key] = $value;
        }
        $profile_data = [];
        foreach ($request->only(['business_license', 'tel', 'email', 'prov_id', 'city_id', 'area_id', 'address', 'content']) as $key => $value) {
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
        $res = Seller::saveData($member_data, $profile_data, $id);
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
        if (!isset(Seller::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Seller::query()->whereIn('id', $ids)->update(['status' => $status]);
        if ($res) {
            if ($status == Seller::STATUS_OFF) {
                SellerLoginLog::removeLoginStatus($ids);//锁定的时候清除已经登录的账号
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
        $res = Seller::query()->whereIn('id', $ids)->delete();
        if ($res) {
            SellerLoginLog::removeLoginStatus($ids);//清除已经登录的账号
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
            'status' => Seller::STATUS_ON
        ];
        $res_list = Seller::query()->select('id', 'title')->where($where)
            ->orderBy('id', 'desc')
            ->get();
        return $this->success($res_list);
    }

}
