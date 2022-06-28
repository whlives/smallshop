/**
 * 表单公共操作
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:45 PM
 */

layui.define(['common'], function (exports) {
    let $ = layui.$,
        layer = layui.layer,
        table = layui.table,
        form = layui.form,
        common = layui.common,
        setter = layui.setter,
        model_url = '',
        callback_obj;

    //设置表格公共信息
    table.set({
        method: 'post',
        defaultToolbar: ['filter'],//默认操作按钮
        limit: 20,//默认分页大小
        limits: [10, 20, 50, 100],
        text: '对不起，加载出现异常！',
        parseData: function (res) { //res 即为原始返回的数据
            if (res.data) {
                return {
                    "code": res.code, //解析接口状态
                    "msg": res.message, //解析提示文本
                    "count": res.data.total, //解析数据长度
                    "data": res.data.lists //解析数据列表
                };
            }
            ;
        }
    });

    //这里判断token过期时间,还剩15分钟的时候刷新token
    let now_time = new Date().getTime() / 1000;
    let expire = Number(layui.data(layui.setter.tableName)['expire']);
    if ((expire - 900) < now_time) {
        let refresh_token_result = common.ajax('/refresh_token');
        if (refresh_token_result) {
            let expire = new Date().getTime() / 1000;
            layui.data(layui.setter.tableName, {
                key: 'expire',
                value: Number(expire) + Number(refresh_token_result.data.expire)
            });
        }
    }

    //表格头部表单搜索
    form.on('submit(search_button)', function (data) {
        let field = data.field;
        table.reload('table_list', {
            where: field,
            page: {
                curr: 1//重新从第 1 页开始
            }
        }, true);
    });

    //搜索栏隐藏/显示更多搜索条件
    $('.search_from').on('click', '.search_more_button', function () {
        if ($(this).find('.sm_iconfont').hasClass('icon-xiangxia')) {
            $(this).html('精简搜索条件<i class="sm_iconfont icon-xiangshang"></i>');
            $('.search_from').find('.layui-form-item').eq(1).removeClass('layui-hide');
        } else {
            $(this).html('更多搜索条件<i class="sm_iconfont icon-xiangxia"></i>');
            $('.search_from').find('.layui-form-item').eq(1).addClass('layui-hide');
        }
    })

    //默认导出
    form.on('submit(export_button)', function (data) {
        let search_data = data.field;
        table_common.export(layui.setter.apiHost + model_url, search_data);
    });

    //头部工具栏操作
    table.on('toolbar(table_list)', function (obj) {
        let type = obj.event;
        switch (type) {
            case 'add':
                try {
                    callback_obj['add'].call()
                } catch {
                    common.open_edit('添加', 'add');
                }
                break;
            case 'status_on':
                try {
                    callback_obj['status_on'].call()
                } catch {
                    common.action_ajax('status', {status: 1});
                }
                break;
            case 'status_off':
                try {
                    callback_obj['status_off'].call()
                } catch {
                    common.action_ajax('status', {status: 0});
                }
                break;
            case 'delete':
                try {
                    callback_obj['delete'].call()
                } catch {
                    layer.confirm('确定删除吗', function (index) {
                        common.action_ajax('delete');
                    });
                }
                break;
            case 'LAYTABLE_COLS':
            case 'LAYTABLE_PRINT':
            case 'LAYTABLE_EXPORT':
                break;
            default:
                callback_obj[type].call({})
                /*try {
                    callback_obj[type].call({})
                } catch {
                    layer.msg('没有对应的回调函数');
                }*/
                break;
        }
    });
    //监听工具条操作按钮
    table.on('tool(table_list)', function (obj) {
        let data = obj.data;
        switch (obj.event) {
            case 'edit':
                try {
                    callback_obj['edit'].call(this, data, obj)
                } catch {
                    common.open_edit('编辑', 'add', {id: data.id});
                }
                break;
            case 'delete':
                try {
                    callback_obj['delete'].call(this, data, obj)
                } catch {
                    layer.confirm('确定删除吗', function (index) {
                        if (common.action_ajax('delete', {id: data.id}, false)) {
                            obj.del();
                            layer.close(index);
                        }
                    });
                }
                break;
            default:
                try {
                    callback_obj[obj.event].call(this, data, obj)
                } catch {
                    layer.msg('没有对应的回调函数');
                }
                break;
        }
    });
    //监听锁定操作
    form.on('switch(status_btn)', function (obj) {
        let data = {id: this.value, status: obj.elem.checked == true ? 1 : 0};
        if (common.action_ajax('status', data, false)) {
            layer.msg('操作成功！');
        }
    });
    //监听单元格编辑
    table.on('edit(table_list)', function (obj) {
        let data = {id: obj.data.id, field: obj.field, field_value: obj.value};
        if (common.action_ajax('field_update', data, false)) {
            layer.msg('操作成功！');
        }
    });

    /**
     * 组装form参数
     * @param name 名称
     * @param value 值
     * @returns {HTMLInputElement}
     */
    function parameterAssembly(name, value) {
        let input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = value;
        return input;
    }

    let table_common = {
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
        },
        /**
         * 导出数据
         * @param url 地址
         * @param search_data 条件
         */
        export: function (url, search_data) {
            let request = layui.setter.request;
            //创建临时form表单
            let form = document.createElement("form");
            form.style.display = "none";
            form.action = url;
            form.method = "post";
            form.target = "_blank";
            document.body.appendChild(form);
            form.appendChild(parameterAssembly('export', 1));
            form.appendChild(parameterAssembly(request.tokenName, layui.data(layui.setter.tableName)[request.tokenName] || ''));//token
            //表头
            table.eachCols('table_list', function (index, item) {
                if (!item.hide && typeof (item.field) !== 'undefined' && item.field) {
                    let input = parameterAssembly('cols[' + item.field + ']', item.title);
                    form.appendChild(input);
                }
            });
            //搜索条件
            $.each(search_data, function (index, item) {
                let input = parameterAssembly(index, item);
                form.appendChild(input);
            })
            form.submit();
            form.remove();
        },
        /**
         * 设置表格回调函数
         * @param set_obj
         */
        set_callback_obj: function (set_obj) {
            callback_obj = set_obj;
            //console.log(callback_obj)
            //示例回调函数
            /*let table_callback = {
                add: function () {
                    //这里是处理回调
                }
            }
            table_common.set_callback_obj(table_callback);*/
        }

    }

    //对外暴露的接口
    exports(
        'table_common', table_common
    );
});
