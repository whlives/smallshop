<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/20
 * Time: 21:14 PM
 */

namespace App\Services;

use App\Models\Market\Coupons;
use App\Models\Market\CouponsDetail;
use App\Models\Market\CouponsRule;

class CouponsService
{

    /**
     * 获取可用优惠券
     * @param int $m_id 用户id
     * @param int $seller_id 商家id
     * @param array $goods 商品信息
     * @return array|array[]
     */
    public static function getCoupons(int $m_id, int $seller_id, array $goods)
    {
        $return = [
            'valid_coupons' => [],
            'invalid_coupons' => []
        ];
        $where = [
            ['status', CouponsDetail::STATUS_ON],
            ['m_id', $m_id],
            ['is_use', CouponsDetail::USE_OFF],
            ['start_at', '<=', get_date()],
            ['end_at', '>=', get_date()],
        ];
        $my_coupons = CouponsDetail::query()->where($where)->select('id', 'coupons_id')->get();
        if ($my_coupons->isEmpty()) {
            return $return;
        }
        $coupons_ids = array_column($my_coupons->toArray(), 'coupons_id');
        $res_coupons = Coupons::query()->where(['seller_id' => $seller_id, 'status' => Coupons::STATUS_ON])->whereIn('id', array_unique($coupons_ids))->get();
        if ($res_coupons->isEmpty()) {
            return $return;
        }
        $invalid = $valid = [];
        //开始过滤满足条件的优惠券
        foreach ($res_coupons->toArray() as $coupons) {
            $conform_rule = self::getConformRule($coupons, $goods);
            $amount = $coupons['amount'];
            if ($coupons['type'] == Coupons::TYPE_DISCOUNT) {
                $amount = format_price($coupons['amount'] / 10);//折扣券
            }
            $_item = array(
                'title' => $coupons['title'],
                'type' => $coupons['type'],
                'amount' => $amount,
                'use_price' => $coupons['use_price'],
                'image' => $coupons['image'],
                'start_at' => $coupons['start_at'],
                'end_at' => $coupons['end_at'],
                'note' => $coupons['note'],
            );
            if ($conform_rule) {
                $valid[$coupons['id']] = $_item;
            } else {
                $invalid[$coupons['id']] = $_item;
            }
        }
        $invalid_coupons = $valid_coupons = [];
        foreach ($my_coupons as $value) {
            $coupons_id = $value['coupons_id'];
            if (isset($valid[$coupons_id])) {
                //有效的
                $_item = $valid[$coupons_id];
                $_item['id'] = $value['id'];
                $valid_coupons[] = $_item;
            } elseif (isset($invalid[$coupons_id])) {
                //无效的
                $_item = $invalid[$coupons_id];
                $_item['id'] = $value['id'];
                $invalid_coupons[] = $_item;
            }
        }
        return [
            'valid_coupons' => $valid_coupons,
            'invalid_coupons' => $invalid_coupons
        ];
    }

    /**
     * 验证优惠券是否可用
     * @param int $m_id 用户id
     * @param int $seller_id 商家id
     * @param int $coupons_id 优惠券id
     * @param array $goods 商品
     * @return array|false
     * @throws \App\Exceptions\ApiError
     */
    public static function checkCoupons(int $m_id, int $seller_id, int $coupons_id, array $goods)
    {
        $where = [
            ['m_id', $m_id],
            ['id', $coupons_id],
            ['status', CouponsDetail::STATUS_ON],
            ['start_at', '<=', get_date()],
        ];
        $coupons_detail = CouponsDetail::query()->where($where)->first();
        if (!$coupons_detail) {
            api_error(__('api.coupons_not_exists'));
        } elseif ($coupons_detail['is_use'] == CouponsDetail::USE_ON) {
            api_error(__('api.coupons_is_use'));
        } elseif ($coupons_detail['end_at'] <= get_date()) {
            api_error(__('api.coupons_overdue'));
        }
        //查询优惠券详情
        $coupons = Coupons::query()->where(['seller_id' => $seller_id, 'status' => Coupons::STATUS_ON, 'id' => $coupons_detail['coupons_id']])->first();
        if (!$coupons) {
            api_error(__('api.coupons_not_exists'));
        }
        $conform_rule = self::getConformRule($coupons->toArray(), $goods);
        if ($conform_rule) {
            $conform_rule['coupons']['title'] = $coupons['title'];
            return $conform_rule;
        }
        return false;
    }

    /**
     * 检验优惠券是否满足条件
     * @param array $coupons
     * @param array $goods
     * @return array|false 检验通过返回满足的商品sku_id和与优惠金额
     */
    public static function getConformRule(array $coupons, array $goods)
    {
        $conform_sku_id = array_column($goods, 'sku_id');//默认所有商品可用
        $res_rules = CouponsRule::query()->where('coupons_id', $coupons['id'])->get();
        $sku_ids = [];
        if (!$res_rules->isEmpty()) {
            //没有规则就是全部可用
            $rules = [];
            foreach ($res_rules as $_rule) {
                $rules[$_rule['type']][$_rule['in_type']][] = $_rule['obj_id'];
            }
            foreach ($rules as $type => $value) {
                foreach ($value as $in_type => $obj_id) {
                    $sku_ids[] = self::getConformSkuid($goods, CouponsRule::TYPE_FIELD_NAME[$type], $obj_id, $in_type);
                }
            }
        } else {
            $sku_ids[] = $conform_sku_id;//没有设置条件的时候默认所有商品都可以使用
        }
        if (!$sku_ids) return false;//没有满足条件的优惠券
        foreach ($sku_ids as $sku_id) {
            $conform_sku_id = array_intersect($conform_sku_id, $sku_id);
        }
        $conform_sku_id = array_unique($conform_sku_id);

        //计算满足条件的商品金额
        if ($conform_sku_id) {
            $total_price = 0;
            foreach ($goods as $sku) {
                if (in_array($sku['sku_id'], $conform_sku_id)) {
                    $total_price += $sku['show_price'] * $sku['buy_qty'];
                }
            }
            $total_price = format_price($total_price);
            //判断商品金额是否满足优惠券,并计算符合的优惠券优惠金额
            if ($total_price >= $coupons['use_price']) {
                $promotion_price = 0;
                if ($coupons['type'] == Coupons::TYPE_REDUCTION) {
                    $promotion_price = $coupons['amount'];
                } elseif ($coupons['type'] == Coupons::TYPE_DISCOUNT) {
                    $promotion_price = $total_price - ($total_price * ($coupons['amount'] / 100));
                }
                return array(
                    'sku_id' => $conform_sku_id,
                    'promotion_price' => format_price($promotion_price)
                );
            }
        }
        return false;
    }

    /**
     * 获取满足条件商品sku_id
     * @param array $goods 商品信息
     * @param string $field 条件字段
     * @param array $obj_id 条件商品id
     * @param int $in_type 类型包含、不包含
     * @return array
     */
    public static function getConformSkuid(array $goods, string $field, array $obj_id, int $in_type)
    {
        $sku_ids = [];
        foreach ($goods as $value) {
            if (in_array($value[$field], $obj_id) && $in_type == CouponsRule::IN_TYPE_IN) {
                $sku_ids[] = $value['sku_id'];
            } elseif (!in_array($value[$field], $obj_id) && $in_type == CouponsRule::IN_TYPE_OUT) {
                $sku_ids[] = $value['sku_id'];
            }
        }
        return $sku_ids;
    }

}
