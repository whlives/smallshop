<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 3:39 PM
 */

namespace App\Http\Controllers\V1;

use App\Models\Order\Cart;
use App\Services\GoodsService;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    public int $m_id;

    public function __construct()
    {
        $this->m_id = $this->getUserId();
    }

    /**
     * 我的购物车
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        $cart = Cart::select('goods_id', 'buy_qty', 'sku_id')->where('m_id', $this->m_id)->orderBy('updated_at', 'desc')->get();
        if ($cart->isEmpty()) {
            api_error(__('api.cart_goods_not_exists'));
        }
        $goods_service = new GoodsService($this->m_id);
        $goods = $goods_service->formatSellerGoods($cart->toArray(), Cart::TYPE_CART);
        $goods['valid_goods'] = $goods_service->eliminateUselessParams($goods['valid_goods']);//剔除沉余参数
        $goods['invalid_goods'] = $goods_service->eliminateUselessParams($goods['invalid_goods']);//剔除沉余参数
        return $this->success($goods);
    }

    /**
     * 添加到购物车
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function add(Request $request)
    {
        $sku_id = (int)$request->post('sku_id');
        $buy_qty = (int)$request->post('buy_qty', 1);
        if (!$sku_id || !$buy_qty) {
            api_error(__('api.missing_params'));
        }
        $cart = Cart::where(['m_id' => $this->m_id, 'sku_id' => $sku_id])->first();
        if (isset($cart['buy_qty'])) $buy_qty = $cart['buy_qty'] + $buy_qty;
        //查询商品是否正常
        [$goods_sku, $goods] = GoodsService::checkCartGoods($sku_id, $buy_qty);
        if ($cart) {
            //已经存在直接修改数量
            $res = Cart::where('id', $cart['id'])->update(['buy_qty' => $buy_qty]);
        } else {
            $cart_data = [
                'm_id' => $this->m_id,
                'seller_id' => $goods['seller_id'],
                'goods_id' => $goods_sku['goods_id'],
                'sku_id' => $sku_id,
                'buy_qty' => $buy_qty,
            ];
            $res = Cart::create($cart_data);
        }
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 修改购物车商品数量
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function edit(Request $request)
    {
        $sku_id = (int)$request->post('sku_id');
        $buy_qty = (int)$request->post('buy_qty', 1);
        if (!$sku_id || !$buy_qty) {
            api_error(__('api.missing_params'));
        }
        $cart = Cart::where(['m_id' => $this->m_id, 'sku_id' => $sku_id])->first();
        if (!$cart) {
            api_error(__('api.cart_goods_error'));
        }
        //查询商品是否正常
        [$goods_sku, $goods] = GoodsService::checkCartGoods($sku_id, $buy_qty);
        //已经存在直接修改数量
        $res = Cart::where('id', $cart['id'])->update(['buy_qty' => $buy_qty]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 删除购物车商品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function delete(Request $request)
    {
        $sku_id = $request->post('sku_id');
        if (!$sku_id) {
            api_error(__('api.missing_params'));
        }
        $sku_id = format_number($sku_id, true);
        if (!$sku_id) {
            api_error(__('api.missing_params'));
        }
        $res = Cart::where('m_id', $this->m_id)->whereIn('sku_id', $sku_id)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 清空购物车商品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function clear(Request $request)
    {
        $res = Cart::where('m_id', $this->m_id)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

}