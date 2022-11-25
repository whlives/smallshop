<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/20
 * Time: 21:14 PM
 */

namespace App\Services;

use App\Models\Goods\Goods;
use App\Models\Goods\GoodsObject;
use App\Models\Goods\GoodsSku;
use App\Models\Market\PromoGroup;
use App\Models\Market\PromoSeckill;
use App\Models\Market\Promotion;
use App\Models\Member\Address;
use App\Models\Order\Cart;
use App\Models\Order\OrderInvoice;
use App\Models\Seller\Seller;

class GoodsService
{
    public array $user_group = [];
    public int $m_id;

    public function __construct($m_id)
    {
        $this->user_group = get_user_group();
        $this->m_id = $m_id;
    }

    /**
     * 解析商品规格
     * @param string $value
     * @return string
     */
    public static function formatSpecValue(string $value)
    {
        $spec_str = '';
        $spec_value = json_decode($value, true);
        if (!$spec_value) return $spec_str;
        foreach ($spec_value as $value) {
            $spec_str .= $value['name'] . ':' . $value['alias'] . '+';
        }
        return trim($spec_str, '+');
    }

    /**
     * 判断购物车商品
     * @param int $sku_id
     * @param int $buy_qty
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public static function checkCartGoods(int $sku_id, int $buy_qty)
    {
        //查询商品是否正常
        $goods_sku = GoodsSku::select('goods_id', 'status', 'min_buy', 'max_buy', 'stock')->where('id', $sku_id)->first();
        if (!$goods_sku) {
            api_error(__('api.goods_sku_error'));
        } elseif ($goods_sku['status'] != GoodsSku::STATUS_ON) {
            api_error(__('api.goods_sku_status_error'));
        } elseif ($goods_sku['min_buy'] > $buy_qty) {
            api_error(__('api.goods_min_buy_qty_error') . $goods_sku['min_buy'] . '件');
        } elseif ($goods_sku['max_buy'] && $goods_sku['max_buy'] < $buy_qty) {
            api_error(__('api.goods_max_buy_qty_error') . $goods_sku['max_buy'] . '件');
        } elseif ($goods_sku['stock'] < $buy_qty) {
            api_error(__('api.goods_stock_no_enough') . '最多能订购' . $goods_sku['stock'] . '件');
        }
        $goods = Goods::select('id', 'seller_id', 'shelves_status', 'type', 'promo_type')->where('id', $goods_sku['goods_id'])->first();
        if (!$goods) {
            api_error(__('api.goods_error'));
        } elseif ($goods['shelves_status'] != Goods::SHELVES_STATUS_ON) {
            api_error(__('api.goods_shelves_status_error'));
        } elseif ($goods['type'] != Goods::TYPE_GOODS || $goods['promo_type'] != Goods::PROMO_TYPE_DEFAULT) {
            api_error(__('api.goods_not_join_cart'));
        }
        return [$goods_sku, $goods];
    }

    /**
     * 获取会员折扣价格
     * @param array $goods 商品信息
     * @param array $group_data 用户分组
     * @return array
     */
    public static function getVipPrice(array $goods, array $group_data)
    {
        $goods['show_price'] = $goods['sell_price'];
        $goods['line_price'] = $goods['market_price'];
        $pct = $group_data['pct'] ?? '';
        if ($pct) {
            $goods['show_price'] = format_price($goods['sell_price'] * $pct);
            $goods['line_price'] = $goods['market_price'];
        }
        unset($goods['sell_price'], $goods['market_price']);
        return $goods;
    }

