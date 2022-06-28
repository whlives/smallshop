<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/3
 * Time: 4:45 PM
 */

namespace App\Models\Market;

use App\Models\BaseModel;
use App\Models\Financial\Point;
use App\Models\Financial\PointDetail;
use App\Models\Member\Member;

/**
 * 优惠活动
 */
class Promotion extends BaseModel
{
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    //规则
    const RULE_TYPE_AMOUNT = 1;
    const RULE_TYPE_REG = 2;
    const RULE_TYPE_DESC = [
        self::RULE_TYPE_AMOUNT => '消费金额',
        self::RULE_TYPE_REG => '新用户注册',
    ];

    //消费金额类型
    const AMOUNT_TYPE_REDUCTION = 1;//满减
    const AMOUNT_TYPE_DISCOUNT = 2;//折扣优惠
    const AMOUNT_TYPE_POINT = 3;//赠送积分
    const AMOUNT_TYPE_COUPONS = 4;//满送送优惠券
    const AMOUNT_TYPE_DESC = [
        self::AMOUNT_TYPE_REDUCTION => '满减',
        self::AMOUNT_TYPE_DISCOUNT => '折扣优惠',
        self::AMOUNT_TYPE_POINT => '赠送积分',
        self::AMOUNT_TYPE_COUPONS => '送优惠券',
    ];

    //注册类型
    const REG_TYPE_POINT = 50;//赠送积分
    const REG_TYPE_COUPONS = 51;//满送送优惠券
    const REG_TYPE_DESC = [
        self::REG_TYPE_POINT => '赠送积分',
        self::REG_TYPE_COUPONS => '赠送优惠券',
    ];

    protected $table = 'promotion';
    protected $guarded = ['id'];

    /**
     * 注册奖励活动
     * @param array $member_data
     * @return bool
     */
    public static function reg(array $member_data)
    {
        //判断是否有注册活动
        $where = [
            ['status', self::STATUS_ON],
            ['rule_type', self::RULE_TYPE_REG],
            ['start_at', '<=', get_date()],
            ['end_at', '>=', get_date()],
        ];
        $group_id = $member_data['group_id'];
        $res_list = self::select('title', 'type', 'type_value')
            ->where($where)
            ->whereRaw("find_in_set($group_id, user_group)")
            ->get();
        if ($res_list->isEmpty()) {
            return false;
        }
        foreach ($res_list as $value) {
            switch ($value['type']) {
                case self::REG_TYPE_POINT:
                    //奖励积分
                    Point::updateAmount($member_data['id'], $value['type_value'], PointDetail::EVENT_SYSTEM_REWARD, '', '注册奖励');
                    break;
                case self::REG_TYPE_COUPONS:
                    //赠送优惠券
                    CouponsDetail::generate($value['type_value'], $member_data['id'], 1);
                    break;
            }
        }
        return true;
    }

    /**
     * 订单奖励活动
     * @param array $order
     * @return bool
     */
    public static function order(array $order)
    {
        //判断是否有订单奖励活动
        $where = [
            ['seller_id', $order['seller_id']],
            ['status', self::STATUS_ON],
            ['rule_type', self::RULE_TYPE_AMOUNT],
            ['use_price', '<=', $order['subtotal']],
            ['start_at', '<=', $order['created_at']],
            ['end_at', '>=', $order['created_at']],
        ];
        $group_id = Member::where('id', $order['m_id'])->value('group_id');;
        $res_list = self::select('title', 'type', 'type_value')
            ->where($where)
            ->whereIn('type', [self::AMOUNT_TYPE_COUPONS, self::AMOUNT_TYPE_POINT])
            ->whereRaw("find_in_set($group_id, user_group)")
            ->get();
        if ($res_list->isEmpty()) {
            return false;
        }
        $use_price = $point_amount = 0;
        $point_promotion = $coupons_promotion = [];
        foreach ($res_list as $value) {
            switch ($value['type']) {
                case self::AMOUNT_TYPE_POINT:
                    //获取奖励积分最多的
                    if ($value['type_value'] > $point_amount) {
                        $point_amount = $value['type_value'];
                        $point_promotion = $value;
                    }

                    break;
                case self::AMOUNT_TYPE_COUPONS:
                    //获取起用金额最高的
                    if ($value['use_price'] > $use_price) {
                        $use_price = $value['use_price'];
                        $coupons_promotion = $value;
                    }
                    break;
            }
        }
        if ($point_promotion && $point_promotion['type_value']) {
            //奖励积分
            Point::updateAmount($order['m_id'], $value['type_value'], PointDetail::EVENT_SYSTEM_REWARD, '', '订单奖励');
        }
        if ($coupons_promotion && $coupons_promotion['type_value']) {
            //赠送优惠券
            CouponsDetail::generate($value['type_value'], $order['m_id'], 1);
        }
        return true;
    }
}