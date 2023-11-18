/**
 * 扩展表单验证，增加验证类型和支持自定义提示文字
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:45 PM
 */

layui.define(function (exports) {
    let $ = layui.$,
        form = layui.form;

    //自定义验证
    form.verify({
        price: function (value, item) {
            if (!/(^[1-9]\d*(\.\d{1,2})?$)|(^0(\.\d{1,2})?$)/.test(value)) {
                let reqText = $(item).attr('lay-reqText');
                if (reqText) {
                    return reqText;
                } else {
                    return '价格格式错误';
                }
            }
        },
        password: function (value) {
            let id = $('#form input[name="id"]').val();//编辑的时候不强制
            let password = /^[\S]{6,12}$/;
            let r = value.match(password);
            if (r == null && !id) {
                return '密码必须6到12位，且不能出现空格';
            }
        },
        resspaword: function (value) {
            let pass = $('#form input[name="password"]').val();
            if (value != pass) {
                return '两次密码不一致';
            }
        }
    });

//对外输出
    exports('verify', {});
});