    /**
     * 格式为购物车格式
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public function formatCart()
    {
        $type = (int)request()->post('type');
        $goods_id = (int)request()->post('goods_id');
        $sku_id = request()->post('sku_id');
        $buy_qty = (int)request()->post('buy_qty');
        if (!$type || !$sku_id || ($type == Cart::TYPE_NOW && !$goods_id)) {
            api_error(__('api.missing_params'));
        }
        $cart = [];
        if ($type == Cart::TYPE_CART) {
            //购物车下单
            $sku_id = format_number($sku_id, true);
            if (!$sku_id) {
                api_error(__('api.missing_params'));
            }
            $cart = Cart::select('goods_id', 'sku_id', 'buy_qty')->where('m_id', $this->m_id)->whereIn('sku_id', $sku_id)->orderBy('updated_at', 'desc')->get();
            if ($cart->isEmpty()) {
                api_error(__('api.cart_goods_error'));
            }
            $cart = $cart->toArray();
        } elseif ($type == Cart::TYPE_NOW) {
            //直接购买
            $sku_id = (int)$sku_id;
            if ($buy_qty < 1) {
                api_error(__('api.buy_qty_error'));
            }
            $cart[] = ['sku_id' => $sku_id, 'buy_qty' => $buy_qty, 'goods_id' => $goods_id];
        }
        if (!$cart) {
            api_error(__('api.goods_error'));
        }
        return [$cart, $type];
    }

    /**
     * 验证收货地址
     * @param bool $is_must 是否必须地址id
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public function checkAddress(bool $is_must = false)
    {
        $address_id = (int)request()->post('address_id');
        if ($is_must && !$address_id) {
            api_error(__('api.address_not_exists'));
        }
        if ($address_id) {
            $address = Address::where(['m_id' => $this->m_id, 'id' => $address_id])->first();
            if (!$address) {
                api_error(__('api.address_not_exists'));
            }
        } else {
            //没有收货地址查询默认地址
            $address = Address::where(['m_id' => $this->m_id])->orderBy('default', 'desc')->orderBy('id', 'desc')->first();
        }
        $return = [];
        if ($address) {
            $return = [
                'id' => $address['id'],
                'full_name' => $address['full_name'],
                'tel' => $address['tel'],
                'prov_name' => $address['prov_name'],
                'city_name' => $address['city_name'],
                'area_name' => $address['area_name'],
                'address' => $address['address']
            ];
        }
        if ($is_must && !$address) {
            //下单的时候地址必须
            api_error(__('api.address_not_exists'));
        }
        return $return;
    }

    /**
     * 按商家格式化商品信息
     * @param array $cart
     * @param int $type
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public function formatSellerGoods(array $cart, int $type)
    {
        $sku_ids = array_column($cart, 'sku_id');
        $format_goods_sku = self::formatGoodsSku($sku_ids);
        $seller_ids = array_column($format_goods_sku, 'seller_id');
        //获取商家信息
        $res_seller = Seller::select('id', 'title', 'image', 'status', 'invoice')->whereIn('id', array_unique($seller_ids))->get();
        if ($res_seller->isEmpty()) {
            api_error(__('api.seller_error'));
        }
        $res_seller = array_column($res_seller->toArray(), null, 'id');
        $new_goods_sku = [];
        //将商品按加入购物车的时间排序
        foreach ($cart as $value) {
            $new_goods_sku[$value['sku_id']] = array_merge($format_goods_sku[$value['sku_id']], ['buy_qty' => $value['buy_qty']]);
        }
        //组装商品和商家信息，并区分失效商品
        $valid_goods = $invalid_goods = [];
        foreach ($new_goods_sku as $_sku) {
            $seller_id = $_sku['seller_id'];
            $_seller = $res_seller[$seller_id] ?? [];
            if (!$_seller) continue;
            //判断商品和商家的状态，活动商品和卡券类的只能单独下单
            if ($_seller['status'] != Seller::STATUS_ON || $_sku['status'] != GoodsSku::STATUS_ON || $_sku['shelves_status'] != Goods::SHELVES_STATUS_ON || (($_sku['type'] != Goods::TYPE_GOODS || $_sku['promo_type'] != Goods::PROMO_TYPE_DEFAULT) && $type == Cart::TYPE_CART)) {
                $invalid_goods[$seller_id]['seller'] = $_seller;
                $invalid_goods[$seller_id]['goods'][] = $_sku;
            } else {
                //以下的判断主要是提供给购物车提示使用
                //判断库存
                if ($_sku['min_buy'] > $_sku['buy_qty']) {
                    $_sku['error_tip'] = __('api.tip_goods_min_buy_qty_error') . $_sku['min_buy'];
                }
                if ($_sku['max_buy'] && $_sku['max_buy'] < $_sku['buy_qty']) {
                    $_sku['error_tip'] = __('api.tip_goods_max_buy_qty_error') . $_sku['max_buy'];
                }
                if ($_sku['buy_qty'] > $_sku['stock']) {
                    $_sku['error_tip'] = __('api.tip_goods_stock_no_enough');
                }
                $valid_goods[$seller_id]['seller'] = $_seller;
                $valid_goods[$seller_id]['goods'][] = $_sku;
            }
        }
        return [
            'valid_goods' => array_values($valid_goods),
            'invalid_goods' => array_values($invalid_goods)
        ];
    }

    /**
     * 格式化商品信息
     * @param array $sku_ids
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public function formatGoodsSku(array $sku_ids)
    {
        //获取子商品信息
        $res_sku = GoodsSku::select('id as sku_id', 'goods_id', 'image', 'sku_code', 'spec_value', 'stock', 'sell_price', 'market_price', 'point', 'weight', 'min_buy', 'max_buy', 'status')->whereIn('id', $sku_ids)->get();
        if ($res_sku->isEmpty()) {
            api_error(__('api.goods_sku_error'));
        }
        $res_sku = $res_sku->toArray();
        //获取主商品信息
        $goods_ids = array_column($res_sku, 'goods_id');
        $res_goods = Goods::select('id as goods_id', 'title', 'seller_id', 'brand_id', 'category_id', 'delivery_id', 'shelves_status', 'type', 'promo_type', 'level_one_pct', 'level_two_pct')->whereIn('id', array_unique($goods_ids))->get();
        if ($res_goods->isEmpty()) {
            api_error(__('api.goods_error'));
        }
        $res_goods = array_column($res_goods->toArray(), null, 'goods_id');
        $sku = [];
        foreach ($res_sku as $_sku) {
            $_goods = $res_goods[$_sku['goods_id']] ?? [];
            //获取活动价格和会员折扣
            $_sku = self::getVipPrice($_sku, $this->user_group);
            $_item = array_merge($_sku, $_goods);
            $_item['spec_value'] = self::formatSpecValue($_item['spec_value']);
            $_item['promotion_price'] = 0;//优惠金额(后面拆分优惠金额的时候使用)
            $sku[$_sku['sku_id']] = $_item;
        }
        return $sku;
    }

    /**
     * 计算选中商品的金额、优惠、运费
     * @param array $goods
     * @param array $address
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public function getConfirm(array $goods, array $address)
    {
        if (!$goods['valid_goods'] || $goods['invalid_goods']) {
            api_error(__('api.goods_is_update'));
        }
        $format_goods = $goods['valid_goods'];
        //过滤库存不足、数量错误的商品
        self::checkBuyQtyGoods($format_goods);
        $format_goods = self::sumSellerGoodsPrice($format_goods);
        //获取商品优惠券
        $format_goods = self::getCoupons($format_goods);
        //计算商品优惠信息
        $format_goods = self::promotionPrice($format_goods);
        //获取商品邮费
        $format_goods = self::getDeliveryPrice($format_goods, $address);
        //剔除沉余参数
        return self::eliminateUselessParams($format_goods);
    }

    /**
     * 计算选中商品的金额含优惠、运费等
     * @param array $goods
     * @param array $address
     * @param array $coupons
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public function getOrderPrice(array $goods, array $address, array $coupons)
    {
        if (!$goods['valid_goods'] || $goods['invalid_goods']) {
            return __('api.goods_is_update');
        }
        $format_goods = $goods['valid_goods'];
        //过滤库存不足、数量错误的商品
        self::checkBuyQtyGoods($format_goods);
        $format_goods = self::sumSellerGoodsPrice($format_goods);
        //计算优惠券优惠金额
        if ($coupons) {
            $format_goods = self::checkCoupons($format_goods, $coupons);
        }
        //计算促销优惠信息
        $format_goods = self::promotionPrice($format_goods);
        //获取商品邮费
        return self::getDeliveryPrice($format_goods, $address);
    }

    /**
     * 验证商品购买数量
     * @param array $format_goods
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public function checkBuyQtyGoods(array $format_goods)
    {
        //验证库存和购买数量
        foreach ($format_goods as $seller_goods) {
            foreach ($seller_goods['goods'] as $goods) {
                //判断库存
                if ($goods['min_buy'] > $goods['buy_qty']) {
                    api_error(__('api.goods_min_buy_qty_error') . $goods['min_buy'] . '件，商品：' . $goods['title']);
                }
                if ($goods['max_buy'] && $goods['max_buy'] < $goods['buy_qty']) {
                    api_error(__('api.goods_max_buy_qty_error') . $goods['max_buy'] . '件，商品：' . $goods['title']);
                }
                if ($goods['buy_qty'] > $goods['stock']) {
                    api_error(__('api.goods_stock_no_enough') . $goods['title']);
                }
            }
        }
    }

    /**
     * 计算商品的价格件数
     * @param array $format_goods
     * @return array
     */
    public function sumSellerGoodsPrice(array $format_goods)
    {
        foreach ($format_goods as $_key => $seller_goods) {
            $price = self::sumGoodsPrice($seller_goods['goods']);
            $all_buy_qty = self::sumGoodsBuyQty($seller_goods['goods']);
            $format_goods[$_key]['price'] = $price;
            $format_goods[$_key]['all_buy_qty'] = $all_buy_qty;
        }
        return $format_goods;
    }

