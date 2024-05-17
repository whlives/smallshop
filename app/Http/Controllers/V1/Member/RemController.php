<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:41 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Exceptions\ApiError;
use App\Http\Controllers\V1\BaseController;
use App\Models\Financial\BalanceDetail;
use App\Models\Member\Member;
use App\Models\Order\Order;
use App\Models\Order\OrderDelivery;
use App\Models\Order\OrderGoods;
use App\Models\Seller\Seller;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemController extends BaseController
{
    public int $m_id;

    public function __construct()
    {
        $this->m_id = $this->getUserId();
    }

    /**
     * 我的推荐
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset, $page] = get_page_params();
        $where = [
            'parent_id' => $this->m_id
        ];
        $query = Member::select('id', 'username', 'nickname', 'headimg')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        $rem_list = [];
        if (!$res_list->isEmpty()) {
            $m_ids = array_column($res_list->toArray(), 'id');
            $count_num_data = Member::select(DB::raw("parent_id,count(parent_id) as count_num"))
                ->whereIn('parent_id', $m_ids)
                ->groupBy('parent_id')
                ->get();
            if (!$count_num_data->isEmpty()) {
                $count_num_data = array_column($count_num_data->toArray(), 'count_num', 'parent_id');
            }
            foreach ($res_list as $value) {
                $_item = $value;
                $_item['username'] = mb_substr($value['username'], 0, 3) . '****' . mb_substr($value['username'], -4, 4);
                $_item['rem_num'] = $count_num_data[$value['id']] ?? 0;
                $rem_list[] = $_item;
            }
        }
        $amount_sum = 0;
        if ($page == 1) {
            $amount_sum = BalanceDetail::where(['m_id' => $this->m_id, 'event' => BalanceDetail::EVENT_RECOMMEND_ORDER])->sum('amount');
        }
        $return = [
            'total' => $total,
            'amount_sum' => $amount_sum,
            'rem_list' => $rem_list
        ];
        return $this->success($return);
    }

    /**
     * 我的推荐收益明细
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function balanceDetail(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $where = [
            'm_id' => $this->m_id,
            'event' => BalanceDetail::EVENT_RECOMMEND_ORDER,
        ];
        $query = BalanceDetail::select('detail_no', 'note', 'created_at', 'amount')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $return = [
            'lists' => $res_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 列表
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function order(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $level_two_m_id = (int)$request->post('level_two_m_id');
        $type = $request->post('type');
        $where = [
            'is_delete' => Order::IS_DELETE_NO
        ];
        if (isset(Order::STATUS_DESC[$type])) {
            if ($type == Order::STATUS_CANCEL) {
                $where_in = [Order::STATUS_CANCEL, Order::STATUS_SYSTEM_CANCEL];
            } else {
                $where['status'] = $type;
            }
        }
        //下级人员的订单
        if ($level_two_m_id) {
            $where['level_one_m_id'] = $this->m_id;
            $where['level_two_m_id'] = $level_two_m_id;
        }
        $query = Order::select('id', 'order_no', 'm_id', 'seller_id', 'product_num', 'subtotal', 'status', 'delivery_price_real', 'comment_at')
            ->where($where);
        if (isset($where_in)) {
            $query->whereIn('status', $where_in);
        }
        //所有推荐订单
        if (!$level_two_m_id) {
            $query->where(function ($query1) {
                $query1->orWhere(['level_one_m_id' => $this->m_id, 'level_two_m_id' => $this->m_id]);
            });
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
        $m_ids = array_column($res_list, 'm_id');
        $order_ids = array_column($res_list, 'id');
        //获取商品信息
        $order_goods = OrderGoods::getGoodsForOrderId(array_unique($order_ids), true);
        $member_res = Member::select('id', 'nickname', 'headimg')->whereIn('id', $m_ids)->get();
        if ($member_res->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $member_res = array_column($member_res->toArray(), null, 'id');
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = [
                'order_no' => $value['order_no'],
                'member' => $member_res[$value['m_id']] ?? [],
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
     * 订单详情
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function orderDetail(Request $request)
    {
        $order_no = $request->post('order_no');
        if (!$order_no) {
            api_error(__('api.missing_params'));
        }
        $order_info = Order::where('order_no', $order_no)->first();
        if ($order_info['level_one_m_id'] != $this->m_id && $order_info['level_two_m_id'] != $this->m_id) {
            api_error(__('api.missing_params'));
        }
        $order_info = $order_info->toArray();
        //查询订单商品
        $order_goods_res = OrderGoods::select('id', 'goods_id', 'goods_title', 'image', 'sell_price', 'buy_qty', 'spec_value', 'refund')->where('order_id', $order_info['id'])->get();
        if ($order_goods_res->isEmpty()) {
            api_error(__('api.order_goods_error'));
        }
        $order_goods = [];
        foreach ($order_goods_res->toArray() as $value) {
            $_item = $value;
            $_item['refund_text'] = OrderGoods::REFUND_DESC[$value['refund']];
            $order_goods[] = $_item;
        }
        $seller = Seller::select('id', 'title', 'image')->where('id', $order_info['seller_id'])->first();
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
            'button' => get_platform() == 'wechat' ? [] : new \stdClass(),
            'delivery' => OrderDelivery::deliveryInfo($order_info),
            'member' => Member::select('nickname', 'headimg')->where('id', $order_info['m_id'])->first()
        ];
        return $this->success($return);
    }
}
