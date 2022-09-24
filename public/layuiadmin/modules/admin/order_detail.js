/**
 * 订单
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:45 PM
 */

layui.define(function (exports) {
    let $ = layui.$,
        table = layui.table,
        form = layui.form,
        element = layui.element,
        laytpl = layui.laytpl,
        common = layui.common,
        params = '',
        model_url = '/order/order';

    common.set_model_url(model_url);//设置默认模块地址

    //定模快捷切换选项
    element.on('tab(order_detail_tab)', function (data) {
        let type = this.getAttribute('lay-id');
        switch (type) {
            case 'detail':
                get_detail();
                break;
            default:
                get_tab_list(type);
        }
        common.set_button(model_url);//设置按钮权限
    });

    //详情
    function get_detail() {
        common.ajax(model_url + '/detail', {id: params.id}, function (result) {
            laytpl($('#detail_tpl').html()).render(result.data, function (html) {
                $('#detail').html(html);
                form.render();
                common.set_button(model_url);//设置按钮权限
            })
        });
    }

    //tab切换数据请求
    function get_tab_list(type) {
        common.ajax(model_url + '/get_' + type, {order_id: params.id}, function (result) {
            laytpl($('#' + type + '_tpl').html()).render(result.data, function (html) {
                $('#' + type).html(html);
                //转化静态表格
                table.init('detail_' + type, {
                    escape: false,
                });
            })
        });
    }

    //监听支付提交
    form.on('submit(pay)', function (data) {
        layer.prompt({title: '请输入备注', formType: 2}, function (note, index) {
            let form_data = data.field;
            form_data['note'] = note;
            form_submit('pay', form_data);
            layer.close(index);
        });
        return false;
    });

    //监听取消提交
    form.on('submit(cancel)', function (data) {
        layer.prompt({title: '请输入备注', formType: 2}, function (note, index) {
            let form_data = data.field;
            form_data['note'] = note;
            form_submit('cancel', form_data);
            layer.close(index);
        });
        return false;
    });

    //监听发货提交
    form.on('submit(delivery)', function (data) {
        let form_data = data.field;
        form_data['order_goods_id']
        form_submit('delivery', form_data);
        return false;
    });

    //监听撤销发货提交
    form.on('submit(un_delivery)', function (data) {
        layer.prompt({title: '请输入备注', formType: 2}, function (note, index) {
            let form_data = data.field;
            form_data['note'] = note;
            form_submit('un_delivery', form_data);
            layer.close(index);
        });
        return false;
    });

    //监听订单确认
    form.on('submit(confirm)', function (data) {
        layer.prompt({title: '请输入备注', formType: 2}, function (note, index) {
            let form_data = data.field;
            form_data['note'] = note;
            form_submit('confirm', form_data);
            layer.close(index);
        });
        return false;
    });

    //监听订单完成
    form.on('submit(complete)', function (data) {
        layer.prompt({title: '请输入备注', formType: 2}, function (note, index) {
            let form_data = data.field;
            form_data['note'] = note;
            form_submit('complete', form_data);
            layer.close(index);
        });
        return false;
    });

    /**
     * 订单操作
     * @param type
     * @param form_data
     */
    function form_submit(type, form_data) {
        common.ajax(model_url + '/' + type, form_data, function () {
            layer.msg('操作成功', {time: 1000}, function () {
                get_detail();
            })
        });
    }

    let obj = {
        /**
         * 设置参数并初始化
         * @param data
         */
        set_params: function (data) {
            params = data;
            get_detail();
        },
    }

    //对外暴露的接口
    exports('order_detail', obj);
});
