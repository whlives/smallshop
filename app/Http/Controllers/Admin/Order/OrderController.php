<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:36 PM
 */

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Admin\BaseController;
use App\Libs\Delivery;
use App\Models\Areas;
use App\Models\Financial\Trade;
use App\Models\Member\Member;
use App\Models\Order\Order;
use App\Models\Order\OrderDelivery;
use App\Models\Order\OrderDeliveryTemplate;
use App\Models\Order\OrderGoods;
use App\Models\Order\OrderInvoice;
use App\Models\Order\OrderLog;
use App\Models\Order\Refund;
use App\Models\Seller\Seller;
use App\Models\Seller\SellerAddress;
use App\Models\System\ExpressCompany;
use App\Models\System\Payment;
use App\Services\ExportService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends BaseController
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
        $where = $where_in = [];
        $export = (int)$request->input('export');
        $order_no = $request->input('order_no');
        $full_name = $request->input('full_name');
        $username = $request->input('username');
        $tel = $request->input('tel');
        $trade_no = $request->input('trade_no');
        $seller_id = $request->input('seller_id');
        $status = $request->input('status');
        $time_type = $request->input('time_type');
        $id = (int)$request->input('id');
        $payment_no = $request->input('payment_no');
        $delivery = (int)$request->input('delivery');
        if ($order_no) $where[] = ['order_no', $order_no];
        if ($full_name) $where[] = ['full_name', $full_name];
        if ($username) {
            $member_id = Member::query()->where('username', $username)->value('id');
            if ($member_id) {
                $where[] = ['m_id', $member_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        if ($tel) $where[] = ['tel', $tel];
        if ($trade_no) {
            $trade_id = Trade::query()->where('trade_no', $trade_no)->value('id');
            if ($trade_id) {
                $where[] = ['trade_id', $trade_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        if ($seller_id) $where[] = ['seller_id', $seller_id];
        if (is_numeric($status)) $where[] = ['status', $status];
        [$start_at, $end_at] = get_time_range();
        if ($start_at && $end_at && $time_type) {
            $where[] = [$time_type, '>=', $start_at];
            $where[] = [$time_type, '<=', $end_at];
        }
        if ($id) $where[] = ['id', $id];
        if ($payment_no) $where[] = ['payment_no', $payment_no];
        if ($delivery) {
            //发货筛选
            switch ($delivery) {
                case 1:
                    $where[] = ['status', Order::STATUS_PAID];
                    break;
                case 2:
                    $where_in['status'] = [Order::STATUS_SHIPMENT, Order::STATUS_PART_SHIPMENT];
                    break;
            }
        }
        if ($export) {
            ExportService::order($request, ['where' => $where, 'where_in' => $where_in], $start_at, $end_at);//导出数据
            exit;
        }
        $query = Order::query()->select('id', 'm_id', 'order_no', 'flag', 'payment_id', 'subtotal', 'full_name', 'tel', 'status', 'pay_at', 'created_at')
            ->where($where);
        if ($where_in) {
            foreach ($where_in as $key => $val) {
                $query->wherein($key, $val);
            }
        }
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $res_list = $res_list->toArray();
        $m_ids = array_column($res_list, 'm_id');
        $order_ids = array_column($res_list, 'id');
        if ($m_ids) {
            $member_data = Member::query()->whereIn('id', array_unique($m_ids))->pluck('username', 'id');
        }
        if ($order_ids) {
            $order_goods = OrderGoods::getGoodsForOrderId($order_ids, true);
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['subtotal'] = '￥' . $value['subtotal'];
            $_item['status_text'] = Order::STATUS_DESC[$value['status']];
            $_item['username'] = $member_data[$value['m_id']] ?? '';
            $_item['payment'] = Payment::PAYMENT_DESC[$value['payment_id']] ?? '';
            $_item['goods'] = $order_goods[$value['id']] ?? [];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 订单详情
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
        $order = Order::find($id);
        if (!$order) {
            api_error(__('admin.content_is_empty'));
        }
        $order = $order->toArray();
        $order['is_cancel'] = OrderService::isCancel($order);
        $order['is_delivery'] = OrderService::isDelivery($order);
        $order['is_pay'] = OrderService::isPay($order);
        $order['is_confirm'] = OrderService::isConfirm($order);
        $order['status_text'] = Order::STATUS_DESC[$order['status']];
        $order['delivery_type'] = Order::DELIVERY_DESC[$order['delivery_type']];
        $order['payment_name'] = Payment::PAYMENT_DESC[$order['payment_id']] ?? '';
        //人员信息
        $member_data = Member::query()->select("id", "username", "nickname")->whereIn('id', [$order['m_id'], $order['level_one_m_id'], $order['level_two_m_id']])->get();
        if (!$member_data->isEmpty()) {
            $member_data = array_column($member_data->toArray(), null, 'id');
        }
        $order['username'] = $member_data[$order['m_id']]['nickname'] ?? '';
        $order['level_one_m_name'] = $member_data[$order['level_one_m_id']]['nickname'] ?? '';
        $order['level_two_m_name'] = $member_data[$order['level_two_m_id']]['nickname'] ?? '';
        //店铺信息
        $seller = Seller::query()->select('id', 'title')->find($order['seller_id']);
        //发票信息
        $invoice = OrderInvoice::query()->select('type', 'title', 'tax_no')->where('order_id', $id)->first();
        if ($invoice) $invoice['type_text'] = OrderInvoice::TYPE_DESC[$invoice['type']];
        //物流公司
        $express_company = [];
        if ($order['is_delivery']) {
            $express_company = ExpressCompany::query()->select('id', 'title')->where('status', ExpressCompany::STATUS_ON)->orderBy('id', 'desc')->get();
        }
        $return = [
            'order' => $order,
            'seller' => $seller,
            'goods' => OrderGoods::getGoodsForOrderId([$order['id']]),
            'express_company' => $express_company,
            'invoice' => $invoice
        ];
        return $this->success($return);
    }

    /**
     * 获取发货信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function getDelivery(Request $request)
    {
        $order_id = (int)$request->input('order_id');
        if (!$order_id) {
            api_error(__('admin.missing_params'));
        }
        $res_list = OrderDelivery::query()->select('order_goods_id', 'company_name', 'company_code', 'code', 'note', 'created_at')
            ->where('order_id', $order_id)
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['goods'] = [];
            if ($value['order_goods_id']) {
                $order_goods_id = json_decode($value['order_goods_id'], true);
                $_item['goods'] = OrderGoods::getGoodsForId($order_goods_id);
            }
            unset($value['order_goods_id']);
            $data_list[] = $_item;
        }
        return $this->success($data_list);
    }

    /**
     * 获取日志信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function getLog(Request $request)
    {
        $order_id = (int)$request->input('order_id');
        if (!$order_id) {
            api_error(__('admin.missing_params'));
        }
        $res_list = OrderLog::query()->select('username', 'user_type', 'action', 'note', 'created_at')
            ->where('order_id', $order_id)
            ->orderBy('id', 'desc')
            ->get();

        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['user_type'] = OrderLog::USER_TYPE_DESC[$value['user_type']];
            $_item['action'] = OrderLog::ACTION_DESC[$value['action']];
            $data_list[] = $_item;
        }
        return $this->success($data_list);
    }

    /**
     * 获取售后信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function getRefund(Request $request)
    {
        $order_id = (int)$request->input('order_id');
        if (!$order_id) {
            api_error(__('admin.missing_params'));
        }
        $res_list = Refund::query()->select('id', 'm_id', 'order_goods_id', 'refund_no', 'amount', 'refund_type', 'status', 'reason', 'created_at')
            ->where('order_id', $order_id)
            ->orderBy('id', 'desc')
            ->get();

        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $order_goods_id = array_column($res_list->toArray(), 'order_goods_id');
        if ($order_goods_id) {
            $order_goods = OrderGoods::getGoodsForId($order_goods_id, true);
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['refund_type_text'] = Refund::REFUND_TYPE_DESC[$_item['refund_type']];
            $_item['status_text'] = Refund::STATUS_DESC[$_item['status']];
            $_item['goods'] = $order_goods[$value['order_goods_id']] ?? [];
            $data_list[] = $_item;
        }
        return $this->success($data_list);
    }

    /**
     * 状态
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus(Request $request)
    {
        return $this->success(Order::STATUS_DESC);
    }

    /**
     * 获取价格
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function getPrice(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $data = Order::query()->select('id', 'sell_price_total', 'promotion_price', 'discount_price', 'delivery_price_real', 'subtotal')->find($id);
        if (!$data) {
            api_error(__('admin.order_error'));
        }
        return $this->success($data);
    }

    /**
     * 修改价格
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function updatePrice(Request $request)
    {
        $id = (int)$request->input('id');
        $discount_price = $request->input('discount_price');
        $delivery_price_real = $request->input('delivery_price_real');
        if (!$id || !check_price(abs($discount_price)) || !check_price($delivery_price_real)) {
            api_error(__('admin.missing_params'));
        }
        $order = Order::query()->find($id);
        if (!$order) {
            api_error(__('admin.order_error'));
        }
        $res = OrderService::updatePrice($order->toArray(), $discount_price, $delivery_price_real, $this->user_data, OrderLog::USER_TYPE_ADMIN);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 获取地址
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function getAddress(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $data = Order::query()->select('id', 'full_name', 'tel', 'prov', 'city', 'area', 'address')->find($id);
        if (!$data) {
            api_error(__('admin.order_error'));
        }
        $data['prov_id'] = $data['city_id'] = $data['area_id'] = 0;
        if ($data['prov']) {
            $data['prov_id'] = Areas::getAreaId($data['prov'], 0);
        }
        if ($data['prov_id'] && $data['city']) {
            $data['city_id'] = Areas::getAreaId($data['city'], $data['prov_id']);
        }
        if ($data['city_id'] && $data['area']) {
            $data['area_id'] = Areas::getAreaId($data['area'], $data['city_id']);
        }
        return $this->success($data);
    }

    /**
     * 修改地址
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function updateAddress(Request $request)
    {
        $id = (int)$request->input('id');
        $prov_id = (int)$request->input('prov_id');
        $city_id = (int)$request->input('city_id');
        $area_id = (int)$request->input('area_id');
        $full_name = $request->input('full_name');
        $tel = $request->input('tel');
        $address = $request->input('address');
        if (!$id || !$prov_id || !$city_id || !$full_name || !$tel || !$address) {
            api_error(__('admin.missing_params'));
        }
        $area_name = Areas::getAreaName([$prov_id, $city_id, $area_id]);
        $update_data = [
            'full_name' => $full_name,
            'tel' => $tel,
            'address' => $address,
            'prov' => $area_name[$prov_id] ?? '',
            'city' => $area_name[$city_id] ?? '',
            'area' => $area_name[$area_id] ?? ''
        ];
        $order = Order::query()->find($id);
        if (!$order) {
            api_error(__('admin.order_error'));
        }
        if (!OrderService::isUpdateAddress($order->toArray())) {
            api_error(__('admin.save_error'));
        }
        $res = Order::query()->where('id', $id)->update($update_data);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
    }

    /**
     * 后台支付订单支付
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function pay(Request $request)
    {
        $id = (int)$request->input('id');
        $note = $request->input('note');
        if (!$id || !$note) {
            api_error(__('admin.missing_params'));
        }
        $order = Order::query()->find($id);
        if (!$order) {
            api_error(__('admin.order_error'));
        }
        $res = OrderService::pay($order->toArray(), $this->user_data, OrderLog::USER_TYPE_ADMIN, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 后台取消订单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function cancel(Request $request)
    {
        $id = (int)$request->input('id');
        $note = $request->input('note');
        if (!$id || !$note) {
            api_error(__('admin.missing_params'));
        }
        $order = Order::query()->find($id);
        if (!$order) {
            api_error(__('admin.order_error'));
        }
        $res = OrderService::cancel($order->toArray(), $this->user_data, OrderLog::USER_TYPE_ADMIN, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 后台订单发货
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function delivery(Request $request)
    {
        $id = (int)$request->input('id');
        $order_goods_id = $request->input('order_goods_id');
        $company_id = (int)$request->input('company_id');
        $code = $request->input('code');
        $note = $request->input('note');
        if (!$id || !$order_goods_id || !$company_id) {
            api_error(__('admin.missing_params'));
        }
        if ($company_id != ExpressCompany::NOT_DELIVERY && !$code) {
            api_error(__('admin.delivery_code_error'));
        }
        $order = Order::query()->find($id);
        if (!$order) {
            api_error(__('admin.order_error'));
        }
        $param = [
            'order_goods_id' => $order_goods_id,
            'company_id' => $company_id,
            'code' => $code
        ];
        $res = OrderService::delivery($order->toArray(), $this->user_data, OrderLog::USER_TYPE_ADMIN, $note, $param);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 后台撤销订单发货
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function unDelivery(Request $request)
    {
        $id = (int)$request->input('id');
        $note = $request->input('note');
        if (!$id || !$note) {
            api_error(__('admin.missing_params'));
        }
        $order = Order::query()->find($id);
        if (!$order) {
            api_error(__('admin.order_error'));
        }
        $res = OrderService::unDelivery($order->toArray(), $this->user_data, OrderLog::USER_TYPE_ADMIN, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 后台确认订单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function confirm(Request $request)
    {
        $id = (int)$request->input('id');
        $note = $request->input('note');
        if (!$id || !$note) {
            api_error(__('admin.missing_params'));
        }
        $order = Order::query()->find($id);
        if (!$order) {
            api_error(__('admin.order_error'));
        }
        $res = OrderService::confirm($order->toArray(), $this->user_data, OrderLog::USER_TYPE_ADMIN, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 后台完成订单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function complete(Request $request)
    {
        $id = (int)$request->input('id');
        $note = $request->input('note');
        if (!$id || !$note) {
            api_error(__('admin.missing_params'));
        }
        $order = Order::query()->find($id);
        if (!$order) {
            api_error(__('admin.order_error'));
        }
        $res = OrderService::complete([$order->toArray()], $this->user_data, OrderLog::USER_TYPE_ADMIN, $note);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('admin.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 批量电子面单发货
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function batchDeliveryList(Request $request)
    {
        $ids = $this->checkBatchId();
        $where[] = ['status', Order::STATUS_PAID];
        $res_list = Order::query()->select('id', 'm_id', 'order_no', 'flag', 'full_name', 'tel', 'prov', 'city', 'area', 'address', 'status')
            ->where($where)
            ->whereIn('id', $ids)
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['status_text'] = Order::STATUS_DESC[$value['status']];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
        ];
        return $this->success($return);
    }

    /**
     * 批量电子面单发货提交
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function batchDeliverySubmit(Request $request)
    {
        $company_id = (int)$request->input('company_id');
        $address_id = (int)$request->input('address_id');
        $ids = $this->checkBatchId();
        if (!$company_id || !$address_id) {
            api_error(__('admin.missing_params'));
        }
        $express_company = ExpressCompany::query()->select('title', 'code', 'param')->where('id', $company_id)->first();
        if (!$express_company) {
            api_error(__('admin.express_company_error'));
        }
        $express_company = $express_company->toArray();
        $address = SellerAddress::query()->where(['id' => $address_id, 'seller_id' => 1])->first();
        if (!$address) {
            api_error(__('admin.delivery_address_error'));
        }
        $address = $address->toArray();
        $where[] = ['status', Order::STATUS_PAID];
        $res_list = Order::query()->select('id', 'm_id', 'seller_id', 'order_no', 'product_num', 'flag', 'full_name', 'tel', 'prov', 'city', 'area', 'address', 'status')
            ->where($where)
            ->whereIn('id', $ids)
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $delivery = new Delivery();
        $error_order_no = [];
        foreach ($res_list->toArray() as $value) {
            if (!OrderService::isDelivery($value)) {
                $error_order_no[] = $value['order_no'] . '【' . __('admin.order_status_error') . '】';
                continue;
            }
            $_delivery_data = $delivery->htmlOrder($express_company, $value, $address);
            if (is_array($_delivery_data)) {
                //电子面单获取成功
                OrderService::apiDelivery($value, $this->user_data, OrderLog::USER_TYPE_ADMIN, $express_company, $_delivery_data);
            } else {
                $error_order_no[] = $value['order_no'] . '【' . $_delivery_data . '】';
            }
        }
        if (!$error_order_no) {
            return $this->success();
        } else {
            $order_no_str = join(',', $error_order_no);
            api_error('1|订单' . $order_no_str . '发货失败');
        }
    }

    /**
     * 批量打印发货单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function printGoods(Request $request)
    {
        $ids = $this->checkBatchId();
        $where = [];
        $res_list = Order::select('id', 'order_no', 'full_name', 'tel', 'prov', 'city', 'area', 'address', 'note', 'created_at as create_at', 'sell_price_total', 'delivery_price_real', 'promotion_price', 'discount_price', 'subtotal')
            ->where($where)
            ->whereIn('id', $ids)
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $order_ids = array_column($res_list->toArray(), 'id');
        $order_goods = OrderGoods::getGoodsForOrderId($order_ids, true);
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['order_goods'] = $order_goods[$value['id']] ?? [];
            $data_list[] = $_item;
        }
        return $this->success($data_list);
    }

    /**
     * 批量打印快递单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function printDelivery(Request $request)
    {
        $ids = $this->checkBatchId();
        $where = [];
        $res_list = OrderDeliveryTemplate::query()->select('content')
            ->where($where)
            ->whereIn('order_id', $ids)
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        return $this->success($res_list);
    }

}
