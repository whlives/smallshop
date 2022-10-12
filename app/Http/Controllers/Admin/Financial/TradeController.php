<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/24
 * Time: 2:32 PM
 */

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Financial\Trade;
use App\Models\Financial\TradeRefund;
use App\Models\Member\Member;
use App\Models\System\Payment;
use App\Services\ExportService;
use App\Services\TradeService;
use Illuminate\Http\Request;

class TradeController extends BaseController
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
        $export = (int)$request->input('export');
        $username = $request->input('username');
        $trade_no = $request->input('trade_no');
        $id = (int)$request->input('id');
        $payment_no = $request->input('payment_no');
        $status = $request->input('status');
        $type = (int)$request->input('type');
        if ($username) {
            $m_id = Member::where('username', $username)->value('id');
            if ($m_id) {
                $where[] = ['trade.m_id', $m_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        if ($trade_no) $where[] = ['trade.trade_no', $trade_no];
        if ($id) $where[] = ['trade.id', $id];
        if ($payment_no) $where[] = ['trade.payment_no', $payment_no];
        if (is_numeric($status)) $where[] = ['trade.status', $status];
        if ($type) $where[] = ['trade.type', $type];
        $where[] = ['trade.status', '<>', Trade::STATUS_OFF];//过滤掉没有付款的
        [$start_at, $end_at] = get_time_range();
        if ($start_at && $end_at) {
            $where[] = ['trade.created_at', '>=', $start_at];
            $where[] = ['trade.created_at', '<=', $end_at];
        }
        if ($export) {
            ExportService::trade($request, ['where' => $where], $start_at, $end_at);//导出数据
            exit;
        }
        $query = Trade::select('id', 'm_id', 'trade_no', 'type', 'subtotal', 'flag', 'payment_id', 'payment_no', 'pay_total', 'platform', 'status', 'pay_at', 'created_at')
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
            $_item['type'] = Trade::TYPE_DESC[$value['type']];
            $_item['status_text'] = Trade::STATUS_DESC[$value['status']];
            $_item['payment'] = Payment::PAYMENT_DESC[$value['payment_id']] ?? '';
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 状态
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus(Request $request)
    {
        return $this->success(Trade::STATUS_DESC);
    }

    /**
     * 类型
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getType(Request $request)
    {
        return $this->success(Trade::TYPE_DESC);
    }

    /**
     * 整个交易单退款
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function refund(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.invalid_params'));
        }
        $trade = Trade::where('id', $id)->first();
        if (!$trade) {
            api_error(__('admin.trade_error'));
        } elseif ($trade['status'] != Trade::STATUS_ON) {
            api_error(__('admin.trade_status_error'));
        }
        //查询是否已经有退款单，有的话不能再这里退款
        $trade_refund = TradeRefund::where(['trade_no' => $trade['trade_no'], 'status' => TradeRefund::STATUS_ON])->count();
        if ($trade_refund) {
            api_error(__('admin.trade_is_refund'));
        } else {
            $res_refund = TradeService::tradeRefund($trade['id'], $trade['trade_no'], $trade['pay_total'], TradeRefund::TYPE_TRADE);
            if ($res_refund === true) {
                Trade::where('id', $id)->update(['status' => Trade::STATUS_REFUND]);
                return $this->success();
            } else {
                //退款失败的话需要还原状态
                Trade::where('id', $id)->update(['status' => $trade['status']]);
                api_error($res_refund);
            }
        }
    }

}