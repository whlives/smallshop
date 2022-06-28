<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/23
 * Time: 1:41 PM
 */

namespace App\Http\Controllers\Admin\Market;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Market\CouponsDetail;
use App\Models\Member\Member;
use Illuminate\Http\Request;
use Validator;

class CouponsDetailController extends BaseController
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
        $coupons_id = (int)$request->input('coupons_id');
        $username = $request->input('username');
        if (!$coupons_id) {
            api_error(__('admin.content_is_empty'));
        }
        $where[] = ['coupons_id', $coupons_id];
        if ($username) {
            $member_id = Member::where('username', $username)->value('id');
            if ($member_id) {
                $where[] = ['m_id', $member_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        $query = CouponsDetail::select('id', 'm_id', 'status', 'is_use', 'use_at', 'bind_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $m_ids = array_column($res_list->toArray(), 'm_id');
        if ($m_ids) {
            $member_data = Member::whereIn('id', array_unique($m_ids))->pluck('username', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['is_use'] = CouponsDetail::USE_DESC[$value['is_use']];
            $_item['username'] = $member_data[$value['m_id']] ?? '';
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 添加编辑
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function save(Request $request)
    {
        //验证规则
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'coupons_id' => 'required|numeric',
            'num' => 'required|numeric',
        ], [
            'username.required' => '用户名不能为空',
            'coupons_id.required' => '优惠券id不能为空',
            'coupons_id.numeric' => '优惠券id只能是数字',
            'num.required' => '数量不能为空',
            'num.numeric' => '数量只能是数字',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $username = $request->input('username');
        $coupons_id = (int)$request->input('coupons_id');
        $num = (int)$request->input('num');
        //验证用户名
        $m_id = Member::where('username', $username)->value('id');
        if (!$m_id) {
            api_error(__('admin.user_error'));
        }
        $res = CouponsDetail::generate($coupons_id, $m_id, $num);
        if ($res === true) {
            return $this->success();
        } elseif ($res) {
            api_error($res);
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
        if (!isset(CouponsDetail::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = CouponsDetail::whereIn('id', $ids)->update(['status' => $status]);
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
        $res = CouponsDetail::whereIn('id', $ids)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }
}