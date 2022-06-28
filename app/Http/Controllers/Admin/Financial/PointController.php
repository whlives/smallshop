<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/24
 * Time: 2:32 PM
 */

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Financial\Point;
use App\Models\Financial\PointDetail;
use App\Models\Member\Member;
use Illuminate\Http\Request;
use Validator;

class PointController extends BaseController
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
        $m_id = (int)$request->input('m_id');
        if ($username) {
            $m_id = Member::where('username', $username)->value('id');
            if ($m_id) {
                $where[] = ['m_id', $m_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        if ($m_id) $where[] = ['m_id', $m_id];
        $query = Point::select('id', 'm_id', 'amount', 'updated_at')
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
     * 批量充值
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function batchRecharge(Request $request)
    {
        //验证规则
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'amount' => 'required|price',
            'note' => 'required'
        ], [
            'username.required' => '用户名不能为空',
            'amount.required' => '金额不能为空',
            'amount.price' => '金额格式错误',
            'note.required' => '备注不能为空',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $username = str_replace('，', ',', $request->input('username'));
        $username = explode(',', $username);
        //查询id是否都存在
        $is_exists = $member = [];
        foreach ($username as $value) {
            $member_id = Member::where('username', $value)->value('id');
            if (!$member_id) {
                $is_exists[] = $value;
            } else {
                $member[$member_id] = $value;
            }
        }
        if ($is_exists) {
            api_error('1|用户名' . join(',', $is_exists) . '不存在');
        }
        if (!$member) {
            api_error(__('admin.invalid_params'));
        }
        //全部通过开始充值
        $amount = $request->input('amount');
        $note = $request->input('note');
        $error_username = [];
        foreach (array_keys($member) as $val) {
            $res = Point::updateAmount($val, $amount, PointDetail::EVENT_SYSTEM_RECHARGE, '', $note);
            if (!$res['status']) {
                $error_username[] = $member[$val];
            }
        }
        if ($error_username) {
            api_error('1|用户名' . join(',', $error_username) . '充值失败');
        } else {
            return $this->success();
        }
    }

    /**
     * 充值或者扣减
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function update(Request $request)
    {
        //验证规则
        $validator = Validator::make($request->all(), [
            'm_id' => 'required|numeric',
            'type' => 'required',
            'amount' => 'required|price',
            'note' => 'required'
        ], [
            'm_id.required' => '用户id错误',
            'm_id.numeric' => '用户id错误',
            'type.required' => '类型错误',
            'amount.required' => '金额不能为空',
            'amount.price' => '金额格式错误',
            'note.required' => '备注不能为空',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $m_id = (int)$request->input('m_id');
        $type = $request->input('type');
        $amount = $request->input('amount');
        $note = $request->input('note');
        //验证用户
        if (!Member::where('id', $m_id)->exists()) {
            api_error(__('admin.invalid_params'));
        }
        $event = '';
        if ($type == 'recharge') {
            $event = PointDetail::EVENT_SYSTEM_RECHARGE;
        } elseif ($type == 'deduct') {
            $event = PointDetail::EVENT_SYSTEM_DEDUCT;
            $amount = -$amount;
        }
        $res = Point::updateAmount($m_id, $amount, $event, '', $note);
        if ($res['status']) {
            return $this->success();
        } else {
            api_error($res['message']);
        }
    }

    /**
     * 详情列表获取
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        [$limit, $offset] = get_page_params();
        //搜索
        $where = [];
        $m_id = (int)$request->input('m_id');
        if (!$m_id) {
            api_error(__('admin.content_is_empty'));
        }
        $where[] = ['m_id', $m_id];
        $query = PointDetail::select('id', 'm_id', 'type', 'event', 'detail_no', 'amount', 'balance', 'note', 'created_at')
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
            $_item['event'] = PointDetail::EVENT_DESC[$value['event']];
            $_item['amount'] = ($value['type'] == PointDetail::TYPE_RECR ? '-' : '+') . $value['amount'];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }
}