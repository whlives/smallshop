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
use Illuminate\Http\Request;

class TradeRefundController extends BaseController
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
        $refund_no = $request->input('refund_no');
        $order_no = $request->input('order_no');
        $trade_no = $request->input('trade_no');
        $payment_no = $request->input('payment_no');
        $type = (int)$request->input('type');
        $status = $request->input('status');
        if ($username) {
            $m_id = Member::query()->where('username', $username)->value('id');
            if ($m_id) {
                $where[] = ['trade_refund.m_id', $m_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        if ($refund_no) $where[] = ['trade_refund.refund_no', $refund_no];
        if ($order_no) $where[] = ['trade_refund.order_no', $order_no];
        if ($trade_no) $where[] = ['trade_refund.trade_no', $trade_no];
        if ($payment_no) $where[] = ['trade_refund.payment_no', $payment_no];
        if ($type) $where[] = ['trade_refund.type', $type];
        if (is_numeric($status)) $where[] = ['trade_refund.status', $status];
        [$start_at, $end_at] = get_time_range();
        if ($start_at && $end_at) {
            $where[] = ['trade_refund.created_at', '>=', $start_at];
            $where[] = ['trade_refund.created_at', '<=', $end_at];
        }
        if ($export) {
            ExportService::tradeRefund($request, ['where' => $where], $start_at, $end_at);//导出数据
            exit;
        }
        $query = TradeRefund::query()->select('id', 'm_id', 'refund_no', 'trade_no', 'order_no', 'type', 'subtotal', 'payment_id', 'payment_id', 'payment_no', 'platform', 'status', 'note', 'pay_at', 'created_at')
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
            $member_data = Member::query()->whereIn('id', array_unique($m_ids))->pluck('username', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['username'] = $member_data[$value['m_id']] ?? '';
            $_item['type'] = TradeRefund::TYPE_DESC[$value['type']];
            $_item['payment'] = Payment::PAYMENT_DESC[$value['payment_id']] ?? '';
            $_item['status_text'] = TradeRefund::STATUS_DESC[$value['status']];
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
        return $this->success(TradeRefund::TYPE_DESC);
    }

}