    /**
     * 计算商品的价格
     * @param array $seller_goods
     * @return array
     */
    public function sumGoodsPrice(array $seller_goods)
    {
        $all_sell_price = $all_market_price = $all_weight = $all_point = $all_promotion_price = 0;
        foreach ($seller_goods as $goods) {
            $all_sell_price += ($goods['show_price'] * $goods['buy_qty']);
            $all_market_price += ($goods['line_price'] * $goods['buy_qty']);
            $all_weight += ($goods['weight'] * $goods['buy_qty']);
            $all_point += ($goods['point'] * $goods['buy_qty']);
            $all_promotion_price += $goods['promotion_price'];
        }
        return [
            'sell_price' => format_price($all_sell_price),
            'market_price' => format_price($all_market_price),
            'weight' => format_price($all_weight),
            'point' => format_price($all_point),
            'promotion_price' => $all_promotion_price,//优惠金额
            'subtotal' => format_price($all_sell_price - $all_promotion_price)//需要支付金额
        ];
    }

    /**
     * 计算商品总件数
     * @param array $seller_goods
     * @return int|mixed
     */
    public function sumGoodsBuyQty(array $seller_goods)
    {
        $all_buy_qty = 0;
        foreach ($seller_goods as $goods) {
            $all_buy_qty += $goods['buy_qty'];
        }
        return $all_buy_qty;
    }

