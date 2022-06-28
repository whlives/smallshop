<?php

use App\Http\Middleware\SellerToken;
use Illuminate\Support\Facades\Route;

Route::post('login', 'LoginController@index');//登录
Route::post('login/captcha', 'LoginController@captcha');//图像验证码
Route::post('login/sms_captcha', 'LoginController@smsCaptcha');//短信验证码

//验证token
Route::group(['middleware' => SellerToken::class], function () {
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
     ***************设置模块*******************
     */
    Route::group(['prefix' => 'system', 'namespace' => 'System'], function () {
        //品牌
        Route::prefix('brand')->controller('BrandController')->group(function () {
            Route::post('select', 'select');
        });
        //快递公司
        Route::prefix('express_company')->controller('ExpressCompanyController')->group(function () {
            Route::post('select', 'select');
        });
    });

    /**
     ***************会员模块*******************
     */
    Route::group(['prefix' => 'member', 'namespace' => 'Member'], function () {
        Route::prefix('group')->controller('GroupController')->group(function () {
            Route::post('select', 'select');
        });
    });

    /**
     ***************商家模块*******************
     */
    Route::group(['prefix' => 'seller', 'namespace' => 'Seller'], function () {
        //商家
        Route::prefix('seller')->controller('SellerController')->group(function () {
            Route::post('info', 'info');
            Route::post('info_update', 'infoUpdate');
        });
        //商家地址
        Route::prefix('address')->controller('AddressController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('select', 'select');
        });
        //商家分类
        Route::prefix('category')->controller('CategoryController')->group(function () {
            Route::post('/', 'index');
            Route::post('detail', 'detail');
            Route::post('save', 'save');
            Route::post('delete', 'delete');
            Route::post('status', 'status');
            Route::post('select_all', 'selectAll');
        });
    });

    /**
     ***************商品模块*******************
     */
    Route::group(['prefix' => 'goods', 'namespace' => 'Goods'], function () {
        //分类
        Route::prefix('category')->controller('CategoryController')->group(function () {
            Route::post('select_all', 'selectAll');
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
            Route::post('delete', 'delete');
            Route::post('type', 'type');
            Route::post('get_attribute', 'getAttribute');
            Route::post('get_spec', 'getSpec');
            Route::post('coupons', 'coupons');
            Route::post('delivery', 'delivery');
            Route::post('qrcode', 'qrcode');
        });
        //商品回收站
        Route::prefix('recycle')->controller('RecycleController')->group(function () {
            Route::post('/', 'index');
            Route::post('restore', 'restore');
            Route::post('delete', 'delete');
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
            Route::post('delivery', 'delivery');
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
        //商家资金
        Route::prefix('balance')->controller('BalanceController')->group(function () {
            Route::post('', 'index');
            Route::post('type', 'type');
            Route::post('withdraw', 'withdraw');
        });
        //商家资金提现
        Route::prefix('withdraw')->controller('WithdrawController')->group(function () {
            Route::post('', 'index');
            Route::post('get_status', 'getStatus');
        });
    });

});