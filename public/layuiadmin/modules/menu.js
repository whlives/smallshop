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
        laytpl = layui.laytpl,
        common = layui.common,
        incr = 'icon-jiahaozhankai',
        decr = 'icon-jianhaoshouqi',
        model_url = '';

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
            $(this).find('.children').removeClass(show ? incr : decr).addClass(show ? decr : incr);
            if (!$(this).hasClass('menu_id') && i > 0) {
                show ? $(this).show() : $(this).hide();//这里一级不能隐藏
            }
        });
    }

    //折叠或展开下级
    $('.layui-form').on('click', '.children', function () {
        //切换图标
        $(this).toggleClass(function () {
            if ($(this).hasClass(decr)) {
                $(this).removeClass(decr);
                return incr;
            } else {
                $(this).removeClass(incr);
                return decr;
            }
        });
        //展开或折叠下级
        let id = $(this).parent().parent().attr('data-id');
        if ($(this).hasClass(incr)) {
            fold(id);
        } else {
            expansion(id);
        }
    });

    //折叠
    function fold(id) {
        $('.menu_id_' + id).hide().each(function (i) {
            fold($(this).attr('data-id'));
        })
    }

    //展开
    function expansion(id) {
        $('.menu_id_' + id).show().each(function (i) {
            let child_id = $(this).attr('data-id');
            if ($(this).find('.children').hasClass(incr)) {
                fold(child_id);
            } else {
                expansion(child_id);
            }
        })
    }

    /**
     * 循环菜单内容
     * @param data
     * @param hierarchy 层级
     */
    function menu_tpl(data, hierarchy) {
        $.each(data, function (index, item) {
            item.hierarchy = hierarchy;
            laytpl($('#menu_list_tpl').html()).render(item, function (html) {
                $('#menu_list').append(html);
            })
            if (item.children) {
                menu_tpl(item.children, (hierarchy + 1));
            }
        })
    }

    let menu = {
        /**
         * 初始化模块
         * @param url 前缀地址
         */
        init: function (url) {
            model_url = url;
            common.set_model_url(url);
            common.ajax(model_url, {}, function (res) {
                menu_tpl(res.data, 0)
                layui.form.render();//初始化表单
                common.set_button(model_url);//设置按钮权限
            })
        },
    }

    //对外暴露的接口
    exports('menu', menu);
});
