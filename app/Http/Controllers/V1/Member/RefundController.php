<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/17
 * Time: 4:26 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Http\Controllers\V1\BaseController;
use App\Models\Order\DeliveryTraces;
use App\Models\Order\OrderGoods;
use App\Models\Order\Refund;
use App\Models\Order\RefundDelivery;
use App\Models\Order\RefundImage;
use App\Models\Order\RefundLog;
use App\Models\Seller\Seller;
use App\Models\System\ExpressCompany;
use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends BaseController
{
    public array $member_data;

    public function __construct()
    {
        $this->member_data = $this->getUserInfo();
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
        $where = [
            'm_id' => $this->member_data['id'],
            'is_delete' => Refund::IS_DELETE_NO
        ];
        $query = Refund::query()->select('id', 'order_goods_id', 'seller_id', 'refund_no', 'refund_type', 'status')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $res_list = $res_list->toArray();
        $order_goods_ids = array_column($res_list, 'order_goods_id');
        $seller_ids = array_column($res_list, 'seller_id');
        //获取商品信息
        $order_goods = OrderGoods::getGoodsForId(array_unique($order_goods_ids), true);
        //获取商家信息
        $seller_res = Seller::query()->select('id', 'title', 'image')->whereIn('id', $seller_ids)->get();
        if ($seller_res->isEmpty()) {
            api_error(__('api.seller_error'));
        }
        $seller_res = array_column($seller_res->toArray(), null, 'id');
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = [
                'id' => $value['id'],
                'refund_no' => $value['refund_no'],
                'order_goods_id' => $value['order_goods_id'],
                'refund_type_text' => Refund::REFUND_TYPE_DESC[$value['refund_type']],
                'refund_type' => $value['refund_type'],
                'status' => $value['status'],
                'status_text' => Refund::STATUS_MEMBER_DESC[$value['status']],
                'goods' => $order_goods[$value['order_goods_id']] ?? [],
                'seller' => $seller_res[$value['seller_id']] ?? [],
            ];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 售后详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $order_goods_id = (int)$request->post('order_goods_id');
        if (!$order_goods_id) {
            api_error(__('api.missing_params'));
        }
        $refund = Refund::getInfoForOrderGoodsId($order_goods_id, $this->member_data['id']);
        $goods = OrderGoods::query()->select('goods_id', 'goods_title', 'image', 'spec_value')->where('id', $refund['order_goods_id'])->first();
        $return = [
            'refund_no' => $refund['refund_no'],
            'order_goods_id' => $refund['order_goods_id'],
            'amount' => $refund['amount'],
            'delivery_price' => $refund['delivery_price'],
            'refund_type' => $refund['refund_type'],
            'refund_type_text' => Refund::REFUND_TYPE_DESC[$refund['refund_type']],
            'reason' => Refund::REASON_DESC[$refund['refund_type']][$refund['reason']],
            'status' => $refund['status'],
            'status_text' => Refund::STATUS_DESC[$refund['status']],
            'created_at' => $refund['created_at'],
            'done_at' => $refund['done_at'],
            'goods' => $goods,
            'button' => RefundService::refundButton($refund)
        ];
        return $this->success($return);
    }

    /**
     * 售后日志
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function log(Request $request)
    {
        $refund_no = $request->post('refund_no');
        if (!$refund_no) {
            api_error(__('api.missing_params'));
        }
        $refund = Refund::getInfo($refund_no, $this->member_data['id']);
        $log = RefundLog::getLog($refund);
        return $this->success($log);
    }

    /**
     * 售后信息获取
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function apply(Request $request)
    {
        $order_goods_id = (int)$request->post('order_goods_id');
        if (!$order_goods_id) {
            api_error(__('api.missing_params'));
        }
        [$order_goods, $order, $refund, $max_amount, $delivery_price] = RefundService::checkRefund($order_goods_id, $this->member_data['id']);
        $return = [
            'goods' => [
                'goods_title' => $order_goods['goods_title'],
                'image' => $order_goods['image'],
                'buy_qty' => $order_goods['buy_qty'],
                'spec_value' => $order_goods['spec_value'],
            ],
            'amount' => 0,
            'max_amount' => $max_amount,
            'delivery_price' => $delivery_price,
            'refund_type' => 0,
            'reason' => 0,
            'note' => '',
            'image' => [],
            'reason_data' => Refund::formatReason()
        ];
        //修改时回填已经申请的信息
        if ($refund) {
            $return['amount'] = $refund['amount'];
            $return['refund_type'] = $refund['refund_type'];
            $return['reason'] = $refund['reason'];
            //查询最后一次的日志
            $last_log = RefundLog::query()->where(['refund_id' => $refund['id']])->orderBy('id', 'desc')->first();
            if ($last_log) {
                $return['note'] = $last_log['note'] ? json_decode($last_log['note'], true) : [];
                $image = RefundImage::query()->select('image')->where('log_id', $last_log['id'])->get();
                if ($image) {
                    $return['image'] = $image;
                }
            }
        }
        return $this->success($return);
    }

    /**
     * 提交售后信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function applyPut(Request $request)
    {
        $order_goods_id = (int)$request->post('order_goods_id');
        $refund_type = (int)$request->post('refund_type');
        $reason = (int)$request->post('reason');
        $note = $request->post('note');
        $image = $request->post('image');
        $amount = $request->post('amount');
        if (!$order_goods_id || !$refund_type || !$reason || !check_price($amount) || !isset(Refund::REASON_DESC[$refund_type][$reason])) {
            api_error(__('api.missing_params'));
        }
        [$order_goods, $order, $refund, $max_amount, $delivery_price] = RefundService::checkRefund($order_goods_id, $this->member_data['id'], $refund_type);
        if ($refund_type == Refund::REFUND_TYPE_REPLACE) {
            $amount = 0;//换货的时候金额为0
        }
        if ($amount > $max_amount) {
            api_error(__('api.refund_amount_error'));
        }
        $apply_data = [
            'order_id' => $order['id'],
            'order_goods_id' => $order_goods_id,
            'payment_id' => $order['payment_id'],
            'seller_id' => $order['seller_id'],
            'm_id' => $this->member_data['id'],
            'amount' => $amount,
            'max_amount' => $max_amount,
            'delivery_price' => $delivery_price,
            'refund_type' => $refund_type,
            'reason' => $reason,
            'status' => Refund::STATUS_WAIT_APPROVE,
        ];
        $log_note = [
            [
                'title' => '退款类型',
                'info' => Refund::REFUND_TYPE_DESC[$refund_type]
            ],
            [
                'title' => '退款金额',
                'info' => '￥' . $amount
            ],
            [
                'title' => '退款原因',
                'info' => Refund::REASON_DESC[$refund_type][$reason]
            ]
        ];
        if ($note) {
            $log_note[] = ['title' => '备注', 'info' => $note];
        }
        //售后日志信息
        $refund_log = [
            'user_type' => RefundLog::USER_TYPE_MEMBER,
            'user_id' => $this->member_data['id'],
            'username' => $this->member_data['username'],
            'action' => RefundLog::ACTION_EDIT,
            'note' => $log_note ? json_encode($log_note, JSON_UNESCAPED_UNICODE) : '',
        ];
        if (!$refund) {
            $refund_log['action'] = RefundLog::ACTION_APPLY;
            $apply_data['refund_no'] = RefundService::getRefundNo();
        }
        $image = $image ? explode(',', $image) : [];
        $res = RefundService::putRefund($refund, $apply_data, $refund_log, $image);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('api.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 售后发货
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function delivery(Request $request)
    {
        $refund_no = $request->post('refund_no');
        $company_id = (int)$request->post('company_id');
        $code = $request->post('code');
        $note = $request->post('note');
        if (!$refund_no || !$company_id || !$code) {
            api_error(__('api.missing_params'));
        }
        $refund = Refund::getInfo($refund_no, $this->member_data['id']);

        $express_company = ExpressCompany::query()->select('title', 'code')->where('id', $company_id)->first();
        if (!$express_company) {
            api_error(__('api.express_company_error'));
        }
        $param = [
            'express_company' => $express_company,
            'code' => $code,
        ];
        $res = RefundService::delivery($refund, $this->member_data, RefundLog::USER_TYPE_MEMBER, $note, $param);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('api.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 物流轨迹
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function deliveryLog(Request $request)
    {
        $refund_no = $request->post('refund_no');
        if (!$refund_no) {
            api_error(__('api.missing_params'));
        }
        $refund = Refund::getInfo($refund_no, $this->member_data['id']);

        $delivery_traces = [];
        $delivery_res = RefundDelivery::query()->select('company_code', 'company_name', 'code')->where(['refund_id' => $refund['id'], 'type' => RefundDelivery::TYPE_MEMBER])->get();
        if ($delivery_res->isEmpty()) {
            return $this->success($delivery_traces);
        }
        $traces_query = DeliveryTraces::query()->select('company_code', 'code', 'accept_time', 'info');
        foreach ($delivery_res as $value) {
            $delivery_traces[$value['company_code'] . $value['code']] = [
                'company_name' => $value['company_name'],
                'code' => $value['code'],
                'traces' => []
            ];
            $traces_query->orWhere(function ($query) use ($value) {
                $query->where(['company_code' => $value['company_code'], 'code' => $value['code']]);
            });
        }
        $traces_res = $traces_query->orderBy('id', 'asc')->get();
        if (!$traces_res->isEmpty()) {
            foreach ($traces_res as $value) {
                $_item = [
                    'accept_time' => $value['accept_time'],
                    'info' => $value['info'],
                ];
                $delivery_traces[$value['company_code'] . $value['code']]['traces'][] = $_item;
            }
        }
        return $this->success(array_values($delivery_traces));
    }

    /**
     * 撤销售后
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function cancel(Request $request)
    {
        $refund_no = $request->post('refund_no');
        $note = $request->post('note');
        if (!$refund_no) {
            api_error(__('api.missing_params'));
        }
        $note = $note ?: '用户取消售后';
        $refund = Refund::getInfo($refund_no, $this->member_data['id']);
        $res = RefundService::cancel($refund, $this->member_data, RefundLog::USER_TYPE_MEMBER, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('api.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 确认收货
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function confirm(Request $request)
    {
        $refund_no = $request->post('refund_no');
        $note = $request->post('note');
        if (!$refund_no) {
            api_error(__('api.missing_params'));
        }
        $refund = Refund::getInfo($refund_no, $this->member_data['id']);
        $res = RefundService::confirm($refund, $this->member_data, RefundLog::USER_TYPE_MEMBER, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('api.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 删除订单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function delete(Request $request)
    {
        $refund_no = $request->post('refund_no');
        if (!$refund_no) {
            api_error(__('api.missing_params'));
        }
        $refund = Refund::getInfo($refund_no, $this->member_data['id']);
        if (!RefundService::isUserDelete($refund)) {
            api_error(__('api.refund_status_error'));
        }
        $res = Refund::query()->where(['m_id' => $this->member_data['id'], 'refund_no' => $refund_no])->update(['is_delete' => Refund::IS_DELETE_YES]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

}
