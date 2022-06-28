<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/3
 * Time: 4:46 PM
 */

namespace App\Models\Market;

use App\Models\BaseModel;

/**
 * 优惠券明细
 */
class CouponsDetail extends BaseModel
{
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    //是否使用
    const USE_OFF = 0;//未使用
    const USE_ON = 1;//已使用
    const USE_DESC = [
        self::USE_OFF => '未使用',
        self::USE_ON => '已使用',
    ];

    protected $table = 'coupons_detail';
    protected $guarded = ['id'];

    public $timestamps = false;

    /**
     * 发放优惠券
     * @param int $coupons_id 优惠券id
     * @param int $m_id 用户id
     * @param int $num 数量
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public static function generate(int $coupons_id, int $m_id, int $num)
    {
        if ($num > 100) {
            return __('admin.coupons_max_100');
        }
        $coupons = Coupons::where('id', $coupons_id)->first();
        if (!$coupons) {
            return __('admin.coupons_not_exists');
        } elseif ($coupons['end_at'] < get_date() && !$coupons['day_num']) {
            return __('admin.coupons_overdue');
        } elseif ($coupons['status'] != Coupons::STATUS_ON) {
            return __('admin.coupons_status_error');
        }
        if ($coupons['day_num']) {
            $start_at = get_date();
            $end_at = get_date(time() + (3600 * 24 * $coupons['day_num']));
        } else {
            $start_at = $coupons['start_at'];
            $end_at = $coupons['end_at'];
        }
        $insert_data = [];
        for ($i = 1; $i <= $num; $i++) {
            $insert_data[] = [
                'coupons_id' => $coupons_id,
                'm_id' => $m_id,
                'status' => CouponsDetail::STATUS_ON,
                'is_use' => CouponsDetail::USE_OFF,
                'start_at' => $start_at,
                'end_at' => $end_at,
                'bind_at' => get_date()
            ];
        }
        $res = CouponsDetail::insert($insert_data);
        if ($res) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 领取优惠券
     * @param int $coupons_id 优惠券id
     * @param int $m_id 用户id
     * @param int $num 数量
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public static function obtain(int $coupons_id, int $m_id, int $num)
    {
        $coupons = Coupons::where('id', $coupons_id)->first();
        if (!$coupons) {
            return __('api.coupons_not_exists');
        } elseif ($coupons['end_at'] < get_date() && !$coupons['day_num']) {
            return __('api.coupons_overdue');
        } elseif ($coupons['status'] != Coupons::STATUS_ON) {
            return __('api.coupons_status_error');
        } elseif ($coupons['open'] != Coupons::OPEN_ON) {
            return __('api.coupons_not_open');
        }
        //查询领取数量限制
        if ($coupons['limit'] > 0) {
            $have_num = self::where(['m_id' => $m_id, 'coupons_id' => $coupons_id])->count();
            if (($have_num + $num) > $coupons['limit']) {
                return __('api.coupons_limit_max') . $coupons['limit'];
            }
        }
        if ($coupons['day_num']) {
            $start_at = get_date();
            $end_at = get_date(time() + (3600 * 24 * $coupons['day_num']));
        } else {
            $start_at = $coupons['start_at'];
            $end_at = $coupons['end_at'];
        }
        $insert_data = [];
        for ($i = 1; $i <= $num; $i++) {
            $insert_data[] = [
                'coupons_id' => $coupons_id,
                'm_id' => $m_id,
                'status' => CouponsDetail::STATUS_ON,
                'is_use' => CouponsDetail::USE_OFF,
                'start_at' => $start_at,
                'end_at' => $end_at,
                'bind_at' => get_date()
            ];
        }
        $res = CouponsDetail::insert($insert_data);
        if ($res) {
            return true;
        } else {
            return false;
        }
    }
}