    /**
     * 获取优惠券
     * @param array $format_goods
     * @return array
     */
    public function getCoupons(array $format_goods)
    {
        foreach ($format_goods as $_key => $seller_goods) {
            $coupons = CouponsService::getCoupons($this->m_id, $seller_goods['seller']['id'], $seller_goods['goods']);
            $format_goods[$_key]['coupons'] = $coupons;
        }
        return $format_goods;
    }

    /**
     * 校验优惠券信息并计算优惠
     * @param array $format_goods
     * @param array $coupons
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public function checkCoupons(array $format_goods, array $coupons)
    {
        if (!$coupons) return $format_goods;
        foreach ($format_goods as $_key => $seller_goods) {
            $seller_id = $seller_goods['seller']['id'];
            $coupons_id = $coupons[$seller_id] ?? 0;
            if ($coupons_id) {
                $coupons_data = CouponsService::checkCoupons($this->m_id, $seller_id, $coupons_id, $seller_goods['goods']);
                if (!$coupons_data) {
                    api_error(__('api.coupons_no_use'));
                }
                $sku_ids = $coupons_data['sku_id'];
                //过滤掉不符合的商品
                $coupons_goods = [];
                foreach ($seller_goods['goods'] as $goods) {
                    if (in_array($goods['sku_id'], $sku_ids)) {
                        $coupons_goods[] = $goods;
                    }
                }
                $price = self::sumGoodsPrice($coupons_goods);
                //计算优惠金额所占权重
                $promotion_goods = self::getPromotionRate($coupons_goods, $price['sell_price'], $coupons_data['promotion_price']);//计算优惠的时候需要用优惠前的价格
                //合并计算了优惠券金额权重后的商品
                $coalescing_goods = self::coalescingPromotionGoods($seller_goods['goods'], $promotion_goods);
                $coalescing_price = self::sumGoodsPrice($coalescing_goods);
                //组装商品
                $format_goods[$_key]['goods'] = $coalescing_goods;
                $format_goods[$_key]['price'] = $coalescing_price;
                $format_goods[$_key]['coupons_id'] = $coupons_id;
                $format_goods[$_key]['promotion'][] = [
                    'title' => $coupons_data['coupons']['title'],
                    'price' => $coupons_data['promotion_price']
                ];
            }
        }
        return $format_goods;
    }

    /**
     * 获取优惠信息
     * @param array $format_goods
     * @return array
     */
    public function promotionPrice(array $format_goods)
    {
        $group_id = $this->user_group['group_id'];
        foreach ($format_goods as $_key => $seller_goods) {
            $sell_price = $seller_goods['price']['sell_price'];//计算优惠的时候需要按照原价计算
            //查询该商家下的优惠活动
            $where = [
                ['type', Promotion::RULE_TYPE_AMOUNT],
                ['seller_id', $seller_goods['seller']['id']],
                ['status', Promotion::STATUS_ON],
                ['use_price', '<=', $sell_price],
                ['start_at', '<=', get_date()],
                ['end_at', '>=', get_date()],
            ];
            $type_promotion = [Promotion::AMOUNT_TYPE_REDUCTION, Promotion::AMOUNT_TYPE_DISCOUNT];
            $res_promotion = Promotion::select('title', 'type', 'type_value')->where($where)->whereIn('type', $type_promotion)->whereRaw("find_in_set($group_id, user_group)")->get();
            if (!$res_promotion->isEmpty()) {
                $promotion = [
                    'title' => '',
                    'price' => 0
                ];
                foreach ($res_promotion as $value) {
                    $new_promotion_price = 0;
                    switch ($value['type']) {
                        case Promotion::AMOUNT_TYPE_REDUCTION:
                            $new_promotion_price = $value['type_value'];//优惠金额
                            break;
                        case Promotion::AMOUNT_TYPE_DISCOUNT:
                            if ($value['type_value']) {
                                $new_promotion_price = $sell_price - ($sell_price * ($value['type_value'] / 100));//优惠金额
                            }
                            break;
                    }
                    //获取优惠最大的活动
                    $new_promotion_price = format_price($new_promotion_price);
                    if ($promotion['price'] < $new_promotion_price) {
                        $promotion['title'] = $value['title'];
                        $promotion['price'] = $new_promotion_price;
                    }
                }
                $format_goods[$_key]['price']['promotion_price'] += $promotion['price'];
                $format_goods[$_key]['price']['subtotal'] = $seller_goods['price']['subtotal'] - $promotion['price'];
                $format_goods[$_key]['promotion'][] = $promotion;
                //开始平均分摊优惠金额
                $format_goods[$_key]['goods'] = self::getPromotionRate($seller_goods['goods'], $sell_price, $promotion['price']);
            }
        }
        return $format_goods;
    }

