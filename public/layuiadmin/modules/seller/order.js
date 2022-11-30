/**
 * 订单
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:45 PM
 */

layui.define(['table_common'], function (exports) {
    let $ = layui.$,
        table_common = layui.table_common,
        common = layui.common,
        form = layui.form,
        element = layui.element,
        table = layui.table,
        laydate = layui.laydate,
        model_url = '/order/order';

    table_common.set_model_url(model_url);//设置默认模块地址

    table.render({
        elem: '#table_list',
        url: layui.setter.apiHost + model_url,
        toolbar: '#toolbar',
        cols: [[
            {type: 'checkbox', style: 'background-color: #FFFFFF;'},
            {field: 'id', title: '商品', minWidth: 550, toolbar: '#goods_tmp', style: 'background-color: #FFFFFF;'},
            {field: 'subtotal', title: '支付金额', width: 100, style: 'background-color: #FFFFFF;'},
            {field: 'full_name', title: '收货人', width: 100, style: 'background-color: #FFFFFF;'},
            {field: 'status_text', title: '状态', width: 80, align: 'center', style: 'background-color: #FFFFFF;'},
            {title: '操作', width: 150, toolbar: '#action_button', style: 'background-color: #FFFFFF;'},
            {field: 'payment', title: '支付方式', width: 100, style: 'background-color: #FFFFFF;'},
            {field: 'username', title: '用户名', width: 120, style: 'background-color: #FFFFFF;'},
        ]],
        page: true,
        done: function () {
            table_common.set_button(model_url);
        }
    });

    //表格回调
    let table_callback = {
        //电子面单
        delivery: function (data) {
            let ids = data ? data.id : common.get_check_id('table_list');
            common.detail('批量发货(电子面单)', 'batch_delivery', {id: ids});
        },
        //打印发货单
        print_goods: function (data) {
            let ids = data ? data.id : common.get_check_id('table_list');
            common.open_iframe('批量打印发货单', '../order/print_goods.html?id=' + ids);
        },
        //打印快递单
        print_delivery: function (data) {
            let ids = data ? data.id : common.get_check_id('table_list');
            common.open_iframe('批量打印快递单', '../order/print_delivery.html?id=' + ids)
        },
        //订单详情
        detail: function (data) {
            common.detail('订单详情', 'detail', {id: data.id});
        },
        //改价
        update_price: function (data) {
            let set_data = {
                detail_url: '/get_price',//获取数据地址
                save_url: '/update_price',//保存地址
            }
            common.open_edit('改价', 'update_price', {id: data.id}, '500px', set_data);
        },
        //修改地址
        update_address: function (data) {
            let set_data = {
                detail_url: '/get_address',//获取数据地址
                save_url: '/update_address',//保存地址
            }
            common.open_edit('修改地址', 'update_address', {id: data.id}, '500px', set_data);
        },
    }
    table_common.set_callback_obj(table_callback);

    //日期
    laydate.render({
        elem: '#time_range',
        range: '~',
        type: 'date'
    });

    //定模快捷切换选项
    element.on('tab(order_table_tab)', function (data) {
        //重置搜索条件
        $('.search_from input,select').each(function () {
            $(this).val('');
        })
        form.render();
        //重载表格
        table.reload('table_list', {
            where: {delivery: this.getAttribute('lay-id')},
            page: {
                curr: 1//重新从第 1 页开始
            }
        });
    });
    //对外暴露的接口
    exports('order', {});
});
