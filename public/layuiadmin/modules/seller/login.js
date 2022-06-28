/**
 * 登录
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:45 PM
 */

layui.define(['my_hash'], function (exports) {
    let $ = layui.$,
        layer = layui.layer,
        setter = layui.setter,
        form = layui.form,
        common = layui.common,
        my_hash = layui.my_hash;

    form.render();

    let token = layui.data(setter.tableName)[setter.request.tokenName];
    //存在token的时候先验证token
    if (token) {
        let result = common.ajax('/seller/seller/info');
        if (result) {
            //登入成功的提示与跳转
            layer.msg('已经登录，即将跳转到首页', {
                offset: '15px',
                icon: 1,
                time: 1000
            }, function () {
                location.href = 'index.html'; //后台主页
            });
        }
    }
    //提交
    form.on('submit(login-submit)', function (obj) {
        obj.field.password = my_hash.md5(obj.field.password);
        let result = common.ajax('/login', obj.field);
        if (result) {
            //判断是否需要手机验证码
            if (result.data.sms_captcha) {
                $('.sms_captcha').removeClass('layui-hide');
                return false;
            }
            //请求成功后，写入 access_token
            layui.data(setter.tableName, {
                key: setter.request.tokenName,
                value: result.data.token
            });
            layui.data(setter.tableName, {
                key: 'username',
                value: result.data.username
            });
            layui.data(setter.tableName, {
                key: 'button',
                value: result.data.button
            });
            layui.data(setter.tableName, {
                key: 'role_id',
                value: result.data.role_id
            });
            //记录token过期时间
            let expire = new Date().getTime() / 1000;
            layui.data(setter.tableName, {
                key: 'expire',
                value: Number(expire) + Number(result.data.expire)
            });
            //登入成功的提示与跳转
            layer.msg('登入成功', {
                offset: '15px',
                icon: 1,
                time: 1000
            }, function () {
                location.href = 'index.html';//后台主页
            });
        } else {
            get_code();
        }
    });

    //获取验证码
    function get_code() {
        let result = common.ajax('/login/captcha');
        if (result) {
            $('#LAY-user-get-vercode').attr('src', result.data.img);
            $('[name="captcha_key"]').val(result.data.key);
        }
    }

    get_code();
    //更换图形验证码
    $('body').on('click', '#LAY-user-get-vercode', function () {
        get_code();
    });

    //获取短信验证码
    form.on('submit(sms_captcha)', function (obj) {
        let timeInt = 60;
        let timeFunc;
        obj.field.password = my_hash.md5(obj.field.password);
        let result = common.ajax('/login/sms_captcha', obj.field);
        if (result) {
            if (result.data.sms_captcha) {
                layer.alert('短信验证码不能为空');
                return false;
            }
            $(".sms_captcha_button").addClass("layui-btn-disabled");
            timeFunc = setInterval(function () {
                --timeInt;
                $(".sms_captcha_button").html(timeInt + "s");
                if (timeInt <= 0) {
                    clearInterval(timeFunc);
                    timeInt = 60;
                    $(".sms_captcha_button").html("获取验证码");
                    $(".sms_captcha_button").removeClass("layui-btn-disabled");
                }
            }, 1000);
            layer.alert('短信已发送到' + result.data.mobile + ',请查收!')
        } else {
            get_code();
        }
    });

    //对外暴露的接口
    exports('login', {});
});