    /**
     * 计算优惠比例金额
     * @param array $seller_goods
     * @param float $subtotal
     * @param float $promotion_price
     * @return array
     */
    public function getPromotionRate(array $seller_goods, float $subtotal, float $promotion_price)
    {
        if (!$promotion_price) return $seller_goods;
        //计算比例
        $tmp_total_rate = 0;
        $tmp_rate = [];
        foreach ($seller_goods as $goods_id => $goods) {
            $pct = round(($goods['show_price'] * $goods['buy_qty']) / $subtotal, 4);
            $tmp_total_rate += $pct;
            $tmp_rate[$goods_id] = $pct;
        }
        //在比例加起来不等于1的时候需要容差
        if ($tmp_total_rate != 1) {
            $_rate = $tmp_total_rate - 1;
            if ($_rate < 1) {
                $_rate = abs($_rate);
                $goods_key = array_search(min($tmp_rate), $tmp_rate);
            } else {
                $_rate = -abs($_rate);
                $goods_key = array_search(max($tmp_rate), $tmp_rate);
            }
            $tmp_rate[$goods_key] += $_rate;
        }
        //计算商品的优惠金额
        $tmp_total_promotion_price = 0;
        $tmp_promotion_price_arr = [];
        foreach ($seller_goods as $goods_id => $goods) {
            $_promotion_price = format_price($tmp_rate[$goods_id] * $promotion_price);
            $tmp_total_promotion_price += $_promotion_price;
            $tmp_promotion_price_arr[$goods_id] = $_promotion_price;
            $seller_goods[$goods_id]['promotion_price'] += $_promotion_price;
        }
        //在总优惠金额加起来不等于优惠金额的时候需要容差
        if ($tmp_total_promotion_price != $promotion_price) {
            $_price = $tmp_total_promotion_price - $promotion_price;
            if ($_price < $promotion_price) {
                $_price = abs($_price);
                $goods_key = array_search(min($tmp_promotion_price_arr), $tmp_promotion_price_arr);
            } else {
                $_price = -abs($_price);
                $goods_key = array_search(max($tmp_promotion_price_arr), $tmp_promotion_price_arr);
            }
            $seller_goods[$goods_key]['promotion_price'] += format_price($_price);
        }
        return $seller_goods;
    }

