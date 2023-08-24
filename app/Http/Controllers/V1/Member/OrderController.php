<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/17
 * Time: 4:26 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Http\Controllers\V1\BaseController;
use App\Models\Goods\Comment;
use App\Models\Member\Member;
use App\Models\Order\DeliveryTraces;
use App\Models\Order\Order;
use App\Models\Order\OrderDelivery;
use App\Models\Order\OrderGoods;
use App\Models\Seller\Seller;
use App\Services\OrderService;
use App\Services\RefundService;
use Illuminate\Http\Request;

class OrderController extends BaseController
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
        $status = $request->post('status');
        $is_comment = (int)$request->post('is_comment');
        $where = [
            'm_id' => $this->member_data['id'],
            'is_delete' => Order::IS_DELETE_NO
        ];
        if (isset(Order::STATUS_DESC[$status])) {
            if ($status == Order::STATUS_CANCEL) {
                $where_in = [Order::STATUS_CANCEL, Order::STATUS_SYSTEM_CANCEL];
            } else {
                $where['status'] = $status;
            }
        }
        if ($is_comment) {
            $where_in = [Order::STATUS_DONE, Order::STATUS_COMPLETE];
        }
        $query = Order::query()->select('id', 'order_no', 'seller_id', 'product_num', 'subtotal', 'status', 'delivery_price_real', 'comment_at')
            ->where($where);
        if (isset($where_in)) {
            $query->whereIn('status', $where_in);
        }
        if ($is_comment) {
            $query->whereNull('comment_at');
        }
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $res_list = $res_list->toArray();
        $order_ids = array_column($res_list, 'id');
        $seller_ids = array_column($res_list, 'seller_id');
        //获取商品信息
        $order_goods = OrderGoods::getGoodsForOrderId(array_unique($order_ids), true);
        //获取商家信息
        $seller_res = Seller::query()->select('id', 'title', 'image')->whereIn('id', $seller_ids)->get();
        if ($seller_res->isEmpty()) {
            api_error(__('api.seller_error'));
        }
        $seller_res = array_column($seller_res->toArray(), null, 'id');
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = [
                'order_no' => $value['order_no'],
                'seller' => $seller_res[$value['seller_id']] ?? [],
                'goods' => $order_goods[$value['id']] ?? [],
                'product_num' => $value['product_num'],
                'subtotal' => $value['subtotal'],
                'delivery_price_real' => $value['delivery_price_real'],
                'status' => $value['status'],
                'status_text' => Order::STATUS_MEMBER_DESC[$value['status']],
                'button' => OrderService::orderButton($value)
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
     * 详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $order_no = $request->post('order_no');
        if (!$order_no) {
            api_error(__('api.missing_params'));
        }
        $order_info = Order::getInfo($order_no, $this->member_data['id']);
        //查询订单商品
        $order_goods_res = OrderGoods::query()->select('id', 'goods_id', 'goods_title', 'image', 'sell_price', 'buy_qty', 'spec_value', 'refund')->where('order_id', $order_info['id'])->get();
        if ($order_goods_res->isEmpty()) {
            api_error(__('api.order_goods_error'));
        }
        $order_goods = [];
        foreach ($order_goods_res->toArray() as $value) {
            $_item = $value;
            $_item['refund_text'] = OrderGoods::REFUND_DESC[$value['refund']];
            $_item['refund_button'] = OrderService::isRefund($order_info, $value) ? 1 : 0;
            $order_goods[] = $_item;
        }
        $seller = Seller::query()->select('id', 'title', 'image')->where('id', $order_info['seller_id'])->first();
        $order = [
            'full_name' => $order_info['full_name'],
            'tel' => $order_info['tel'],
            'address' => $order_info['prov'] . $order_info['city'] . $order_info['area'] . $order_info['address'],
            'status' => $order_info['status'],
            'status_text' => Order::STATUS_MEMBER_DESC[$order_info['status']],
            'order_no' => $order_info['order_no'],
            'sell_price_total' => $order_info['sell_price_total'],
            'delivery_price_real' => $order_info['delivery_price_real'],
            'discount_price' => $order_info['discount_price'],
            'promotion_price' => $order_info['promotion_price'],
            'subtotal' => $order_info['subtotal'],
            'note' => $order_info['note'],
            'created_at' => $order_info['created_at'],
            'pay_at' => $order_info['pay_at'],
            'send_at' => $order_info['send_at'],
            'done_at' => $order_info['done_at'],
        ];
        $return = [
            'order' => $order,
            'goods' => $order_goods,
            'seller' => $seller,
            'button' => OrderService::orderButton($order_info)
        ];
        return $this->success($return);
    }

    /**
     * 取消订单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function cancel(Request $request)
    {
        $order_no = $request->post('order_no');
        if (!$order_no) {
            api_error(__('api.missing_params'));
        }
        $order_info = Order::getInfo($order_no, $this->member_data['id']);
        $res = OrderService::cancel($order_info, $this->member_data);
        if ($res === true) {
            return $this->success();
        } elseif ($res === false) {
            api_error(__('api.fail'));
        } else {
            api_error($res);
        }
    }

    /**
     * 确认订单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function confirm(Request $request)
    {
        $order_no = $request->post('order_no');
        if (!$order_no) {
            api_error(__('api.missing_params'));
        }
        Member::checkPayPassword($this->member_data['id']);//判断支付密码
        $order_info = Order::getInfo($order_no, $this->member_data['id']);
        $res = OrderService::confirm($order_info, $this->member_data);
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
    public function delivery(Request $request)
    {
        $order_no = $request->post('order_no');
        if (!$order_no) {
            api_error(__('api.missing_params'));
        }
        $order_info = Order::getInfo($order_no, $this->member_data['id']);
        $delivery_traces = [];
        $delivery_res = OrderDelivery::query()->select('order_goods_id', 'company_code', 'company_name', 'code')->where('order_id', $order_info['id'])->get();
        if ($delivery_res->isEmpty()) {
            return $this->success($delivery_traces);
        }
        $traces_query = DeliveryTraces::query()->select('company_code', 'code', 'accept_time', 'info');
        foreach ($delivery_res as $value) {
            $_item = [
                'company_name' => $value['company_name'],
                'code' => $value['code'],
                'traces' => [],
                'goods' => []
            ];
            if ($value['order_goods_id']) {
                $order_goods_id = json_decode($value['order_goods_id'], true);
                $_item['goods'] = OrderGoods::getGoodsForId($order_goods_id);
            }
            $delivery_traces[$value['company_code'] . $value['code']] = $_item;
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
     * 获取评论商品信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function comment(Request $request)
    {
        $order_no = $request->post('order_no');
        if (!$order_no) {
            api_error(__('api.missing_params'));
        }
        $order_info = Order::getInfo($order_no, $this->member_data['id']);
        if (!OrderService::isComment($order_info)) {
            api_error(__('api.order_status_error'));
        }
        //查询子商品
        $order_goods = OrderGoods::getGoodsForOrderId([$order_info['id']]);
        if (!$order_goods) {
            api_error(__('api.content_is_empty'));
        }
        return $this->success($order_goods);
    }

    /**
     * 提交商品评论信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function commentPut(Request $request)
    {
        $order_no = $request->post('order_no');
        $content = $request->post('content');
        if (!$order_no || !$content) {
            api_error(__('api.missing_params'));
        }
        $content = json_decode($content, true);
        $order_info = Order::getInfo($order_no, $this->member_data['id']);
        if (!OrderService::isComment($order_info)) {
            api_error(__('api.order_status_error'));
        }
        //查询子商品
        $order_goods = OrderGoods::getGoodsForOrderId([$order_info['id']]);
        $order_goods = array_column($order_goods, null, 'id');
        if (count($content) != count($order_goods)) {
            api_error(__('api.missing_params'));
        }
        $comment = [];
        foreach ($content as $value) {
            if (isset($order_goods[$value['id']])) {
                $level = isset($value['level']) ? (int)$value['level'] : 5;
                if ($level < 1 || $level > 5) {
                    api_error(__('api.evaluation_level_error'));
                }
                $_item = [
                    'm_id' => $this->member_data['id'],
                    'goods_id' => $order_goods[$value['id']]['goods_id'],
                    'sku_id' => $order_goods[$value['id']]['sku_id'],
                    'spec_value' => $order_goods[$value['id']]['spec_value'],
                    'level' => $level,
                    'content' => $value['content'] ?? '好评',
                    'image' => isset($value['image']) ? explode(',', $value['image']) : [],
                    'is_image' => Comment::IS_IMAGE_FALSE,
                    'video' => [],
                    'is_video' => Comment::IS_VIDEO_FALSE
                ];
                if ($_item['image']) $_item['is_image'] = Comment::IS_IMAGE_TRUE;
                if ($_item['video']) $_item['is_video'] = Comment::IS_VIDEO_TRUE;
                $comment[] = $_item;
            }
        }
        if (!$comment) {
            api_error(__('api.missing_params'));
        }
        $res = OrderService::commentPut($order_info, $comment);
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
        $order_no = $request->post('order_no');
        if (!$order_no) {
            api_error(__('api.missing_params'));
        }
        $order_info = Order::getInfo($order_no, $this->member_data['id']);
        if (!OrderService::isUserDelete($order_info)) {
            api_error(__('api.order_status_error'));
        }
        $res = Order::query()->where(['m_id' => $this->member_data['id'], 'order_no' => $order_no])->update(['is_delete' => Order::IS_DELETE_YES]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

}
