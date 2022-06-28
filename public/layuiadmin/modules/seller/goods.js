/**
 * 商品
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:45 PM
 */

layui.define(['table_common'], function (exports) {
    let admin = layui.admin,
        view = layui.view,
        $ = layui.$,
        table_common = layui.table_common,
        common = layui.common,
        form = layui.form,
        tree = layui.tree,
        element = layui.element,
        table = layui.table,
        model_url = '/goods/goods';

    table_common.set_model_url(model_url);//设置默认模块地址

    table.render({
        elem: '#table_list',
        url: layui.setter.apiHost + model_url,
        toolbar: '#toolbar',
        cols: [[
            {type: 'checkbox'},
            {field: 'image', title: '图片', width: 90, toolbar: '#img_tmp'},
            {field: 'title', title: '商品名称', minWidth: 250, toolbar: '#title_tmp'},
            {field: 'category_name', title: '分类', width: 100},
            {field: 'type', title: '类型', width: 80, align: 'center'},
            {field: 'sell_price', title: '价格', width: 80},
            {field: 'shelves_status', title: '上架', width: 80, align: 'center', templet: '#shelves_status_tmp'},
            {field: 'status', title: '状态', width: 80, align: 'center', templet: '#status_tmp'},
            {title: '操作', width: 150, align: 'center', toolbar: '#action_button'}
        ]],
        page: true,
        done: function () {
            table_common.set_button(model_url);
        }
    });

    //表格回调
    let table_callback = {
        //添加
        add: function (data) {
            select_category();
        },
        //上架
        shelves_status_on: function (data) {
            common.action_ajax('shelves_status', {status: 1});
        },
        //下架
        shelves_status_off: function (data) {
            common.action_ajax('shelves_status', {status: 0});
        },
        //回收站
        recycle: function (data) {
            common.open_iframe('商品回收站', '../goods/recycle.html');
        },
        //小程序码
        qrcode: function (data) {
            let result = common.ajax(model_url + '/qrcode', {id: data.id});
            if (result) {
                window.open(result.data.mini_qrcode);
            } else {
                layer.msg(result.msg);
            }
        },
    }
    table_common.set_callback_obj(table_callback);

    //添加商品前选择分类
    function select_category() {
        let view_category_url = layui.setter.viewsDir + model_url + '/' + 'select_category';
        admin.popup({
            title: '选择分类',
            area: ['100%', '100%'],
            id: new Date().getTime(),
            success: function (layero, category_index) {
                view(this.id).render(view_category_url).done(function () {
                    let result = common.ajax('/goods/category/select_all', {parent_id: 0});
                    if (result) {
                        tree.render({
                            elem: '#category_id',
                            data: result.data,
                            click: function (obj) {
                                let goods_type = $('input:radio[name="type"]:checked').val();
                                let goods_type_title = $('input:radio[name="type"]:checked').attr('title');
                                if (!obj.data.children) {
                                    layer.confirm('确认选择“' + obj.data.title + '”分类', function (confirm_index) {
                                        layer.close(category_index);//关闭分类选择页
                                        layer.close(confirm_index);
                                        let default_data = {category_id: obj.data.id, category_title: obj.data.title, type: goods_type, type_title: goods_type_title, sku_code: Date.parse(new Date())};
                                        common.open_edit('添加', 'add', default_data);
                                    });
                                }
                            }
                        });
                    }
                });
            }
        });
    }

    //定模快捷切换选项
    element.on('tab(goods_table_tab)', function (data) {
        //重置搜索条件
        $('.search_from input,select').each(function () {
            $(this).val('');
        })
        form.render();
        //重载表格
        table.reload('table_list', {
            where: {shelves_status: this.getAttribute('lay-id')},
            page: {
                curr: 1//重新从第 1 页开始
            }
        });
    });
    //对外暴露的接口
    exports('goods', {});
});
