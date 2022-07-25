<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/6
 * Time: 3:57 PM
 */

namespace App\Http\Controllers\V1;

use App\Jobs\OrderSubmitAfter;
use App\Models\Order\Cart;
use App\Models\Order\Order;
use App\Models\System\Payment;
use App\Services\GoodsService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends BaseController
{
    public int $m_id;
    public GoodsService $goods_service;

    public function __construct()
    {
        $this->m_id = $this->getUserId();
        $this->goods_service = new GoodsService($this->m_id);
    }

    /**
     * 计算购物车价格
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function getPrice(Request $request)
    {
        $type = (int)$request->post('type');
        $sku_id = $request->post('sku_id');
        if (!$type || !$sku_id) {
            api_error(__('api.missing_params'));
        }
        [$cart, $type] = $this->goods_service->formatCart();
        $seller_goods = $this->goods_service->formatSellerGoods($cart, $type);
        $seller_goods = $this->goods_service->getOrderPrice($seller_goods, [], []);//获取商品信息
        $price = $this->goods_service->sumOrderPrice($seller_goods);//组装价格信息
        return $this->success($price);
    }

    /**
     * 确认订单价格计算
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function confirmPrice(Request $request)
    {
        //验证基础信息
        [$cart, $type] = $this->goods_service->formatCart();
        //验证活动类型
        if ($type == Cart::TYPE_NOW) {
            $promo_data = $this->goods_service->checkRule($cart[0]);
        }
        $address = $this->goods_service->checkAddress(true);
        //格式化优惠券配送备注发票等信息
        [$coupons, $delivery, $note, $invoice] = GoodsService::formatParams();
        $seller_goods = $this->goods_service->formatSellerGoods($cart, $type);
        $seller_goods = $this->goods_service->getOrderPrice($seller_goods, $address, $coupons);//获取商品信息
        $price = $this->goods_service->sumOrderPrice($seller_goods);//组装价格信息
        $return = [
            'seller_goods' => $this->goods_service->filterPrice($seller_goods),
            'order_price' => $price
        ];
        return $this->success($return);
    }

    /**
     * 确认订单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request)
    {
        //验证基础信息
        [$cart, $type] = $this->goods_service->formatCart();
        //验证活动类型
        if ($type == Cart::TYPE_NOW) {
            $promo_data = $this->goods_service->checkRule($cart[0]);
        }
        $address = $this->goods_service->checkAddress();
        $seller_goods = $this->goods_service->formatSellerGoods($cart, $type);
        $seller_goods = $this->goods_service->getConfirm($seller_goods, $address);//确认订单信息
        $order_price = $this->goods_service->sumOrderPrice($seller_goods);
        $return = [
            'address' => $address ?: new \stdClass(),
            'seller_goods' => $seller_goods,
            'order_price' => $order_price,
            'payment' => Payment::getPayment()
        ];
        return $this->success($return);
    }

    /**
     * 提交订单
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function submit(Request $request)
    {
        //验证基础信息
        [$cart, $type] = $this->goods_service->formatCart();
        $address = $this->goods_service->checkAddress(true);
        //验证商品信息（包含秒杀、团购等）
        if ($type == Cart::TYPE_NOW) {
            $promo_data = $this->goods_service->checkRule($cart[0], true);
        }
        //格式化优惠券配送备注发票等信息
        [$coupons, $delivery, $note, $invoice] = GoodsService::formatParams();
        $seller_goods = $this->goods_service->formatSellerGoods($cart, $type);
        $seller_goods = $this->goods_service->getOrderPrice($seller_goods, $address, $coupons);//提交订单时的信息
        //判断是否存在不能送达的商品
        foreach ($seller_goods as $value) {
            if ($value['delivery']['sku_ids']) {
                //活动时如果存在不能送达的商品，还原库存
                $this->goods_service->activityStockIncr($value['goods']);
                api_error(__('api.goods_can_not_delivery'));
            }
        }
        $invoice = $this->goods_service->checkInvoice($seller_goods, $invoice);
        $param = [
            'address' => $address,
            'delivery' => $delivery,
            'note' => $note,
            'invoice' => $invoice
        ];
        [$order_info, $order_no_arr, $subtotal] = OrderService::formatOrder($this->m_id, $seller_goods, $param);
        $res = Order::submitOrder($order_info);
        if ($res) {
            //减少商品库存
            OrderService::stockDecr($cart);
            //删除购物车商品
            if ($type == Cart::TYPE_CART) Cart::delCart($this->m_id, $cart);
            if ($type == Cart::TYPE_NOW) {
                //立即购买的后续订单处理
                OrderSubmitAfter::dispatch($order_no_arr, $promo_data ?? []);
            }
            $order_no_arr = join(',', $order_no_arr);
            $return = [
                'order_no' => trim($order_no_arr, ','),
                'subtotal' => format_price($subtotal)
            ];
            return $this->success($return);
        } else {
            api_error(__('api.order_submit_fail'));
        }
    }


}