    /**
     * 计算运费
     * @param array $format_goods
     * @param array $address
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public function getDeliveryPrice(array $format_goods, array $address)
    {
        foreach ($format_goods as $_key => $seller_goods) {
            $delivery_price = DeliveryService::getPrice($seller_goods['goods'], $address);
            if ($delivery_price['sku_ids']) {
                //存在不可配送的商品时候
                $new_seller_goods = [];
                foreach ($seller_goods['goods'] as $goods) {
                    $_goods = $goods;
                    if (in_array($goods['sku_id'], $delivery_price['sku_ids'])) {
                        $_goods['is_delivery'] = __('api.delivery_can_not');//不在配送范围内
                    }
                    $new_seller_goods[] = $_goods;
                }
                $format_goods[$_key]['goods'] = $new_seller_goods;
            }
            $subtotal = $format_goods[$_key]['price']['subtotal'] + $delivery_price['delivery_price_real'];
            $format_goods[$_key]['price']['subtotal'] = format_price($subtotal);
            $format_goods[$_key]['delivery'] = $delivery_price;
        }
        return $format_goods;
    }

    /**
     * 剔除沉余字段信息
     * @param array $format_goods
     * @return array
     */
    public function eliminateUselessParams(array $format_goods)
    {
        foreach ($format_goods as $_key => $seller_goods) {
            unset($format_goods[$_key]['seller']['status'], $format_goods[$_key]['delivery']['sku_ids']);
            foreach ($seller_goods['goods'] as $sku_id => $goods) {
                unset($format_goods[$_key]['goods'][$sku_id]['weight'],
                    $format_goods[$_key]['goods'][$sku_id]['status'],
                    $format_goods[$_key]['goods'][$sku_id]['seller_id'],
                    $format_goods[$_key]['goods'][$sku_id]['brand_id'],
                    $format_goods[$_key]['goods'][$sku_id]['category_id'],
                    $format_goods[$_key]['goods'][$sku_id]['delivery_id'],
                    $format_goods[$_key]['goods'][$sku_id]['shelves_status']
                );
            }
        }
        return $format_goods;
    }

    /**
     * 验证活动
     * @param int $goods_id 商品id
     * @param int $sku_id skuid
     * @param bool $is_submit 是否提交订单，提交订单会涉及扣减库存
     * @return int[]
     * @throws \App\Exceptions\ApiError
     */
    public function checkRule(array $cart, bool $is_submit = false)
    {
        $return = [
            'goods_id' => $cart['goods_id'],
            'sku_id' => $cart['sku_id']
        ];
        $goods = Goods::getGoods($cart['goods_id']);
        $goods_sku = GoodsSku::getGoodsSku($cart['sku_id']);
        if (!$goods || !$goods_sku) api_error(__('api.goods_error'));
        if ($goods['promo_type'] == Goods::PROMO_TYPE_SECKILL || $goods['type'] == Goods::TYPE_COUPONS) {
            if ($cart['buy_qty'] > $goods_sku['max_buy']) {
                api_error(__('api.goods_max_buy_qty_error') . $goods_sku['min_buy']);
            }
            if ($goods['promo_type'] == Goods::PROMO_TYPE_SECKILL) {
                PromoSeckill::checkSeckill($cart);//验证秒杀信息
            }
            Goods::getRedisSkuStock($cart, $is_submit);//验证和扣减秒杀库存
            if ($goods['type'] == Goods::TYPE_COUPONS) {
                //查询已经领取的优惠券数量
                GoodsObject::getCoupons($cart, $this->m_id);
            }
        } elseif ($goods['promo_type'] == Goods::PROMO_TYPE_GROUP) {
            $group_order_id = (int)request()->input('group_order_id');
            //验证团购信息
            PromoGroup::checkGroup($goods['id'], $group_order_id);
            $return['group_order_id'] = $group_order_id;
        }
        return $return;
    }

