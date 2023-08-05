<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 2:14 PM
 */

namespace App\Models\System;

use App\Models\BaseModel;
use App\Models\Financial\Trade;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 支付方式
 */
class Payment extends BaseModel
{
    use SoftDeletes;

    protected $table = 'payment';
    protected $guarded = ['id'];
    protected $hidden = ['deleted_at'];
    protected $dates = ['deleted_at'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    //类型
    const TYPE_LINE = 1;
    const TYPE_OFFLINE = 2;
    const TYPE_DESC = [
        self::TYPE_LINE => '线上',
        self::TYPE_OFFLINE => '线下',
    ];

    //使用客户端
    const CLIENT_TYPE_WEB = 'web';
    const CLIENT_TYPE_H5 = 'h5';
    const CLIENT_TYPE_MP = 'mp';
    const CLIENT_TYPE_WECHAT = 'wechat';
    const CLIENT_TYPE_IOS = 'ios';
    const CLIENT_TYPE_ANDROID = 'android';
    const CLIENT_TYPE_DESC = [
        self::CLIENT_TYPE_WEB => 'web端',
        self::CLIENT_TYPE_H5 => 'h5端',
        self::CLIENT_TYPE_MP => '公众号',
        self::CLIENT_TYPE_WECHAT => '小程序',
        self::CLIENT_TYPE_IOS => 'IOS',
        self::CLIENT_TYPE_ANDROID => '安卓'
    ];

    //支付方式（同步数据库）
    const PAYMENT_BALANCE = 5;
    const PAYMENT_WECHAT = 2;
    const PAYMENT_ALIPAY = 3;
    const PAYMENT_UNIONPAY = 4;
    const PAYMENT_DESC = [
        self::PAYMENT_BALANCE => '余额',
        self::PAYMENT_WECHAT => '微信',
        self::PAYMENT_ALIPAY => '支付宝',
        self::PAYMENT_UNIONPAY => '银联',
    ];

    /**
     * 获取支付方式
     * @return array
     */
    public static function getPayment($type = 1)
    {
        $platform = get_platform();
        $where = [
            ['status', self::STATUS_ON],
            ['type', Payment::TYPE_LINE]
        ];
        //充值不能使用余额
        if ($type == Trade::TYPE_RECHARGE) {
            $where[] = ['id', '<>', self::PAYMENT_BALANCE];
        }
        $res_list = self::select('id', 'title', 'image')
            ->where($where)
            ->whereRaw("find_in_set('$platform', client_type)")
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        if ($res_list->isEmpty()) {
            return [];
        }
        return $res_list->toArray();
    }
}
