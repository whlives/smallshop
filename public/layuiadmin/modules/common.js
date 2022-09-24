/**
 公共业务
 */

layui.define(function (exports) {
    let $ = layui.$,
        layer = layui.layer,
        table = layui.table,
        admin = layui.admin,
        view = layui.view,
        form = layui.form,
        model_url = '';

    //退出
    admin.events.logout = function () {
        //执行退出接口
        admin.req({
            url: layui.setter.apiHost + '/logout',
            data: {},
            success: function (res) {
                //这里要说明一下：done 是只有 response 的 code 正常才会执行。而 success 则是只要 http 为 200 就会执行
                //清空本地记录的 token，并跳转到登入页
                admin.exit(function () {
                    location.href = layui.setter.viewsDir + '/login.html';
                });
            }
        });
    };
    //公共业务的逻辑处理可以写在此处，切换任何页面都会执行
    //加载icon css
    layui.link(layui.setter.iconfontCss);

    let common = {
        /**
         * 设置模块默认前缀
         * @param url 前缀地址
         */
        set_model_url: function (url) {
            model_url = url;
        },
        /**
         * 列表公共ajax请求
         * @param url 请求地址
         * @param data 请求数据
         * @param reload 是否刷新，或者回调
         * @param table_id 当前table id
         * @returns {boolean}
         */
        action_ajax: function (url, data = {}, reload = true, table_id = 'table_list') {
            if (!data) data = {};
            //不存在id时重新获取
            if (!data.id) {
                data.id = common.get_check_id(table_id);
                if (!data.id) {
                    return false;
                }
            }
            admin.req({
                url: layui.setter.apiHost + model_url + '/' + url,
                data: data,
                success: function (result) {
                    if (result.code == 0) {
                        if (reload === true) {
                            layer.msg('操作成功', {time: 1000}, function () {
                                if ($('#' + table_id).length > 0) {
                                    table.reload(table_id);
                                } else {
                                    location.reload();
                                }
                            })
                        } else {
                            try {
                                reload(result);//不刷新的看是否有回调
                            } catch (e) {
                            }
                        }
                    } else if (result.msg) {
                        layer.msg(result.msg);
                    } else {
                        layer.msg('操作失败');
                    }
                }
            })
        },
        /**
         * 发起ajax请求
         * @param url 地址
         * @param data 数据
         * @param callback 回调函数
         * @param error 错误回调函数
         */
        ajax: function (url, data, callback = {}, error = {}) {
            admin.req({
                url: layui.setter.apiHost + url,
                data: data,
                success: function (result) {
                    if (result.code == 0) {
                        try {
                            callback(result);
                        } catch (e) {
                        }
                    } else if (result.msg) {
                        try {
                            error(result);
                        } catch (e) {
                            layer.msg(result.msg);
                        }
                    } else {
                        layer.msg('操作失败');
                    }
                }
            })
        },
        /**
         * 获取已经选择的数据id
         * @param table_id 表格id
         * @returns {any[]|boolean}
         */
        get_check_id: function (table_id = 'table_list') {
            let data_id = new Array();
            let check_status = table.checkStatus(table_id),
                select_data = check_status.data;
            for (let i = 0; i < select_data.length; i++) {
                data_id.push(select_data[i].id);
            }
            if (data_id.length > 0) {
                return data_id;
            } else {
                layer.msg('没有选择任何数据');
                return false;
            }
        },
        /**
         * 查看详情
         * @param title 弹框名称
         * @param view_url 地址
         * @param default_data 默认参数
         * @param width 弹出框宽度
         * @param height 弹出框高度
         */
        detail: function (title, view_url, default_data, width = '100%', height = '100%') {
            if (!default_data) default_data = {};
            view_url = layui.setter.viewsDir + model_url + '/' + view_url;
            admin.popup({
                title: title,
                area: [width, height],
                id: new Date().getTime(),
                success: function (layero, index) {
                    view(this.id).render(view_url, default_data).done(function () {

                    });
                }
            });
        },
        /**
         * 打开一个编辑框
         * @param title 编辑框标题
         * @param view_url 地址
         * @param default_data 默认数据
         * @param width 宽度
         * @param set_data 其他参数
         * @param table_id 表格id
         */
        open_edit: function (title, view_url, default_data = {}, width, set_data = {}, table_id = '') {
            let height = '550px';
            if (!width) width = '100%';
            if (width == '100%') height = '100%';
            if (!set_data.detail_url) set_data.detail_url = '/detail';
            if (!set_data.save_url) set_data.save_url = '/save';
            if (!table_id) table_id = 'table_list';
            admin.popup({
                title: title,
                area: [width, height],
                id: new Date().getTime(),
                success: function (layero, index) {
                    let detail_data = default_data;
                    //存在详情地址和id时获取详情内容
                    if (default_data.id && set_data.detail_url) {
                        admin.req({
                            url: layui.setter.apiHost + model_url + set_data.detail_url,
                            data: {id: default_data.id},
                            async: false,
                            success: function (result) {
                                if (result.code == 0) {
                                    detail_data = $.extend(default_data, result.data);
                                }
                            }
                        });
                    }
                    view_url = layui.setter.viewsDir + model_url + '/' + view_url
                    view(this.id).render(view_url, detail_data).done(function () {
                        common.set_button(model_url);//设置按钮权限
                        if (detail_data) form.val('form', detail_data);//初始化表单
                        form.render(null, 'form');
                        //监听提交
                        form.on('submit(form-submit)', function (data) {
                            admin.req({
                                type: "POST",
                                url: layui.setter.apiHost + model_url + set_data.save_url,
                                data: data.field,
                                success: function (result) {
                                    if (result.code == 0) {
                                        layer.msg('操作成功', {time: 1000}, function () {
                                            if ($('#' + table_id).length > 0) {
                                                table.reload(table_id);
                                            } else {
                                                location.reload();
                                            }
                                            layer.close(index);//执行关闭
                                        });
                                    } else if (result.code == 1) {
                                        layer.alert(result.msg);//持续提示不关闭提示信息
                                    } else {
                                        layer.msg(result.msg);
                                    }
                                },
                                error: function () {
                                    layer.msg('操作失败，请刷新页面重试！');
                                }
                            });
                        });
                    });
                }
            });
        },
        /**
         * 打开一个iframe窗口
         * title 窗口名称
         * view_url 地址
         * width 窗口宽度
         * height 窗口高度
         * */
        open_iframe: function (title, view_url, width = '100%', height = '100%') {
            if (width == '100%') {
                height = '100%';
            }
            admin.popup({
                type: 2,
                title: title,
                area: [width, height],
                id: new Date().getTime(),
                scrollbar: false,
                content: view_url
            });
        },
        /**
         * 图片缩放
         * @param url 图片地址
         * @param w 宽度
         * @param h 高度
         * @param m 模式
         * @returns {string|*}
         */
        image_resize: function (url, w, h, m = 'lfit') {
            let new_url = '';
            //非网络地址和已经加了处理的图片不再处理
            if (url.indexOf("http") == -1 || url.indexOf("oss-process") != -1) {
                return url;
            } else {
                let params = Array();
                if (w) params.push('w_' + w);
                if (h) params.push('h_' + h);
                if (m) params.push('m_' + m);
                new_url = url + '?x-oss-process=image/resize,' + params.join(',');
                return new_url;
            }
            return url;
        },
        /**
         * 获取当前地址的url参数值
         * @param variable
         * @returns {string|boolean}
         */
        get_query_variable: function (variable) {
            let query = window.location.search.substring(1);
            let vars = query.split("&");
            for (let i = 0; i < vars.length; i++) {
                let pair = vars[i].split("=");
                if (pair[0] == variable) {
                    return pair[1];
                }
            }
            return (false);
        },
        /**
         * 设置按钮权限
         * @param model_url 模块
         */
        set_button: function (model_url) {
            let login_data = layui.data(layui.setter.tableName);
            let role_id = login_data['role_id'];
            let button = login_data['button'][model_url.slice(1)];
            $('.layui-btn.layui-hide').each(function () {
                if ($.inArray($(this).attr('lay-event'), button) >= 0 || role_id == 1) {
                    $(this).removeClass('layui-hide')
                }
            });
        },
        /**
         * 省市区选择
         * @param select_id 下拉框id
         * @param parent_id 上级id
         * @param default_id 默认值
         */
        get_area: function (select_id, parent_id, default_id) {
            if (!default_id) default_id = 0;
            if (!parent_id) parent_id = 0;
            if (!select_id) select_id = 'prov_id';
            admin.req({
                url: layui.setter.apiHost + '/helper/area',
                data: {parent_id: parent_id},
                async: false,
                success: function (result) {
                    if (result.code == 0) {
                        let html = '<option value="0">请选择</option>';
                        $.each(result.data, function (index, item) {
                            let selected = '';
                            if (item.id == default_id) {
                                selected = 'selected';
                            }
                            html += '<option value="' + item.id + '" ' + selected + '>' + item.name + '</option>';
                        })
                        $('#' + select_id).html(html);
                        //选择省份时清空地区
                        if (select_id == 'prov_id') {
                            $('#city_id').html('<option value="0">请选择</option>');
                            $('#area_id').html('<option value="0">请选择</option>');
                        }
                        //选择城市时清空地区
                        if (select_id == 'city_id') {
                            $('#area_id').html('<option value="0">请选择</option>');
                        }
                        form.render('select');
                    }
                },
                error: function () {
                    layer.msg('操作失败，请刷新页面重试！');
                }
            });
        },
        /**
         * 设置默认省市区
         * @param prov_id 省份id
         * @param city_id 城市id
         * @param area_id 地区id
         */
        set_default_area: function (prov_id, city_id, area_id) {
            this.get_area('prov_id', 0, prov_id);
            if (prov_id && city_id) {
                this.get_area('city_id', prov_id, city_id);
                if (city_id && area_id) {
                    this.get_area('area_id', city_id, area_id);
                }
            }
        },
        /**
         * 生成下拉框内容
         * @param name 下拉框名称
         * @param data 下拉框数据
         * @param default_id 默认id
         */
        set_select_option: function (name, data, default_id = 0) {
            let html = '<option value=""></option>';
            $.each(data, function (index, item) {
                let selected = '';
                if (item.id == default_id) {
                    selected = 'selected';
                }
                html += '<option value="' + item.id + '" ' + selected + '>' + item.title + '</option>';
            })
            $('[name="' + name + '"]').html(html);
            form.render('select');
        },
    }

    //对外暴露的接口
    exports('common', common);
});
