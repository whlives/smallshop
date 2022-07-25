<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:33 PM
 */

namespace App\Models\Goods;

use App\Models\BaseModel;
use App\Models\Market\Coupons;
use App\Models\Market\CouponsDetail;

/**
 * 优惠券商品
 */
class GoodsCoupons extends BaseModel
{

    protected $table = 'goods_coupons';
    protected $guarded = ['id'];

    public $timestamps = false;

    /**
     * 获取和验证用户购买优惠券信息
     * @param array $cart
     * @param int $m_id
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    public static function getCoupons(array $cart, int $m_id)
    {
        //查询优惠券
        $coupon_id = self::where('goods_id', $cart['goods_id'])->value('coupon_id');
        if (!$coupon_id) {
            api_error(__('api.coupons_not_exists'));
        }
        //查询优惠券详情
        $coupons = Coupons::where(['status' => Coupons::STATUS_ON, 'id' => $coupon_id])->first();
        if (!$coupons) {
            api_error(__('api.coupons_not_exists'));
        } elseif ($coupons['end_at'] < get_date()) {
            api_error(__('api.coupons_overdue'));
        }
        //查询已经领取的优惠券数量
        $obtain_total = CouponsDetail::where(['coupons_id' => $coupon_id, 'm_id' => $m_id])->count();
        $remainder = $coupons['limit'] - $obtain_total;
        //判断本次最多还能购买的数量
        if ($cart['buy_qty'] > $remainder) {
            api_error(__('api.coupons_buy_max'));
        }
    }

}
