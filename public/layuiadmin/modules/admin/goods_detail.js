/**
 * 订单
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:45 PM
 */

layui.define(['plupload', 'goods_sku'], function (exports) {
    let $ = layui.$,
        form = layui.form,
        plupload = layui.plupload,
        laytpl = layui.laytpl,
        common = layui.common,
        goods_sku = layui.goods_sku,
        params = '',
        model_url = '/goods/goods';

    layui.link(layui.setter.base + 'style/goods.css?v=true');

    //图片回调
    let plupload_callback = {
        //商品大图回调
        goods_image: function (res) {
            if (res.url) {
                laytpl($('#goods_image_tpl').html()).render(res, function (html) {
                    $('#goods_image_list').append(html).parent().removeClass('layui-hide');
                })
            }
            //验证数量
            if (res.file_num) {
                let goods_image_num = $("#goods_image_list > div").length;
                if ((goods_image_num + res.file_num) > 5) {
                    layer.msg('商品主图最多不能超过5张')
                    return false;
                }
            }
            return true;//如果需要判断数量就在这里操作返回false
        },
        //规格图片回调
        spec_image: function (res) {
            if (res.url) {
                $("#" + res.id_name).parent().find('img').attr('src', res.url).removeClass('layui-hide');
                $("#" + res.id_name).parent().find('a').attr('href', res.url);
                $("#" + res.id_name).parent().find('[type="hidden"]').val(res.url);
                goods_sku.Creat_Table();
            }
            return true;
        }
    }
    plupload.set_callback_obj(plupload_callback);

    //图片删除或位置移动
    $('#goods_image_list').on('click', '.sm_iconfont', function () {
        let obj = $(this).closest('.goods_image');
        if ($(this).hasClass('image_move_left')) {
            //左移
            let to_index = obj.prev().index();
            $('#goods_image_list .goods_image:eq(' + to_index + ')').before(obj);
        } else if ($(this).hasClass('image_move_right')) {
            //右移
            let to_index = obj.next().index();
            $('#goods_image_list .goods_image:eq(' + to_index + ')').after(obj);
        } else if ($(this).hasClass('image_delete')) {
            //删除
            obj.remove();
        }
    })

    //监听店铺选择
    form.on('select(seller_id)', function (data) {
        get_delivery(data.value);
        get_object(data.value);
    });

    //根据店铺刷新配送方式
    function get_delivery(seller_id, default_id) {
        if (!default_id) default_id = 0;
        common.ajax(model_url + '/delivery', {seller_id: seller_id}, function (result) {
            common.set_select_option(result.data, default_id, 'delivery_id');
        });
    }

    //根据店铺刷新对象（优惠券或套餐包）
    function get_object(seller_id, default_id) {
        if (params.type == 2 || params.type == 5) {
            let label_title = '';
            if (params.type == 2) {
                label_title = '优惠券';
            } else if (params.type == 5) {
                label_title = '套餐包';
            }
            if (!default_id) default_id = 0;
            common.ajax(model_url + '/object', {seller_id: seller_id, type: params.type}, function (result) {
                common.set_select_option(result.data, default_id, 'object_id');
                $('[name="object_id"]').parent().parent().removeClass('layui-hide').find('label').text(label_title);
            });
        } else {
            return false;
        }
    }

    //加载属性
    function get_attribute(goods_id) {
        if (!goods_id) goods_id = 0;
        common.ajax(model_url + '/get_attribute', {
            category_id: params.category_id,
            goods_id: goods_id
        }, function (result) {
            laytpl($('#attribute_tpl').html()).render(result.data, function (html) {
                $('#attribute').append(html).removeClass('layui-hide');
            })
            form.render();
        });
    }

    //加载规格
    function get_spec(goods_id) {
        if (!goods_id) goods_id = 0;
        common.ajax(model_url + '/get_spec', {category_id: params.category_id, goods_id: goods_id}, function (result) {
            laytpl($('#spec_tpl').html()).render(result.data.spec, function (html) {
                $('.goods_spec').append(html).removeClass('layui-hide');
            })
            if (result.data.goods_sku) {
                //回填默认sku数据
                goods_sku.saveLastTableData(result.data.goods_sku);
                goods_sku.Creat_Table();
            }
            plupload.init();//初始化图片上传
            form.render();
        });
    }

    let obj = {
        /**
         * 设置参数并初始化
         * @param data
         */
        set_params: function (data) {
            params = data;
            get_attribute(params.id);
            get_spec(params.id);
            //编辑时回填运费模板
            if (params.seller_id) {
                get_delivery(params.seller_id, params.delivery_id);
                get_object(params.seller_id, params.object_id);
            }
            //回填图片
            if (params.goods_image) {
                let item_goods_image = params.goods_image;
                for (let j = 0, len = item_goods_image.length; j < len; j++) {
                    plupload_callback['goods_image'].call(this, {url: item_goods_image[j]})
                }
            }
        },
    }

    //对外暴露的接口
    exports('goods_detail', obj);
});
