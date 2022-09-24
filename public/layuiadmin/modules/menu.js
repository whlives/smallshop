/**
 * 菜单/分类
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:45 PM
 */

layui.define(['common'], function (exports) {
    let $ = layui.$,
        layer = layui.layer,
        form = layui.form,
        common = layui.common,
        incr = 'icon-jiahaozhankai',
        decr = 'icon-jianhaoshouqi',
        model_url = '';

    //表格加载完成后操作
    layui.data.done = function (d) {
        layui.form.render();
        common.set_button(model_url);
    };

    //头部操作按钮
    $('.layui-card-header .layui-btn').on('click', function () {
        let type = $(this).attr('lay-event');
        switch (type) {
            case 'add':
                common.open_edit('添加', 'add', {parent_id: 0});
                break;
            case 'all_open':
                all_open_close(true);
                break;
            case 'all_close':
                all_open_close(false);
                break;
        }
    });

    //监听操作按钮
    $('.layui-form').on('click', '.layui-btn', function () {
        let type = $(this).attr('lay-event');
        let id = $(this).data('id');
        switch (type) {
            case 'delete':
                layer.confirm('确定删除吗', function (index) {
                    common.action_ajax('delete', {id: id}, function () {
                        $('#row_menu_id_' + id).remove();
                        layer.close(index);
                    });
                });
                break;
            case 'edit':
                common.open_edit('编辑', 'add', {id: id});
                break;
            case 'add_menu':
                common.open_edit('添加', 'add', {parent_id: id});
                break;
        }
    });

    //监听锁定操作
    form.on('switch(status_btn)', function (obj) {
        let send_data = {id: this.value, status: obj.elem.checked == true ? 1 : 0};
        common.action_ajax('status', send_data, function () {
            layer.msg('操作成功');
        });
    });

    //全部展开或折叠
    function all_open_close(show) {
        $('.layui-form tr').each(function (i) {
            if (!$(this).hasClass('menu_id') && i > 0) {
                $('.layui-form .menu_id .children').removeClass(show ? incr : decr).addClass(show ? decr : incr);
                show ? $(this).show() : $(this).hide();
            }
        });
    }

    //隐藏展开下级
    $('.layui-form').on('click', '.children', function () {
        let menu_id = $(this).parent().parent().attr('data-id');
        $('.menu_id_' + menu_id).toggle();
        //动态更换图标
        $(this).toggleClass(function () {
            if ($(this).hasClass(decr)) {
                $(this).removeClass(decr);
                return incr;
            } else {
                $(this).removeClass(incr);
                return decr;
            }
        });
        //循环隐藏或展开下级
        $('.menu_id_' + menu_id).each(function (i) {
            if ($(this).css('display') == 'none') {
                $(this).find('.children').removeClass(decr).addClass(incr);
                $('.menu_id_' + $(this).attr('data-id')).hide();
            } else {
                $(this).find('.children').removeClass(incr).addClass(decr);
                $('.menu_id_' + $(this).attr('data-id')).show();
            }
        })
    })

    let menu = {
        /**
         * 设置模块默认前缀
         * @param url 前缀地址
         */
        set_model_url: function (url) {
            model_url = url;
            common.set_model_url(url);
        },
        /**
         * 设置按钮权限
         * @param model_url 模块
         */
        set_button: function (model_url) {
            common.set_button(model_url);
        }

    }

    //对外暴露的接口
    exports('menu', menu);
});
