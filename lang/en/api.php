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

    //用户20000
    'user_error' => '20000|用户不存在',
    'user_freeze' => '20001|用户已经被冻结',
    'user_blacklist' => '20002|用户已经被加入黑名单',
    'user_is_login' => '20003|用户已经登录',
    'user_no_login' => '20004|用户未登录',
    'password_error' => '20005|用户名或密码错误',
    'old_password_error' => '20006|用户原密码错误',
    'old_pay_password_error' => '20007|原支付密码错误',
    'pay_password_notset' => '20008|支付密码未设置',
    'pay_password_isset' => '20009|已经设置过支付密码',
    'pay_password_error' => '20010|支付密码错误',
    'user_is_repeat' => '20011|用户已经存在',
    'user_mobile_error' => '20012|用户手机号码错误',
    'auth_type_error' => '20013|第三方类型错误',
    'auth_data_error' => '20014|第三方数据错误',
    'user_mobile_is_bind' => '20015|手机号已经绑定其他账号',
    'user_mobile_bind_fail' => '20016|手机号绑定失败',
    'user_mobile_get_fail' => '20017|手机号获取失败',

    //订单30000
    //支付
    'payment_error' => '30000|支付方式错误',
    'payment_openid_error' => '30001|用户openid错误',
    'payment_alipay_return_url_error' => '30002|支付宝网页支付必须设置回调地址',
    'payment_alipay_quit_url_error' => '30003|支付宝H5支付必须设置中途退出返回商户网站的地址',
    //订单30100
    'order_error' => '70001|订单不存在',
    'order_status_error' => '70002|订单状态错误',
    'order_pay_status_error' => '70003|订单已经支付或取消',
    'order_delivery_price_real_error' => '70004|订单运费金额不能小于0元',
    'order_goods_error' => '70005|订单商品不存在',
    'order_submit_fail' => '70006|订单提交失败',
    'address_not_exists' => '70007|地址信息错误',
    'promo_goods_not_cart' => '70008|活动商品和卡券类商品不支持购物车下单',
    'goods_is_update' => '70009|商品信息已经发生变化，请重新提交',
    'delivery_error' => '70010|配送方式错误，请重新选择',
    'goods_can_not_delivery' => '70011|存在不可配送的商品',
    'cart_goods_not_exists' => '70012|购物车商品不存在',
    'seckill_error' => '70013|秒杀信息错误',
    'seckill_not_start' => '70014|秒杀还未开始',
    'seckill_is_end' => '70015|秒杀已经结束',
    'seckill_status_error' => '70016|秒杀活动状态错误',
    'group_error' => '70017|拼团信息错误',
    'group_status_error' => '70018|拼团活动状态错误',
    'group_is_success' => '70019|该团已经成团',
    'group_order_id_error' => '70020|参团id错误',
    'group_not_start' => '70021|拼团活动还未开始',
    'group_is_end' => '70022|拼团活动已经结束',
    'invoice_title_error' => '70023|发票抬头不能为空',
    'invoice_tax_no_error' => '70024|纳税人识别号不能为空',
    'evaluation_level_error' => '70025|评价等级只能是1-5',
    'order_refund_ing_not_cancel' => '70026|订单存在售后信息不能直接取消',

    //售后30500
    'refund_error' => '70018|售后信息错误',
    'refund_status_error' => '70018|售后状态错误',
    'refund_complete' => '70018|售后已经完成',
    'refund_time_out' => '70019|商品已经过了售后时间，请联系客服',
    'refund_replace_complete' => '70020|已经换货的不能再次申请，请联系客服',
    'refund_wait_audit' => '70021|售后处理中',
    'refund_amount_error' => '70022|售后金额不能大于最大金额',
    'express_company_error' => '70023|物流公司信息错误',
    'refund_no_delivery_select_money' => '70024|未发货的商品只能申请仅退款',

    //商品40000
    'search_key_and_category_error' => '40000|关键字和分类不能都为空',
    'search_goods_max_page' => '40001|商品搜索分页最大100页',
    //购物车商品商品40100
    'tip_goods_no_shelves' => '商品已下架',//购物车错误提示不需要编码
    'tip_goods_stock_no_enough' => '库存不足',//购物车错误提示不需要编码
    'tip_goods_min_buy_qty_error' => '最少需要订购',//购物车错误提示不需要编码
    'tip_goods_max_buy_qty_error' => '最多只能订购',//购物车错误提示不需要编码
    'delivery_can_not' => '不在配送范围内',//购物车错误提示不需要编码
    'goods_error' => '40100|商品不存在',
    'goods_shelves_status_error' => '40101|商品已下架',
    'goods_sku_error' => '40102|SKU商品不存在',
    'goods_sku_status_error' => '40103|商品已失效',
    'goods_min_buy_qty_error' => '40104|商品购买件数少于最少购买数量',
    'goods_max_buy_qty_error' => '40105|商品购买件数大于最多购买数量',
    'goods_stock_no_enough' => '40106|商品库存不足',
    'cart_goods_error' => '40107|购物车商品不存在',
    'goods_not_join_cart' => '40108|该商品不支持加入购物车',
    'buy_qty_error' => '40109|商品购买数量错误',

    //促销50000
    //优惠券50000
    'coupons_not_exists' => '50001|优惠券不存在',
    'coupons_overdue' => '50002|优惠券已经过期',
    'coupons_is_use' => '50003|优惠券已使用',
    'coupons_obtain_max' => '50004|已经领取过该优惠券',//超过数量
    'coupons_no_use' => '50004|优惠券不可使用',
    'coupons_buy_max' => '50005|该优惠券购买数量超过限制',//超过数量
    'coupons_limit_max' => '50006|优惠券限制领取张数',//超过数量
    //促销活动50100
    'promotion_end' => '50100|优惠活动已经过期',
    'promotion_pct_error' => '50101|活动折扣值只能是1-100的整数',
    'promotion_point_error' => '50102|赠送积分必须大于等于0',
    'promotion_coupons_id_error' => '50103|优惠券id不能为空',

    //财务60000
    //交易单60000
    'trade_error' => '60000|交易单错误',
    'trade_status_error' => '60001|交易单状态错误',
    'trade_submit_fail' => '60002|交易单创建失败，请进入我们的订单查看',
    'trade_create_fail' => '60003|交易单创建失败',
    'trade_order_info_error' => '60004|订单信息获取失败',
    'trade_not_pay' => '60005|交易单还未支付',
    //资金60100
    'balance_event_error' => '60100|资金类型错误',
    'balance_insufficient' => '60101|余额不足',
    'recharge_error' => '60102|充值单信息错误',
    'recharge_status_error' => '60103|充值单状态错误',
    'recharge_not_balance_pay' => '60104|余额充值不支持余额支付',
    'withdraw_balance_amount_max_500' => '60105|微信单次提现金额不能大于500',

    //积分60200
    'point_event_error' => '60200|资金类型错误',
    'point_insufficient' => '60201|余额不足',

    //商家70000
    'seller_error' => '70000|商家信息错误',


    //第三方调用的70200
    'mini_program_qrcode_error' => '70200|小程序生成失败',


];
