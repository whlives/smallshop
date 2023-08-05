<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/14
 * Time: 2:45 PM
 */

namespace App\Models\Order;

use App\Models\BaseModel;

/**
 * 售后
 */
class Refund extends BaseModel
{
    protected $table = 'refund';
    protected $guarded = ['id'];

    //状态
    const STATUS_WAIT_APPROVE = 0;
    const STATUS_REFUSED_APPROVE = 1;
    const STATUS_WAIT_DELIVERY = 2;
    const STATUS_RECEIVED = 3;
    const STATUS_REFUSED_RECEIVED = 4;
    const STATUS_WAIT_SELLER_DELIVERY = 5;
    const STATUS_WAIT_CONFIRM_DELIVERY = 9;
    const STATUS_WAIT_PAY = 6;
    const STATUS_DONE = 7;
    const STATUS_CUSTOMER_CANCEL = 8;

    const STATUS_DESC = [
        self::STATUS_WAIT_APPROVE => '待审核',
        self::STATUS_REFUSED_APPROVE => '审核拒绝',
        self::STATUS_WAIT_DELIVERY => '待买家退货',
        self::STATUS_RECEIVED => '待商家收货',
        self::STATUS_REFUSED_RECEIVED => '拒绝收货',
        self::STATUS_WAIT_SELLER_DELIVERY => '待卖家发货',
        self::STATUS_WAIT_CONFIRM_DELIVERY => '待买家确认',
        self::STATUS_WAIT_PAY => '待退款',
        self::STATUS_DONE => '售后完成',
        self::STATUS_CUSTOMER_CANCEL => '买家撤销',
    ];

    //会员看到状态
    const STATUS_MEMBER_DESC = [
        self::STATUS_WAIT_APPROVE => '待审核',
        self::STATUS_REFUSED_APPROVE => '审核拒绝',
        self::STATUS_WAIT_DELIVERY => '待退货',
        self::STATUS_RECEIVED => '待商家收货',
        self::STATUS_REFUSED_RECEIVED => '拒绝收货',
        self::STATUS_WAIT_SELLER_DELIVERY => '待卖家发货',
        self::STATUS_WAIT_CONFIRM_DELIVERY => '待收货',
        self::STATUS_WAIT_PAY => '待退款',
        self::STATUS_DONE => '售后完成',
        self::STATUS_CUSTOMER_CANCEL => '售后关闭',
    ];

    //商家看到状态
    const STATUS_SELLER_DESC = [
        self::STATUS_WAIT_APPROVE => '待审核',
        self::STATUS_REFUSED_APPROVE => '审核拒绝',
        self::STATUS_WAIT_DELIVERY => '待买家退货',
        self::STATUS_RECEIVED => '待商家收货',
        self::STATUS_REFUSED_RECEIVED => '拒绝收货',
        self::STATUS_WAIT_SELLER_DELIVERY => '待卖家发货',
        self::STATUS_WAIT_CONFIRM_DELIVERY => '待买家确认',
        self::STATUS_WAIT_PAY => '待退款',
        self::STATUS_DONE => '售后完成',
        self::STATUS_CUSTOMER_CANCEL => '售后关闭',
    ];

    //售后类型
    const REFUND_TYPE_MONEY = 1;
    const REFUND_TYPE_GOODS = 2;
    const REFUND_TYPE_REPLACE = 3;
    const REFUND_TYPE_DESC = [
        self::REFUND_TYPE_MONEY => '仅退款',
        self::REFUND_TYPE_GOODS => '退货退款',
        self::REFUND_TYPE_REPLACE => '换货',
    ];

    //售后理由1仅退款，2退货退款，3换货
    const REASON_DESC = [
        self::REFUND_TYPE_MONEY => [
            '1' => '不想要了',
            '2' => '买错了/订单信息错误',
            '3' => '未按约定时间发货',
            '4' => '缺货',
            '5' => '其他',
            '6' => '退运费'
        ],
        self::REFUND_TYPE_GOODS => [
            '1' => '七天无理由退换货',
            '2' => '商品破损',
            '3' => '收到假货',
            '4' => '收到商品与实际不符',
            '5' => '商品质量问题',
            '6' => '物流太慢/未收到货',
            '7' => '发票问题',
            '8' => '其他',
        ],
        self::REFUND_TYPE_REPLACE => [
            '1' => '商品破损',
            '2' => '收到假货',
            '3' => '收到商品与实际不符',
            '4' => '商品质量问题',
            '5' => '其他',
        ]
    ];

    //用户是否删除
    const IS_DELETE_NO = 0;
    const IS_DELETE_YES = 1;
    const IS_DELETE_DESC = [
        self::IS_DELETE_NO => '否',
        self::IS_DELETE_YES => '是',
    ];

    /**
     * 获取订单信息
     * @param string $refund_no
     * @param int $m_id
     * @return mixed
     * @throws \App\Exceptions\ApiError
     */
    public static function getInfo(string $refund_no, int $m_id)
    {
        $order = self::where(['m_id' => $m_id, 'refund_no' => $refund_no])->first();
        if (!$order) {
            api_error(__('api.refund_error'));
        }
        return $order->toArray();
    }

    /**
     * 格式化售后理由
     * @return array
     */
    public static function formatReason()
    {
        $app_reason = [];
        foreach (self::REASON_DESC as $_type => $_type_value) {
            $app_reason_type = [];
            foreach ($_type_value as $key => $val) {
                $app_reason_type[] = [
                    'id' => $key,
                    'title' => $val
                ];
            }
            $app_reason[] = [
                'type' => $_type,
                'reason' => $app_reason_type
            ];
        }
        return $app_reason;
    }
}
