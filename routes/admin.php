<?php

use App\Http\Middleware\AdminToken;
use Illuminate\Support\Facades\Route;

Route::post('login', 'LoginController@index');//登录
Route::post('login/captcha', 'LoginController@captcha');//图像验证码
Route::post('login/sms_captcha', 'LoginController@smsCaptcha');//短信验证码
//验证token
Route::group(['middleware' => AdminToken::class], function () {
    Route::post('refresh_token', 'LoginController@refreshToken');
    Route::post('logout', 'LoginController@logOut');
    Route::post('main', 'IndexController@main');
    Route::post('left_menu', 'IndexController@leftMenu');
    Route::prefix('helper')->controller('HelperController')->group(function () {
        Route::post('upload', 'upload');//本地上传文件
        Route::post('aliyun_sts', 'aliyunSts');//阿里云oss sts信息
        Route::post('area', 'area');//获取地区
    });
    /**
     ***************管理员模块*******************
     */
    Route::group(['prefix' => 'admin', 'namespace' => 'Admin'], function () {
        Route::prefix('admin')->controller('AdminController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('status', 'status');
            Route::post('delete', 'delete');
            Route::post('info', 'info');
            Route::post('info_update', 'infoUpdate');
        });
        //角色
        Route::prefix('role')->controller('RoleController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('status', 'status');
            Route::post('delete', 'delete');
            Route::post('select', 'select');
        });
        //权限码
        Route::prefix('right')->controller('RightController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('status', 'status');
            Route::post('delete', 'delete');
            Route::post('rights', 'rights');
            Route::post('routes', 'routes');//获取路由
        });
    });
    /**
     ***************设置模块*******************
     */
    Route::group(['prefix' => 'system', 'namespace' => 'System'], function () {
        //基本设置
        Route::prefix('config')->controller('ConfigController')->group(function () {
            Route::post('/', 'index');
            Route::post('update', 'update');
            Route::post('save', 'save');
        });
        //菜单
        Route::prefix('menu')->controller('MenuController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('select_all', 'selectAll');
            Route::post('select', 'select');
        });
        //快递公司
        Route::prefix('express_company')->controller('ExpressCompanyController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('field_update', 'fieldUpdate');
            Route::post('select', 'select');
        });
        //支付方式
        Route::prefix('payment')->controller('PaymentController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('field_update', 'fieldUpdate');
            Route::post('client_type', 'getClientType');
        });
        //品牌
        Route::prefix('brand')->controller('BrandController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('field_update', 'fieldUpdate');
            Route::post('select', 'select');
        });
        //商家后台菜单
        Route::prefix('menu_seller')->controller('MenuSellerController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
        });
        //短信模板
        Route::prefix('sms_template')->controller('SmsTemplateController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
        });
    });
    /**
     ***************日志模块*******************
     */
    Route::group(['prefix' => 'log', 'namespace' => 'Log'], function () {
        //管理员登录日志
        Route::post('admin_login', 'AdminLoginController@index');
        Route::post('admin_login/login_out', 'AdminLoginController@loginOut');
        //管理员操作日志
        Route::post('admin', 'AdminController@index');
        //会员登录日志
        Route::post('member_login', 'MemberLoginController@index');
        Route::post('member_login/login_out', 'MemberLoginController@index');
        //短信记录
        Route::post('sms', 'SmsController@index');
        //附件记录
        Route::post('file', 'FileController@index');
        Route::post('file/save', 'FileController@save');
        //商家登录记录
        Route::post('seller_login', 'SellerLoginController@index');
        Route::post('seller_login/login_out', 'SellerLoginController@loginOut');
    });
    /**
     ***************工具模块*******************
     */
    Route::group(['prefix' => 'tool', 'namespace' => 'Tool'], function () {
        //文章分类
        Route::prefix('category')->controller('CategoryController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('select_all', 'selectAll');
        });
        //文章
        Route::prefix('article')->controller('ArticleController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('field_update', 'fieldUpdate');
        });
        //广告位
        Route::prefix('adv_group')->controller('AdvGroupController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('select', 'select');
        });
        //广告
        Route::prefix('adv')->controller('AdvController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('field_update', 'fieldUpdate');
            Route::post('target_type', 'targetType');
        });
    });
    /**
     ***************会员模块*******************
     */
    Route::group(['prefix' => 'member', 'namespace' => 'Member'], function () {
        //会员组
        Route::prefix('group')->controller('GroupController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('select', 'select');
        });
        //会员
        Route::prefix('member')->controller('MemberController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('un_bind', 'unBind');
        });

    });
    /**
     ***************商品模块*******************
     */
    Route::group(['prefix' => 'goods', 'namespace' => 'Goods'], function () {
        //分类
        Route::prefix('category')->controller('CategoryController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('select_all', 'selectAll');
        });
        //规格
        Route::prefix('spec')->controller('SpecController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('field_update', 'fieldUpdate');
        });
        //规格值
        Route::prefix('spec_value')->controller('SpecValueController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('field_update', 'fieldUpdate');
        });
        //属性
        Route::prefix('attribute')->controller('AttributeController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('field_update', 'fieldUpdate');
        });
        //属性值
        Route::prefix('attribute_value')->controller('AttributeValueController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('field_update', 'fieldUpdate');
        });
        //配送方式
        Route::prefix('delivery')->controller('DeliveryController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
        });
        //商品
        Route::prefix('goods')->controller('GoodsController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('status', 'status');
            Route::post('shelves_status', 'shelvesStatus');
            Route::post('rem', 'rem');
            Route::post('delete', 'delete');
            Route::post('field_update', 'fieldUpdate');
            Route::post('type', 'type');
            Route::post('get_attribute', 'getAttribute');
            Route::post('get_spec', 'getSpec');
            Route::post('object', 'object');
            Route::post('delivery', 'delivery');
            Route::post('qrcode', 'qrcode');
        });
        //商品回收站
        Route::prefix('recycle')->controller('RecycleController')->group(function () {
            Route::post('/', 'index');
            Route::post('restore', 'restore');
            Route::post('delete', 'delete');
        });
        //套餐包
        Route::prefix('package')->controller('PackageController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
        });
        //套餐包商品
        Route::prefix('package_goods')->controller('PackageGoodsController')->group(function () {
            Route::post('/', 'index');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('search', 'search');
        });
    });
    /**
     ***************订单模块*******************
     */
    Route::group(['prefix' => 'order', 'namespace' => 'Order'], function () {
        //订单
        Route::prefix('order')->controller('OrderController')->group(function () {
            Route::post('/', 'index');
            Route::post('get_status', 'getStatus');
            Route::post('detail', 'detail');
            Route::post('get_delivery', 'getDelivery');
            Route::post('get_log', 'getLog');
            Route::post('get_refund', 'getRefund');
            Route::post('get_price', 'getPrice');
            Route::post('update_price', 'updatePrice');
            Route::post('get_address', 'getAddress');
            Route::post('update_address', 'updateAddress');
            Route::post('pay', 'pay');
            Route::post('cancel', 'cancel');
            Route::post('delivery', 'delivery');
            Route::post('un_delivery', 'unDelivery');
            Route::post('confirm', 'confirm');
            Route::post('complete', 'complete');
            Route::post('batch_delivery_list', 'batchDeliveryList');
            Route::post('batch_delivery_submit', 'batchDeliverySubmit');
            Route::post('print_goods', 'printGoods');
            Route::post('print_delivery', 'printDelivery');
        });
        //售后
        Route::prefix('refund')->controller('RefundController')->group(function () {
            Route::post('/', 'index');
            Route::post('get_status', 'getStatus');
            Route::post('detail', 'detail');
            Route::post('audit', 'audit');
            Route::post('refused', 'refused');
            Route::post('confirm_goods', 'confirmGoods');
            Route::post('refused_goods', 'refusedGoods');
            Route::post('send', 'send');
            Route::post('pay', 'pay');
        });
        //发货单
        Route::prefix('delivery')->controller('DeliveryController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
        });
    });
    /**
     ***************商家模块*******************
     */
    Route::group(['prefix' => 'seller', 'namespace' => 'Seller'], function () {
        //商家
        Route::prefix('seller')->controller('SellerController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('select', 'select');
        });
        //商家地址
        Route::prefix('address')->controller('AddressController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('select', 'select');
        });
    });
    /**
     ***************促销模块*******************
     */
    Route::group(['prefix' => 'market', 'namespace' => 'Market'], function () {
        //促销活动
        Route::prefix('promotion')->controller('PromotionController')->group(function () {
            Route::post('', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('get_type', 'getType');
        });
        //优惠券活动
        Route::prefix('coupons')->controller('CouponsController')->group(function () {
            Route::post('', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('select', 'select');
        });
        //优惠券规则
        Route::prefix('coupons_rule')->controller('CouponsRuleController')->group(function () {
            Route::post('', 'index');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('type', 'type');
            Route::post('in_type', 'inType');
            Route::post('search', 'search');
        });
        //优惠券明细
        Route::prefix('coupons_detail')->controller('CouponsDetailController')->group(function () {
            Route::post('', 'index');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
        });
        //秒杀活动
        Route::prefix('seckill')->controller('SeckillController')->group(function () {
            Route::post('', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('search', 'search');
            Route::post('sync_stock', 'syncStock');
        });
        //拼团活动
        Route::prefix('group')->controller('GroupController')->group(function () {
            Route::post('', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('search', 'search');
        });
    });
    /**
     ***************财务模块*******************
     */
    Route::group(['prefix' => 'financial', 'namespace' => 'Financial'], function () {
        //用户资金
        Route::prefix('balance')->controller('BalanceController')->group(function () {
            Route::post('', 'index');
            Route::post('batch_recharge', 'batchRecharge');
            Route::post('update', 'update');
            Route::post('detail', 'detail');
        });
        //用户积分
        Route::prefix('point')->controller('PointController')->group(function () {
            Route::post('', 'index');
            Route::post('batch_recharge', 'batchRecharge');
            Route::post('update', 'update');
            Route::post('detail', 'detail');
        });
        //用户提现
        Route::prefix('withdraw')->controller('WithdrawController')->group(function () {
            Route::post('', 'index');
            Route::post('get_status', 'getStatus');
            Route::post('audit', 'audit');
        });
        //商家资金
        Route::prefix('seller_balance')->controller('SellerBalanceController')->group(function () {
            Route::post('', 'index');
            Route::post('batch_recharge', 'batchRecharge');
            Route::post('update', 'update');
            Route::post('detail', 'detail');
        });
        //商家提现
        Route::prefix('seller_withdraw')->controller('SellerWithdrawController')->group(function () {
            Route::post('', 'index');
            Route::post('get_status', 'getStatus');
            Route::post('audit', 'audit');
        });
        //交易单
        Route::prefix('trade')->controller('TradeController')->group(function () {
            Route::post('', 'index');
            Route::post('get_status', 'getStatus');
            Route::post('get_type', 'getType');
            Route::post('refund', 'refund');
        });
        //退款单
        Route::prefix('trade_refund')->controller('TradeRefundController')->group(function () {
            Route::post('', 'index');
            Route::post('get_status', 'getStatus');
            Route::post('get_type', 'getType');
        });
    });

});
