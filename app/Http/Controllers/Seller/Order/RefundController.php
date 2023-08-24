<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:36 PM
 */

namespace App\Http\Controllers\Seller\Order;

use App\Http\Controllers\Seller\BaseController;
use App\Models\Member\Member;
use App\Models\Order\Order;
use App\Models\Order\OrderGoods;
use App\Models\Order\Refund;
use App\Models\Order\RefundLog;
use App\Models\Seller\SellerAddress;
use App\Models\System\ExpressCompany;
use App\Services\ExportService;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends BaseController
{
    public array $user_data;

    public function __construct()
    {
        $this->user_data = $this->getUserInfo();
    }

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
        $where[] = ['seller_id', $this->user_data['id']];
        $export = (int)$request->input('export');
        $order_id = (int)$request->input('order_id');
        $order_no = $request->input('order_no');
        $refund_no = $request->input('refund_no');
        $refund_type = (int)$request->input('refund_type');
        $status = $request->input('status');
        $username = $request->input('username');
        $time_type = $request->input('time_type');
        if ($order_id) $where[] = ['order_id', $order_id];
        if ($order_no) {
            $order_id = Order::query()->where('order_no', $order_no)->value('id');
            if ($order_id) {
                $where[] = ['order_id', $order_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        if ($refund_no) $where[] = ['refund_no', $refund_no];
        if ($refund_type) $where[] = ['refund_type', $refund_type];
        if (is_numeric($status)) $where[] = ['status', $status];
        if ($username) {
            $member_id = Member::query()->where('username', $username)->value('id');
            if ($member_id) {
                $where[] = ['m_id', $member_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        [$start_at, $end_at] = get_time_range();
        if ($start_at && $end_at && $time_type) {
            $where[] = ['refund.' . $time_type, '>=', $start_at];
            $where[] = ['refund.' . $time_type, '<=', $end_at];
        }
        if ($export) {
            ExportService::refund($request, ['where' => $where], $start_at, $end_at);//导出数据
            exit;
        }
        $query = Refund::query()->select('id', 'm_id', 'order_goods_id', 'refund_no', 'amount', 'refund_type', 'status', 'reason', 'created_at')
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
        $order_goods_id = array_column($res_list, 'order_goods_id');
        if ($order_goods_id) {
            $order_goods = OrderGoods::getGoodsForId($order_goods_id, true);
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['refund_type_text'] = Refund::REFUND_TYPE_DESC[$value['refund_type']];
            $_item['status_text'] = Refund::STATUS_DESC[$value['status']];
            $_item['goods_title'] = $order_goods[$value['order_goods_id']]['goods_title'] ?? '';
            $_item['image'] = $order_goods[$value['order_goods_id']]['image'] ?? '';
            $_item['spec_value'] = $order_goods[$value['order_goods_id']]['spec_value'] ?? '';
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
        return $this->success(Refund::STATUS_DESC);
    }

    /**
     * 售后详情
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
        $refund = self::checkRefund($id);
        $refund['is_refused'] = RefundService::isRefused($refund);//拒绝
        $refund['is_audit'] = RefundService::isAudit($refund);//审核
        $refund['is_confirm_goods'] = RefundService::isConfirmGoods($refund);//确认收货
        $refund['is_refused_goods'] = RefundService::isRefusedGoods($refund);//拒绝收货
        $refund['is_send'] = RefundService::isSend($refund);//发货
        $refund['is_pay'] = RefundService::isPay($refund);//打款
        $refund['status_text'] = Refund::STATUS_DESC[$refund['status']];
        $refund['refund_type_text'] = Refund::REFUND_TYPE_DESC[$refund['refund_type']];
        $log = RefundLog::getLog($refund);
        $order = Order::select('order_no', 'subtotal', 'status', 'note')->find($refund['order_id']);
        $order['status_text'] = Order::STATUS_DESC[$order['status']];
        $order_goods = OrderGoods::getGoodsForId([$refund['order_goods_id']], true);
        $address = [];
        if ($refund['refund_type'] != Refund::REFUND_TYPE_MONEY && $refund['is_audit']) {
            $address = SellerAddress::query()->select('id', 'full_name', 'tel', 'prov_name', 'city_name', 'area_name', 'address')->where('seller_id', $refund['seller_id'])->orderBy('default', 'desc')->get();
        }
        //物流公司
        $express_company = [];
        if ($refund['is_send']) {
            $express_company = ExpressCompany::query()->select('id', 'title')->where('status', ExpressCompany::STATUS_ON)->orderBy('id', 'desc')->get();
        }
        $return = [
            'refund' => $refund,
            'log' => $log,
            'order' => $order,
            'order_goods' => $order_goods[$refund['order_goods_id']] ?? [],
            'address' => $address,
            'express_company' => $express_company,
        ];
        return $this->success($return);
    }

    /**
     * 审核同意
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function audit(Request $request)
    {
        $id = (int)$request->input('id');
        $note = $request->input('note');
        $address_id = $request->input('address_id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $refund = self::checkRefund($id);
        //退货退款和换货的必须选择地址
        if ($refund['refund_type'] != Refund::REFUND_TYPE_MONEY) {
            if (!$address_id) {
                api_error(__('admin.refund_address_error'));
            }
            $address = SellerAddress::query()->where(['id' => $address_id, 'seller_id' => $refund['seller_id']])->first();
            if (!$address) {
                api_error(__('admin.refund_address_error'));
            }
            $res = RefundService::sellerAgreeDelivery($refund, $this->user_data, RefundLog::USER_TYPE_ADMIN, $note, $address->toArray());
        } else {
            //仅退款的
            $res = RefundService::sellerWaitPay($refund, $this->user_data, RefundLog::USER_TYPE_ADMIN, $note);
        }
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 审核拒绝
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function refused(Request $request)
    {
        $id = (int)$request->input('id');
        $note = $request->input('note');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $refund = self::checkRefund($id);
        $res = RefundService::sellerRefused($refund, $this->user_data, RefundLog::USER_TYPE_ADMIN, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
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
    public function confirmGoods(Request $request)
    {
        $id = (int)$request->input('id');
        $note = $request->input('note');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $refund = self::checkRefund($id);
        $res = RefundService::sellerConfirmGoods($refund, $this->user_data, RefundLog::USER_TYPE_ADMIN, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 拒绝收货
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function refusedGoods(Request $request)
    {
        $id = (int)$request->input('id');
        $note = $request->input('note');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $refund = self::checkRefund($id);
        $res = RefundService::sellerRefusedGoods($refund, $this->user_data, RefundLog::USER_TYPE_ADMIN, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 发货
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function send(Request $request)
    {
        $id = (int)$request->input('id');
        $company_id = (int)$request->post('company_id');
        $code = $request->post('code');
        $note = $request->input('note');
        if (!$id || !$company_id || !$code) {
            api_error(__('admin.missing_params'));
        }
        $refund = self::checkRefund($id);
        $express_company = ExpressCompany::query()->select('title', 'code')->where('id', $company_id)->first();
        if (!$express_company) {
            api_error(__('api.express_company_error'));
        }
        $param = [
            'express_company' => $express_company,
            'code' => $code,
        ];
        $res = RefundService::sellerSend($refund, $this->user_data, RefundLog::USER_TYPE_ADMIN, $note, $param);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 确认打款
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function pay(Request $request)
    {
        $id = (int)$request->input('id');
        $type = $request->input('type');
        $note = $request->input('note');
        if (!$id || !$type) {
            api_error(__('admin.missing_params'));
        }
        $refund = self::checkRefund($id);
        $original_road = $type == 'original_road_pay';
        $res = RefundService::sellerPay($refund, $this->user_data, RefundLog::USER_TYPE_ADMIN, $note, $original_road);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 验证订单
     * @param int $id
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    private function checkRefund(int $id)
    {
        $refund = Refund::query()->where('seller_id', $this->user_data['id'])->find($id);
        if (!$refund) {
            api_error(__('admin.invalid_params'));
        }
        return $refund->toArray();
    }

}