    /**
     * 还原活动库存
     * @param array $seller_goods
     * @return void
     */
    public function activityStockIncr(array $seller_goods)
    {
        foreach ($seller_goods as $goods) {
            if ($goods['promo_type'] == Goods::PROMO_TYPE_SECKILL || $goods['type'] == Goods::TYPE_COUPONS) {
                //这里开始还原redis库存
                Goods::stockRedisIncr($goods);
            } elseif ($goods['promo_type'] == Goods::PROMO_TYPE_GROUP) {
                //团购信息
            }
        }
    }

    /**
     * 计算订单总金额
     * @param array $format_goods
     * @return array
     */
    public function sumOrderPrice(array $format_goods)
    {
        $subtotal = $sell_price = $delivery_price = $promotion_price = 0;
        foreach ($format_goods as $seller_goods) {
            $subtotal += $seller_goods['price']['subtotal'];
            $sell_price += $seller_goods['price']['sell_price'];
            $promotion_price += $seller_goods['price']['promotion_price'];
            $delivery_price += $seller_goods['delivery']['delivery_price_real'];
        }
        return [
            'sell_price' => $sell_price,
            'delivery_price' => $delivery_price,
            'promotion_price' => $promotion_price,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * 合并计算了优惠券金额权重后的商品
     * @param array $format_goods
     * @param array $promotion_goods
     * @return array
     */
    public static function coalescingPromotionGoods(array $format_goods, array $promotion_goods)
    {
        $new_format_goods = array_column($format_goods, null, 'sku_id');
        foreach ($promotion_goods as $goods) {
            $new_format_goods[$goods['sku_id']]['promotion_price'] = $goods['promotion_price'];
        }
        return array_values($new_format_goods);
    }

    /**
     * 格式化订单参数
     * @return array
     */
    public static function formatParams()
    {
        $coupons = request()->input('coupons');
        $coupons = $coupons ? json_decode($coupons, true) : [];
        $note = request()->input('note');
        $note = $note ? json_decode($note, true) : [];
        $delivery = request()->input('delivery');
        $delivery = $delivery ? json_decode($delivery, true) : [];
        $invoice = request()->input('invoice');
        $invoice = $invoice ? json_decode($invoice, true) : [];
        return [$coupons, $delivery, $note, $invoice];
    }

    /**
     * 验证发票信息
     * @param array $seller_goods
     * @param array $invoice
     * @return mixed
     * @throws \App\Exceptions\ApiError
     */
    public static function checkInvoice(array $seller_goods, array $invoice)
    {
        foreach ($seller_goods as $value) {
            $seller_id = $value['seller']['id'];
            if (isset($invoice[$seller_id]) && $invoice[$seller_id]) {
                $_seller_invoice = $invoice[$seller_id];
                if ($value['seller']['invoice'] == Seller::INVOICE_ON) {
                    if (!isset($_seller_invoice['title']) || !$_seller_invoice['title']) {
                        api_error(__('api.invoice_title_error'));
                    }
                    if (!isset(OrderInvoice::TYPE_DESC[$_seller_invoice['type']])) {
                        api_error(__('api.invalid_params'));
                    }
                    if ($_seller_invoice['type'] == OrderInvoice::TYPE_ENTERPRISE && !$_seller_invoice['tax_no']) {
                        api_error(__('api.invoice_tax_no_error'));
                    }
                } else {
                    unset($invoice[$seller_id]);
                }
            }
        }
        return $invoice;
    }

    /**
     * 过滤信息只保留价格
     * @param array $format_goods
     * @return array
     */
    public function filterPrice(array $format_goods)
    {
        $new_seller_goods = [];
        foreach ($format_goods as $seller_goods) {
            $new_seller_goods[] = [
                'seller' => $seller_goods['seller'],
                'price' => $seller_goods['price'],
                'delivery' => $seller_goods['delivery'],
                'promotion' => $seller_goods['promotion'] ?? [],
            ];
        }
        return $new_seller_goods;
    }
}