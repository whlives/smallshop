<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:41 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Http\Controllers\V1\BaseController;
use App\Models\Financial\Balance;
use App\Models\Financial\BalanceDetail;
use App\Models\Financial\BalanceRecharge;
use App\Models\Financial\Withdraw;
use Illuminate\Http\Request;

class BalanceController extends BaseController
{
    public int $m_id;

    public function __construct()
    {
        $this->m_id = $this->getUserId();
    }

    /**
     * 列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $event = (int)$request->post('event');
        $where = [
            'm_id' => $this->m_id
        ];
        if (isset(BalanceDetail::EVENT_DESC[$event])) $where['event'] = $event;
        $query = BalanceDetail::select('id', 'type', 'event', 'detail_no', 'amount', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['event'] = BalanceDetail::EVENT_DESC[$value['event']];
            $_item['amount'] = ($value['type'] == BalanceDetail::TYPE_INCR ? '+' : '-') . $value['amount'];
            unset($_item['type']);
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $id = (int)$request->post('id');
        if (!$id) {
            api_error(__('api.missing_params'));
        }
        $res_list = BalanceDetail::select('event', 'detail_no', 'amount', 'balance', 'note', 'created_at')->where(['id' => $id, 'm_id' => $this->m_id])->first();
        if (!$res_list) {
            api_error(__('api.content_is_empty'));
        }
        $res_list['event'] = BalanceDetail::EVENT_DESC[$res_list['event']];
        return $this->success($res_list);
    }

    /**
     * 在线充值
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function recharge(Request $request)
    {
        $amount = $request->post('amount');
        if (!$amount) {
            api_error(__('api.missing_params'));
        } elseif (!check_price($amount)) {
            api_error(__('api.invalid_params'));
        }
        $recharge_no = BalanceRecharge::getRechargeNo();
        $create_data = [
            'm_id' => $this->m_id,
            'recharge_no' => $recharge_no,
            'amount' => $amount,
        ];
        $res = BalanceRecharge::create($create_data);
        if ($res) {
            return $this->success(['recharge_no' => $recharge_no]);
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 用户提现
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function withdraw(Request $request)
    {
        $type = (int)$request->post('type');
        $amount = $request->post('amount');
        $name = $request->post('name');
        $bank_name = $request->post('bank_name');
        $pay_number = $request->post('pay_number');
        if (!$amount || !check_price($amount) || !$name || !$pay_number) {
            api_error(__('api.missing_params'));
        } elseif (!check_price($amount) || !isset(Withdraw::TYPE_DESC[$type])) {
            api_error(__('api.invalid_params'));
        }
        if ($type == Withdraw::TYPE_BANK && !$bank_name) {
            api_error(__('api.missing_params'));
        }
        $withdraw_no = Withdraw::getWithdrawNo();
        //获取姓名如果需要开启实名认证这里就需要加上姓名
        $res = Balance::updateAmount($this->m_id, -$amount, BalanceDetail::EVENT_WITHDRAW, $withdraw_no);
        if ($res['status']) {
            $withdraw_data = [
                'm_id' => $this->m_id,
                'withdraw_no' => $withdraw_no,
                'type' => $type,
                'amount' => $amount,
                'name' => $name,
                'bank_name' => $bank_name,
                'pay_number' => $pay_number,
            ];
            $add = Withdraw::create($withdraw_data);
            if ($add) {
                return $this->success();
            } else {
                api_error(__('api.fail'));
            }
        } else {
            api_error($res['message']);
        }
    }

    /**
     * 提现明细
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function withdrawLog(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $where = [
            'm_id' => $this->m_id
        ];
        $query = Withdraw::select('id', 'type', 'amount', 'status', 'refuse_note', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['type'] = Withdraw::TYPE_DESC[$value['type']];
            $_item['status'] = Withdraw::STATUS_DESC[$value['status']];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }
}