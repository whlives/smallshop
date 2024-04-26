<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/9
 * Time: 4:28 PM
 */

return [
    //公共错误提示
    'invalid_request' => '10000|无效的请求',
    'missing_params' => '10001|缺少参数',
    'invalid_params' => '10002|无效的参数',
    'invalid_token' => '10003|登录已经失效',
    'invalid_sign' => '10004|无效的签名',
    'invalid_device' => '10005|无效的设备',
    'invalid_platform' => '10006|无效的来源',
    'timestamp_error' => '10007|无效的时间戳',
    'timestamp_out' => '10008|时间超时',
    'fail' => '10009|操作失败',
    'content_is_empty' => '10010|内容为空',
    'request_frequent' => '10011|频繁请求，请稍后再试',
    'captcha_error' => '10012|图形验证码错误',
    'role_error' => '10013|权限不足',
    'save_error' => '10014|保存失败',
    'del_error' => '10015|删除失败',
    'upload_file_exists' => '10016|上传文件不存在',
    'upload_file_type_error' => '10017|上传文件格式错误',
    'upload_file_local_close' => '10018|本地上传文件已经关闭',
    'export_time_out_31' => '10019|导出时间不能超过31天',
    'export_time_must' => '10020|导出时必须选择时间',
    'captcha_type' => '10021|验证码类型错误',
    'mobile_format' => '10022|手机号码格式错误',
    'sms_captcha_error' => '10023|手机验证码错误',
    'sms_captcha_time_out' => '10024|手机验证已经过期',
    'sms_frequent' => '10025|短信发送太频繁',
    'sms_send_fail' => '10026|短信发送失败',
    'sms_error_num_max' => '10027|错误次数已达上限，请五分钟后再试',
    'file_not_exists' => '10028|文件不存在',
    'file_type_error' => '10029|文件格式错误',
    'adv_app_id_not_empty' => '10030|跳转第三方的小程序appid不能为空',
    'export_is_in_run' => '10031|其他导出任务正在进行中，请稍后重试',

    //管理员10500
    'admin_user_error' => '10500|用户名错误',
    'admin_password_error' => '10501|密码错误',
    'admin_password_empty' => '10502|密码不能为空',
    'admin_in_blacklist' => '10503|用户被锁定，请联系管理员',
    'admin_role_no_del' => '10504|超级权限禁止删除',
    //设置10700
    'category_child_no_empty' => '10700|该分类存在下级分类，不能删除',
    'menu_child_no_empty' => '10701|该菜单存在下级分类，不能删除',


    //用户20000
    'user_error' => '20000|用户不存在',
    'user_freeze' => '20001|用户已经被冻结',
    'user_blacklist' => '20003|用户已经被加入黑名单',

    //订单30000
    'payment_error' => '30000|支付方式错误',
    'order_error' => '30001|订单错误',
    'order_status_error' => '30002|订单状态错误',
    'order_delivery_price_real_error' => '30003|订单运费金额不能小于0元',
    'order_goods_error' => '30004|订单商品不存在',
    'express_company_error' => '30005|物流公司信息错误',
    'delivery_address_error' => '30006|发货地址不存在',
    'delivery_code_error' => '30007|物流单号不能为空',

    //售后30500
    'refund_status_error' => '30500|售后状态错误',
    'refund_address_error' => '30501|退货地址不存在',
    'refund_amount_fail' => '30502|打款失败，请查看具体错误',

    //商品40000
    'goods_shelves_status_fail' => '50001|操作失败，注意商品状态必须是已审核',
    'goods_coupon_error' => '50002|优惠券信息错误',
    'goods_not_exists' => '50003|商品不存在',
    'goods_is_bind_promotion' => '50004|商品已经绑定了其他活动',
    'goods_stock_error' => '50005|商品库存不足',
    'delivery_weight_min_one' => '50005|首重/续重/件数最小1',

    //促销50000
    //优惠券50000
    'coupons_not_exists' => '50000|优惠券不存在',
    'coupons_at_error' => '50001|有效天数和开始结束时间不能都为空',
    'coupons_overdue' => '50002|优惠券已经过期',
    'coupons_status_error' => '50003|优惠券状态错误',
    'coupons_pct_error' => '50004|折扣值只能是1-100的整数',
    'coupons_max_100' => '50005|一次最多只能生成100张',
    //促销活动50100
    'promotion_end' => '50100|优惠活动已经过期',
    'promotion_pct_error' => '50101|活动折扣值只能是1-100的整数',
    'promotion_point_error' => '50102|赠送积分必须大于等于0',
    'promotion_coupons_id_error' => '50103|优惠券id不能为空',

    //秒杀活动50200
    //套餐包50300
    'package_not_exists' => '50300|优惠券不存在',
    'package_status_error' => '50301|优惠券状态错误',


    //财务60000
    //交易单60000
    'trade_error' => '60000|交易单错误',
    'trade_status_error' => '60001|交易单状态错误',
    'trade_is_refund' => '60002|交易单存在已经退款情况，不允许直接退款',
    //资金60100
    'balance_event_error' => '60100|资金类型错误',
    'balance_insufficient' => '60101|余额不足',
    'recharge_error' => '60102|充值单信息错误',
    'recharge_status_error' => '60103|充值单状态错误',
    'recharge_not_balance_pay' => '60104|余额充值不支持余额支付',

    //积分60200
    'point_event_error' => '60200|资金类型错误',
    'point_insufficient' => '60201|余额不足',

    //商家70000
    'default_seller_no_del' => '70000|默认商家禁止删除',
    'seller_is_must' => '70001|商家信息不存在',

    //第三方调用的70200
    'mini_program_qrcode_error' => '70200|小程序生成失败',

];
