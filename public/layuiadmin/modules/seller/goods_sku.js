/**
 * Created by Administrator on 14-12-01.
 * 模拟淘宝SKU添加组合
 * 页面注意事项：
 *      1、 .div_contentlist   这个类变化这里的js单击事件类名也要改
 *      2、 .Father_Title      这个类作用是取到所有标题的值，赋给表格，如有改变JS也应相应改动
 *      3、 .Father_Item       这个类作用是取类型组数，有多少类型就添加相应的类名：如: Father_Item1、Father_Item2、Father_Item3 ...
 */
layui.define(['layer', 'verify'], function (exports) {
    "use strict";

    let $ = layui.$,
        form = layui.form;
    let last_table_data = new Object();
    //勾选时
    form.on('checkbox(goods_spec)', function (data) {
        step.Creat_Table();
    });
    //输入别名的时候对应修改表格中的名称
    $(document).on('change', '.goods_spec .spec_alias', function () {
        step.Creat_Table();
    })
    //批量设置值
    $(document).on('click', '.goods_spec .batch_set_value', function () {
        step.batchSetValue();
    })

    let step = {
        //SKU信息组合
        Creat_Table: function () {
            step.hebingFunction();
            step.saveLastTableData();
            let SKUObj = $(".goods_spec .layui-form-label");
            let arrayTitle = new Array();//标题组数

            let arraySpecName = new Array();//盛放每组规格名称
            let arraySpecId = new Array();//盛放每组规格值的id
            let arraySpecValue = new Array();//盛放每组规格值的名称
            let arraySpecImage = new Array();//盛放每组规格的图片
            let arraySpecAlias = new Array();//盛放每组规格值的别名

            let arrayColumn = new Array();//指定列，用来合并哪些列
            let bCheck = false;//是否全选
            let columnIndex = 0;
            $.each(SKUObj, function (i, item) {
                arrayColumn.push(columnIndex);
                columnIndex++;
                arrayTitle.push(SKUObj.eq(i).text());
                //选中的CHeckBox取值
                let spec_name = new Array();
                let spec_id = new Array();
                let spec_value = new Array();
                let spec_image = new Array();
                let spec_alias = new Array();

                $(".goods_spec .input_list").eq(i).find('input[type=checkbox]:checked').each(function () {
                    let _name = $(this).attr('data-name');
                    let _id = $(this).attr('data-id');
                    let _value = $(this).attr('title');
                    let _image = $(this).parent().find('input[type=hidden]').val();
                    let _alias = $(this).parent().find('input[type=text]').val();
                    if (!_alias) {
                        _alias = _value;
                    }
                    spec_name.push(_name);
                    spec_id.push(_id);
                    spec_value.push(_value);
                    spec_image.push(_image);
                    spec_alias.push(_alias);
                });
                arraySpecName.push(spec_name);
                arraySpecId.push(spec_id);
                arraySpecValue.push(spec_value);
                arraySpecImage.push(spec_image);
                arraySpecAlias.push(spec_alias);

                if (spec_value.join() != "") {
                    bCheck = true;
                }
            });
            //开始创建Table表
            if (bCheck == true) {
                let RowsCount = 0;
                $("#goods_spec_table").html("");
                let table = $("<table class=\"layui-table\"></table>");
                table.appendTo($("#goods_spec_table"));
                let thead = $("<thead></thead>");
                thead.appendTo(table);
                let trHead = $("<tr></tr>");
                trHead.appendTo(thead);
                //创建表头
                $.each(arrayTitle, function (index, item) {
                    let td = $("<th style=\"width:100px;\">" + item + "</th>");
                    td.appendTo(trHead);
                });

                let itemColumHead1 = $("<th>市场价</th><th>销售价</th><th>库存</th><th>货号</th><th>重量</th><th>起订数量</th><th>最大够买数量(0为不限制)</th>");
                itemColumHead1.appendTo(trHead);
                let tbody = $("<tbody></tbody>");
                tbody.appendTo(table);
                ////生成组合
                let zuheName = step.doExchange(arraySpecName);
                let zuheIdDate = step.doExchange(arraySpecId);
                let zuheValueDate = step.doExchange(arraySpecValue);
                let zuheImageDate = step.doExchange(arraySpecImage);
                let zuheAliasDate = step.doExchange(arraySpecAlias);

                if (zuheAliasDate.length > 0) {
                    //创建行
                    $.each(zuheAliasDate, function (index, item) {
                        let td_name = zuheName[index].split(",");
                        let td_id = zuheIdDate[index].split(",");
                        let td_value = zuheValueDate[index].split(",");
                        let td_image = zuheImageDate[index].split(",");
                        let td_alias = item.split(",");
                        let tr = $("<tr></tr>");
                        tr.appendTo(tbody);
                        let td_input = "";
                        let spec_table_ids = Array();
                        $.each(td_alias, function (i, values) {
                            spec_table_ids.push(td_id[i]);//根据所有选择的规格id组合成的数组id
                            td_input += '<input type="hidden" name="spec_name[' + index + '][]" value="' + td_name[i] + '">';
                            td_input += '<input type="hidden" name="spec_id[' + index + '][]" value="' + td_id[i] + '">';
                            td_input += '<input type="hidden" name="spec_value[' + index + '][]" value="' + td_value[i] + '">';
                            td_input += '<input type="hidden" name="spec_image[' + index + '][]" value="' + td_image[i] + '">';
                            td_input += '<input type="hidden" name="spec_alias[' + index + '][]" value="' + values + '">';
                            let td = $("<td>" + values + "</td>");
                            td.appendTo(tr);
                        });
                        //根据规格值id记录历史数据
                        let spec_table_ids_key = spec_table_ids.join('|');
                        td_input += '<input type="hidden" name="spec_table_ids_key[' + index + ']" value="' + spec_table_ids_key + '">';
                        let sku_code = $('[name="sku_code"]').val() + '_';//给定默认货号

                        //获取默认值
                        let spec_sku_id = step.getLastTableData(spec_table_ids_key, 'spec_sku_id');
                        let spec_market_price = step.getLastTableData(spec_table_ids_key, 'spec_market_price');
                        let spec_sell_price = step.getLastTableData(spec_table_ids_key, 'spec_sell_price');
                        let spec_stock = step.getLastTableData(spec_table_ids_key, 'spec_stock');
                        let spec_sku_code = step.getLastTableData(spec_table_ids_key, 'spec_sku_code');
                        if (!spec_sku_code) spec_sku_code = sku_code + index;
                        let spec_weight = step.getLastTableData(spec_table_ids_key, 'spec_weight');
                        let spec_min_buy = step.getLastTableData(spec_table_ids_key, 'spec_min_buy');
                        let spec_max_buy = step.getLastTableData(spec_table_ids_key, 'spec_max_buy');
                        if (!spec_min_buy) spec_min_buy = 1;
                        if (!spec_max_buy) spec_max_buy = 0;
                        let td_html = '';
                        td_html += '<td><input type="hidden" name="spec_sku_id[' + index + ']" value="' + spec_sku_id + '">' + td_input;
                        td_html += '<input type="text" name="spec_market_price[' + index + ']" value="' + spec_market_price + '" lay-verify="price" autocomplete="off" class="layui-input spec_market_price"></td>';
                        td_html += '<td><input type="text" name="spec_sell_price[' + index + ']" value="' + spec_sell_price + '" lay-verify="price" autocomplete="off" class="layui-input spec_sell_price"></td>';
                        td_html += '<td><input type="text" name="spec_stock[' + index + ']" value="' + spec_stock + '" lay-verify="number" autocomplete="off" class="layui-input spec_stock"></td>';
                        td_html += '<td><input type="text" name="spec_sku_code[' + index + ']" value="' + spec_sku_code + '" lay-verify="required" lay-errormsg="货号不能为空" autocomplete="off" class="layui-input spec_sku_code"></td>';
                        td_html += '<td><input type="text" name="spec_weight[' + index + ']" value="' + spec_weight + '" lay-verify="number" autocomplete="off" class="layui-input spec_weight"></td>';
                        td_html += '<td><input type="text" name="spec_min_buy[' + index + ']" value="' + spec_min_buy + '" lay-verify="number" autocomplete="off" class="layui-input spec_min_buy"></td>';
                        td_html += '<td><input type="text" name="spec_max_buy[' + index + ']" value="' + spec_max_buy + '" lay-verify="number" autocomplete="off" class="layui-input spec_max_buy"></td>';
                        $(td_html).appendTo(tr);
                    });

                    //添加批量修改的按钮start
                    let tr_batch = $("<tr></tr>");
                    tr_batch.appendTo(tbody);
                    let td_alias = zuheAliasDate[0].split(",");
                    let td_batch = '';
                    td_batch += '<td colspan="' + td_alias.length + '" align="right"><button type="button" class="layui-btn layui-btn-sm batch_set_value">批量修改</button></td>';
                    td_batch += '<td ><input type="text" autocomplete="off" class="layui-input spec_market_price"></td>';
                    td_batch += '<td ><input type="text" autocomplete="off" class="layui-input spec_sell_price"></td>';
                    td_batch += '<td ><input type="text" autocomplete="off" class="layui-input spec_stock"></td>';
                    td_batch += '<td ></td>';
                    td_batch += '<td ><input type="text" autocomplete="off" class="layui-input spec_weight"></td>';
                    td_batch += '<td ><input type="text" autocomplete="off" class="layui-input spec_min_buy"></td>';
                    td_batch += '<td ><input type="text" autocomplete="off" class="layui-input spec_max_buy"></td>';
                    $(td_batch).appendTo(tr_batch);
                    //添加批量修改的按钮end
                }
                //结束创建Table表
                arrayColumn.pop();//删除数组中最后一项
                //合并单元格
                $(table).mergeCell({
                    // 目前只有cols这么一个配置项, 用数组表示列的索引,从0开始
                    cols: arrayColumn
                });
            } else {
                step.setDefaultTable();
                //document.getElementById('goods_spec_table').innerHTML="";
            }

        },//合并行
        hebingFunction: function () {
            $.fn.mergeCell = function (options) {
                return this.each(function () {
                    let cols = options.cols;
                    for (let i = cols.length - 1; cols[i] != undefined; i--) {
                        // fixbug console调试
                        // console.debug(cols[i]);
                        mergeCell($(this), cols[i]);
                    }
                    dispose($(this));
                });
            };

            function mergeCell($table, colIndex) {
                $table.data('col-content', ''); // 存放单元格内容
                $table.data('col-rowspan', 1); // 存放计算的rowspan值 默认为1
                $table.data('col-td', $()); // 存放发现的第一个与前一行比较结果不同td(jQuery封装过的), 默认一个"空"的jquery对象
                $table.data('trNum', $('tbody tr', $table).length); // 要处理表格的总行数, 用于最后一行做特殊处理时进行判断之用
                // 进行"扫面"处理 关键是定位col-td, 和其对应的rowspan
                $('tbody tr', $table).each(function (index) {
                    // td:eq中的colIndex即列索引
                    let $td = $('td:eq(' + colIndex + ')', this);
                    // 取出单元格的当前内容
                    let currentContent = $td.html();
                    // 第一次时走此分支
                    if ($table.data('col-content') == '') {
                        $table.data('col-content', currentContent);
                        $table.data('col-td', $td);
                    } else {
                        // 上一行与当前行内容相同
                        if ($table.data('col-content') == currentContent) {
                            // 上一行与当前行内容相同则col-rowspan累加, 保存新值
                            let rowspan = $table.data('col-rowspan') + 1;
                            $table.data('col-rowspan', rowspan);
                            // 值得注意的是 如果用了$td.remove()就会对其他列的处理造成影响
                            $td.hide();
                            // 最后一行的情况比较特殊一点
                            // 比如最后2行 td中的内容是一样的, 那么到最后一行就应该把此时的col-td里保存的td设置rowspan
                            if (++index == $table.data('trNum'))
                                $table.data('col-td').attr('rowspan', $table.data('col-rowspan'));
                        } else { // 上一行与当前行内容不同
                            // col-rowspan默认为1, 如果统计出的col-rowspan没有变化, 不处理
                            if ($table.data('col-rowspan') != 1) {
                                $table.data('col-td').attr('rowspan', $table.data('col-rowspan'));
                            }
                            // 保存第一次出现不同内容的td, 和其内容, 重置col-rowspan
                            $table.data('col-td', $td);
                            $table.data('col-content', $td.html());
                            $table.data('col-rowspan', 1);
                        }
                    }
                });
            }

            // 同样是个private函数 清理内存之用
            function dispose($table) {
                $table.removeData();
            }
        },
        //组合数组
        doExchange: function (doubleArrays) {
            let len = doubleArrays.length;
            if (len >= 2) {
                let arr1 = doubleArrays[0];
                let arr2 = doubleArrays[1];
                let len1 = doubleArrays[0].length;
                let len2 = doubleArrays[1].length;
                let newlen = len1 * len2;
                let temp = new Array(newlen);
                let index = 0;
                for (let i = 0; i < len1; i++) {
                    for (let j = 0; j < len2; j++) {
                        temp[index] = arr1[i] + "," + arr2[j];
                        index++;
                    }
                }
                let newArray = new Array(len - 1);
                newArray[0] = temp;
                if (len > 2) {
                    let _count = 1;
                    for (let i = 2; i < len; i++) {
                        newArray[_count] = doubleArrays[i];
                        _count++;
                    }
                }
                return step.doExchange(newArray);
            } else {
                return doubleArrays[0];
            }
        },
        //记住以前的数据
        saveLastTableData: function (set_value) {
            if (set_value) {
                //加载默认值
                last_table_data = set_value;
            } else {
                let tr_row = $('#goods_spec_table tr').length;
                if (tr_row > 2) {
                    $('#goods_spec_table tr').each(function (index, item) {
                        let new_index = index - 1;
                        if (new_index >= 0 && index <= tr_row - 2) {
                            let id = $(this).find('[name="spec_table_ids_key[' + new_index + ']"]').val();
                            let one_data = Array();
                            one_data['spec_sku_id'] = $(this).find('[name="spec_sku_id[' + new_index + ']"]').val();
                            one_data['spec_market_price'] = $(this).find('[name="spec_market_price[' + new_index + ']"]').val();
                            one_data['spec_sell_price'] = $(this).find('[name="spec_sell_price[' + new_index + ']"]').val();
                            one_data['spec_stock'] = $(this).find('[name="spec_stock[' + new_index + ']"]').val();
                            one_data['spec_sku_code'] = $(this).find('[name="spec_sku_code[' + new_index + ']"]').val();
                            one_data['spec_weight'] = $(this).find('[name="spec_weight[' + new_index + ']"]').val();
                            one_data['spec_min_buy'] = $(this).find('[name="spec_min_buy[' + new_index + ']"]').val();
                            one_data['spec_max_buy'] = $(this).find('[name="spec_max_buy[' + new_index + ']"]').val();
                            last_table_data[id] = one_data;
                        }
                    })
                }
            }
        },
        //获取历史数据
        getLastTableData: function (index, key) {
            if (last_table_data[index]) {
                if (last_table_data[index][key]) {
                    return last_table_data[index][key];
                }
            }
            return '';
        },
        //设置默认表格
        setDefaultTable: function () {
            let default_html = "";
            $("#goods_spec_table").html("");
            let table = $('<table class="layui-table"></table>');
            table.appendTo($("#goods_spec_table"));
            let thead = $("<thead></thead>");
            thead.appendTo(table);
            let trHead = $("<tr></tr>");
            trHead.appendTo(thead);
            let td = $("<th>市场价</th><th>销售价</th><th>库存</th><th>货号</th><th>重量</th><th>起订数量</th><th>最大够买数量(0为不限制)</th>");
            td.appendTo(trHead);
            let tbody = $("<tbody></tbody>");
            tbody.appendTo(table);
            let tr = $("<tr></tr>");
            tr.appendTo(tbody);
            let spec_sku_id = step.getLastTableData('default', 'spec_sku_id');
            let spec_market_price = step.getLastTableData('default', 'spec_market_price');
            let spec_sell_price = step.getLastTableData('default', 'spec_sell_price');
            let spec_stock = step.getLastTableData('default', 'spec_stock');
            let spec_sku_code = step.getLastTableData('default', 'spec_sku_code');
            if (!spec_sku_code) spec_sku_code = $('[name="sku_code"]').val();
            let spec_weight = step.getLastTableData('default', 'spec_weight');
            let spec_min_buy = step.getLastTableData('default', 'spec_min_buy');
            let spec_max_buy = step.getLastTableData('default', 'spec_max_buy');
            if (!spec_min_buy) spec_min_buy = 1;
            if (!spec_max_buy) spec_max_buy = 0;
            let td_html = '';
            td_html += '<td><input type="hidden" name="spec_sku_id[0]" value="' + spec_sku_id + '"><input type="text" name="spec_market_price[0]" value="' + spec_market_price + '" lay-verify="price" autocomplete="off" class="layui-input"></td>';
            td_html += '<td><input type="text" name="spec_sell_price[0]" value="' + spec_sell_price + '" lay-verify="price" autocomplete="off" class="layui-input"></td>';
            td_html += '<td><input type="text" name="spec_stock[0]" value="' + spec_stock + '" lay-verify="number" autocomplete="off" class="layui-input"></td>';
            td_html += '<td><input type="text" name="spec_sku_code[0]" value="' + spec_sku_code + '" lay-verify="required" lay-errormsg="货号不能为空" autocomplete="off" class="layui-input"></td>';
            td_html += '<td><input type="text" name="spec_weight[0]" value="' + spec_weight + '" lay-verify="number" autocomplete="off" class="layui-input"></td>';
            td_html += '<td><input type="text" name="spec_min_buy[0]" value="' + spec_min_buy + '" lay-verify="number" autocomplete="off" class="layui-input"></td>';
            td_html += '<td><input type="text" name="spec_max_buy[0]" value="' + spec_max_buy + '" lay-verify="number" autocomplete="off" class="layui-input"></td>';
            $(td_html).appendTo(tr);
        },
        //批量设置值
        batchSetValue: function () {
            let obj = $('.goods_spec .batch_set_value');
            let spec_market_price = obj.closest('tr').find('.spec_market_price').val();
            let spec_sell_price = obj.closest('tr').find('.spec_sell_price').val();
            let spec_stock = obj.closest('tr').find('.spec_stock').val();
            let spec_weight = obj.closest('tr').find('.spec_weight').val();
            let spec_min_buy = obj.closest('tr').find('.spec_min_buy').val();
            let spec_max_buy = obj.closest('tr').find('.spec_max_buy').val();
            if (spec_market_price) $('.goods_spec .spec_market_price').val(spec_market_price);
            if (spec_sell_price) $('.goods_spec .spec_sell_price').val(spec_sell_price);
            if (spec_stock) $('.goods_spec .spec_stock').val(spec_stock);
            if (spec_weight) $('.goods_spec .spec_weight').val(spec_weight);
            if (spec_min_buy) $('.goods_spec .spec_min_buy').val(spec_min_buy);
            if (spec_max_buy) $('.goods_spec .spec_max_buy').val(spec_max_buy);
            obj.closest('tr').find('input').val('');
        }
    }

    //加载默认sku表格
    $(function () {
        step.setDefaultTable();
    })
    exports('goods_sku', step);
});


