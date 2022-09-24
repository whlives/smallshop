<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'IndexController@index');

//基础公共模块
Route::prefix('helper')->controller('HelperController')->group(function () {
    Route::get('adv/{code}', 'adv')->where('code', '[0-9]+');//广告位
    Route::get('express_company', 'expressCompany');//快递公司
    Route::get('area/{parent_id?}', 'area');//地区
    Route::get('wx_jssdk', 'wxJssdk');//获取微信jssdk信息
});
//文章
Route::prefix('article')->controller('ArticleController')->group(function () {
    Route::get('/{category_id}/{page?}/{limit?}', 'index');
    Route::get('detail/{id}', 'detail');
    Route::get('category/{parent_id?}', 'category');//分类
});
//品牌
Route::prefix('brand')->controller('BrandController')->group(function () {
    Route::get('/{page?}/{limit?}', 'index');
    Route::get('detail/{id}', 'detail');
});
//优惠券
Route::prefix('coupons')->controller('CouponsController')->group(function () {
    Route::get('seller/{seller_id}/{page?}/{limit?}', 'seller');
});
//商家
Route::prefix('seller')->controller('SellerController')->group(function () {
    Route::get('detail/{seller_id}', 'detail');
    Route::get('category/{seller_id}/{parent_id?}', 'category');
    Route::get('category_all/{seller_id}', 'categoryAll');
});
//商品
Route::prefix('goods')->controller('GoodsController')->group(function () {
    Route::get('category/{parent_id?}', 'category');
    Route::get('category_all', 'categoryAll');
    Route::get('search', 'search');
    Route::get('detail/{id}', 'detail');
    Route::get('comment/{id}/{page?}/{limit?}', 'comment');
});
//拼团
Route::prefix('promo')->controller('PromoController')->group(function () {
    Route::get('seckill/{page?}/{limit?}', 'seckill');
    Route::get('group/{page?}/{limit?}', 'group');
    Route::get('group_order/{goods_id}/{page?}/{limit?}', 'groupOrder');
    Route::get('group_order_detail/{group_order_id}/{page?}/{limit?}', 'groupOrderDetail');
});
//站外推送
Route::prefix('out_push')->controller('OutPushController')->group(function () {
    Route::post('/pay_notify/{payment_id}/{platform?}', 'payNotify')->where(['payment_id' => '[0-9]+']);
    Route::post('/delivery_notify', 'deliveryNotify');
});

//需要验证签名的
Route::group(['middleware' => \App\Http\Middleware\SignCheck::class], function () {
    //基础公共模块
    Route::prefix('helper')->controller('HelperController')->group(function () {
        Route::post('captcha', 'captcha');//验证码
        Route::post('aliyun_sts', 'aliyunSts');//阿里云上传
        Route::post('upload', 'upload');//本地上传
    });

    //登陆注册
    Route::prefix('login')->controller('LoginController')->group(function () {
        Route::post('/', 'index');//密码登陆
        Route::post('auth', 'auth');//第三方登陆
        Route::post('wechat', 'wechat');//微信公众号、开放平台登陆
        Route::post('mini_program', 'miniProgram');//小程序登陆
        Route::post('mini_program_bind_mobile', 'miniProgramBindMobile');//小程序绑定手机
        Route::post('out', 'out');//退出登录
        //需要验证码的
        Route::group(['middleware' => \App\Http\Middleware\CaptchaCheck::class], function () {
            Route::post('speed', 'speed');//验证码登陆
            Route::post('bind_mobile', 'bindMobile');//第三方登陆绑定手机
            Route::post('find_password', 'findPassword');//找回密码
        });
    });
    //需要验证token的
    Route::group(['middleware' => \App\Http\Middleware\ApiToken::class], function () {
        Route::post('login/refresh_token', 'LoginController@refreshToken');//刷新token
        //购物车
        Route::prefix('cart')->controller('CartController')->group(function () {
            Route::post('/', 'index');
            Route::post('add', 'add');
            Route::post('edit', 'edit');
            Route::post('delete', 'delete');
            Route::post('clear', 'clear');
        });
        //订单
        Route::prefix('order')->controller('OrderController')->group(function () {
            Route::post('get_price', 'getPrice');
            Route::post('confirm_price', 'confirmPrice');
            Route::post('confirm', 'confirm')->middleware(\App\Http\Middleware\ApiPostRepeat::class);
            Route::post('submit', 'submit')->middleware(\App\Http\Middleware\ApiPostRepeat::class);
        });
        //支付信息
        Route::prefix('pay')->controller('PayController')->group(function () {
            Route::post('payment', 'payment');
            Route::post('pay_data', 'payData')->middleware(\App\Http\Middleware\ApiPostRepeat::class);
            Route::post('trade_status', 'tradeStatus');
        });

        //会员中心
        Route::group(['prefix' => 'member', 'namespace' => 'Member'], function () {
            //我的
            Route::controller('IndexController')->group(function () {
                Route::post('/', 'index');
                Route::post('info', 'info');
                Route::post('save_info', 'saveInfo');
                Route::post('up_password', 'upPassword');
                Route::post('set_pay_password', 'setPayPassword');
                Route::post('up_pay_password', 'upPayPassword');
                Route::post('reset_pay_password', 'resetPayPassword');
                Route::post('remove_auth_bind', 'removeAuthBind'); //第三方登录解除绑定
            });
            //订单
            Route::prefix('order')->controller('OrderController')->group(function () {
                Route::post('/', 'index');
                Route::post('detail', 'detail');
                Route::post('cancel', 'cancel');
                Route::post('confirm', 'confirm');
                Route::post('delivery', 'delivery');
                Route::post('comment', 'comment');
                Route::post('comment_put', 'commentPut');
                Route::post('delete', 'delete');
            });
            //售后
            Route::prefix('refund')->controller('RefundController')->group(function () {
                Route::post('/', 'index');
                Route::post('detail', 'detail');
                Route::post('log', 'log');
                Route::post('apply', 'apply');
                Route::post('apply_put', 'applyPut')->middleware(\App\Http\Middleware\ApiPostRepeat::class);
                Route::post('delivery', 'delivery');
                Route::post('delivery_log', 'deliveryLog');
                Route::post('cancel', 'cancel');
                Route::post('confirm', 'confirm');
                Route::post('delete', 'delete');
            });
            //地址
            Route::prefix('address')->controller('AddressController')->group(function () {
                Route::post('/', 'index');
                Route::post('save', 'save');
                Route::post('detail', 'detail');
                Route::post('delete', 'delete');
                Route::post('default', 'default');
            });
            //收藏
            Route::prefix('favorite')->controller('FavoriteController')->group(function () {
                Route::post('goods', 'goods');
                Route::post('seller', 'seller');
                Route::post('article', 'article');
                Route::post('set', 'set');
            });
            //余额
            Route::prefix('balance')->controller('BalanceController')->group(function () {
                Route::post('/', 'index');
                Route::post('detail', 'detail');
                Route::post('recharge', 'recharge');
                Route::post('withdraw', 'withdraw');
                Route::post('withdraw_log', 'withdrawLog');
            });
            //积分
            Route::prefix('point')->controller('PointController')->group(function () {
                Route::post('/', 'index');
                Route::post('detail', 'detail');
            });
            //优惠券
            Route::prefix('coupons')->controller('CouponsController')->group(function () {
                Route::post('obtain', 'obtain');
                Route::post('is_use', 'isUse');
                Route::post('normal', 'normal');
                Route::post('overdue', 'overdue');
            });
            //评价
            Route::prefix('comment')->controller('CommentController')->group(function () {
                Route::post('/', 'index');
            });
        });
    });

});
