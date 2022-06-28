/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 3:30 PM
 */

layui.define(function (exports) {
    let admin = layui.admin,
        $ = layui.$,
        update_param = '',//上传参数
        plupload_btn_id = '',
        file_progress_num = 1,//图片开始数量
        max_file_size = 2,//默认上传大小单位MB
        mime_types = {
            image: {
                suffix: 'jpg,gif,png,jpeg',
                mime_type: ['image/jpeg', 'image/png', 'image/x-ms-bmp']
            },
            video: {
                suffix: 'mp4',
                mime_type: ['video/mp4']
            },
            zip: {
                suffix: 'zip,rar,tar,tar.gz,tar.bz',
                mime_type: ['application/zip', 'application/x-rar', 'application/x-tar', 'application/x-compressed-tar', 'application/x-bzip-compressed-tar']
            },
            doc: {
                suffix: 'doc,docx,xls,xlsx,ppt,pptx,pdf',
                mime_type: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            }
        };

    let callback_obj;

    //填写图片地址的时候加载缩略图
    $(document).on('blur', '.plupload_btn_input', function () {
        let url = $(this).val();
        if (url) {
            $(this).parent().parent('.layui-form-item').find('img').attr('src', url).removeClass('layui-hide');
            $(this).parent().parent('.layui-form-item').find('a').attr('href', url);
        }
    })

    /**
     * 组装oss参数
     * @returns {{OSSAccessKeyId: *, signature, success_action_status: string, "x-oss-security-token": *, key: *, policy: *}}
     */
    function getMultipartParams(file) {
        let new_multipart_params = {};
        if (update_param.upload_type == 0) {
            let request = layui.setter.request;
            if (request.tokenName) {
                new_multipart_params[request.tokenName] = layui.data(layui.setter.tableName)[request.tokenName] || '';
            }
            update_param['host'] = layui.setter.apiHost + '/helper/upload'
        } else {
            new_multipart_params = {
                'key': update_param.dirname + file.target_name,
                'policy': update_param.policy,
                'OSSAccessKeyId': update_param.access_key_id,
                'success_action_status': '200', //让服务端返回200,不然，默认会返回204
                'signature': update_param.signature,
                'x-oss-security-token': update_param.sts_token,
            };
        }
        return new_multipart_params;
    }

    /**
     * 获取支持的文件类型
     * @returns {string}
     */
    function getFileType() {
        let file_type = 'image';
        let new_file_type = $('#' + plupload_btn_id).attr('file_type');
        if (typeof (new_file_type) !== 'undefined' && new_file_type != '') {
            file_type = new_file_type;
        }
        return file_type;
    }

    /**
     * 获取上传文件大小限制
     * @returns {number}
     */
    function getMaxFileSize() {
        let new_max_file_size = $('#' + plupload_btn_id).attr('max_file_size');
        if (typeof (new_max_file_size) !== 'undefined' && new_max_file_size != '' && !isNaN(new_max_file_size)) {
            max_file_size = new_max_file_size;
        }
        return max_file_size;
    }

    /**
     * 调用自定义回调
     * @param callback_data 回调数据
     * @returns {boolean}
     */
    function getCallBack(callback_data) {
        let next = true;
        if ($('#' + plupload_btn_id).hasClass('is_callback')) {
            try {
                let fun_name = plupload_btn_id;
                //如果存在自定义函数名称就调用自定义，否则默认查找id命名的函数
                let callback_fun = $('#' + plupload_btn_id).attr('callback_fun');
                if (typeof (callback_fun) !== 'undefined' && callback_fun != '') {
                    fun_name = callback_fun;
                }
                //调用自定义函数处理回调,比如控制上传的数量(plupload_btn_id 上传图片的按钮id)
                next = callback_obj[fun_name].call(this, callback_data)
            } catch (e) {
                next = false;
            }
        } else {
            //没有回调的时候如果是上传完成了需要显示图片
            if (callback_data.url) {
                try {
                    $("#" + plupload_btn_id).parent().parent('.layui-form-item').find('img').attr('src', callback_data.url).removeClass('layui-hide');
                    $("#" + plupload_btn_id).parent().parent('.layui-form-item').find('a').attr('href', callback_data.url);
                    $("#" + plupload_btn_id).parent().parent('.layui-form-item').find('input').val(callback_data.url);
                } catch (e) {
                }
            }
        }
        return next;
    }

    /**
     * 上传提示
     */
    function uploadLoading() {
        let loading = '<div class="upload_loading"><div class="upload_loading_box"><div class="spinner"><div class="rect1 spinner_loading"></div> <div class="rect2 spinner_loading"></div> <div class="rect3 spinner_loading"></div> <div class="rect4 spinner_loading"></div> <div class="rect5 spinner_loading"></div><div class="upload_loading_progress">第1个文件正在上传0%</div></div></div></div><style type="text/css">.upload_loading{position:fixed;z-index:999999999;top:0;left:0;width:100%;height:100%;text-align:center;background:rgba(0,0,0,.1);font-size:0}.upload_loading:before{content:"";display:inline-block;height:100%;vertical-align:middle;margin-right:-.25em}.upload_loading_box{display:inline-block;vertical-align:middle;width:80%;height:auto;position:relative}.spinner{margin:100px auto;width:180px;height:60px;text-align:center;font-size:10px}.spinner>.spinner_loading{background-color:#009688;height:100%;width:6px;display:inline-block;-webkit-animation:stretchdelay 1.2s infinite ease-in-out;animation:stretchdelay 1.2s infinite ease-in-out}.spinner .rect2{-webkit-animation-delay:-1.1s;animation-delay:-1.1s}.spinner .rect3{-webkit-animation-delay:-1s;animation-delay:-1s}.spinner .rect4{-webkit-animation-delay:-.9s;animation-delay:-.9s}.spinner .rect5{-webkit-animation-delay:-.8s;animation-delay:-.8s}.upload_loading_progress {color: #009688;font-weight:bold;}@-webkit-keyframes stretchdelay{0%,100%,40%{-webkit-transform:scaleY(.4)}20%{-webkit-transform:scaleY(1)}}@keyframes stretchdelay{0%,100%,40%{transform:scaleY(.4);-webkit-transform:scaleY(.4)}20%{transform:scaleY(1);-webkit-transform:scaleY(1)}}</style>';
        $('body').append(loading);
    }

    /**
     * 上传进度
     * @param progress
     */
    function uploadProgress(progress = 0) {
        $('.upload_loading_progress').html('第' + file_progress_num + '个文件正在上传' + progress + '%');
    }

    /**
     * 单个文件上传完成回调
     * @param file
     * @param result
     */
    function getUploadFileEnd(file, result) {
        if (update_param.upload_type == 1) {
            //处理阿里云上传的
            if (result.status == 200) {
                let res_url = update_param.domain + '/' + update_param.dirname + file.target_name;
                getCallBack({id_name: plupload_btn_id, url: res_url});
            } else {
                layer.msg('上传失败');
            }
        } else {
            //处理本地上传的
            let res_data = $.parseJSON(result.response)
            if (res_data.code == 0) {
                let res_url = res_data.data.url;
                getCallBack({id_name: plupload_btn_id, url: res_url});
            } else {
                layer.msg(res_data.msg);
            }
        }
    }

    /**
     * 上传结束
     */
    function uploadEnd() {
        file_progress_num = 1;//再次上传从1开始计数
        $('.upload_loading').remove();//关闭加载效果
    }

    /**
     * 调用示例
     * plupload.init();
     */
    let obj = {
        //设置回调函数
        set_callback_obj: function (set_obj) {
            callback_obj = set_obj;
            //示例回调函数
            /*let plupload_callback = {
                up_image: function (res) {
                    if (res) {
                        //这里是处理回调后的url
                    }
                    return true;//如果需要判断数量就在这里操作返回false
                }
            }
            plupload.set_callback_obj(plupload_callback);
            */
        },
        init: function () {
            admin.req({
                url: layui.setter.apiHost + '/helper/aliyun_sts',
                success: function (result) {
                    if (result.code == 0) {
                        update_param = result.data;
                        /**
                         按钮参数
                         id：按钮id，回调是都使用这个名称
                         file_type：文件类型 image、video、doc、zip，默认图片
                         max_file_size：文件大小限制单位MB（只能是纯数字），默认2M
                         is_callback：class包含is_callback参数时回调自定义函数
                         按钮示例：<button type="button" class="layui-btn layui-btn-sm plupload_btn" id="url" file_type="video" max_file_size="20"><i class="layui-icon sm_iconfont icon-yunshangchuan"></i>选择图片</button>
                         */
                            //自动组装按钮id
                        let plupload_button_ids = new Array();
                        $(".plupload_btn").each(function () {
                            plupload_button_ids.push($(this).attr('id'))
                        })
                        //监控上传按钮的点击事件
                        $(".plupload_btn").on('click', function () {
                            plupload_btn_id = $(this).attr('id');
                        })
                        let uploader = new plupload.Uploader({
                            runtimes: 'html5,flash,silverlight,html4',
                            browse_button: plupload_button_ids,
                            chunk_size: '0',//分片上传大小
                            unique_names: true,//生成一个唯一的文件名
                            flash_swf_url: '../lib/plupload/js/Moxie.swf',
                            silverlight_xap_url: '../lib/plupload/js/Moxie.xap',
                            url: layui.setter.apiHost + '/helper/upload',
                            filters: {
                                mime_types: [
                                    {title: "Image files", extensions: mime_types['image']['suffix']},
                                    {title: "Video files", extensions: mime_types['video']['suffix']},
                                    {title: "Doc files", extensions: mime_types['doc']['suffix']},
                                    {title: "Zip files", extensions: mime_types['zip']['suffix']},
                                ],
                                prevent_duplicates: true //不允许选取重复文件
                            },
                            //resize: {quality: quality, preserve_headers: false},
                            init: {
                                FilesAdded: function (up, files) {
                                    let file_type = getFileType();
                                    let max_file_size = getMaxFileSize();
                                    plupload.each(files, function (file) {
                                        if ($.inArray(file.type, mime_types[file_type]['mime_type']) < 0) {
                                            uploader.removeFile(file);
                                            layer.msg('不允许的文件格式');
                                        } else if (file.size > (max_file_size * 1024 * 1024)) {
                                            uploader.removeFile(file);
                                            layer.msg('文件不能超过' + (max_file_size) + 'MB');
                                        } else {
                                            uploadLoading();//弹出正在上传的提示
                                        }
                                    });
                                    let next = getCallBack({id_name: plupload_btn_id, url: '', file_num: files.length});//自定义回调
                                    if (next) {
                                        uploader.start();
                                    } else {
                                        //清空上传队列
                                        $.each(files, function (index, item) {
                                            uploader.removeFile(item);
                                        })
                                        uploadEnd();
                                    }
                                },
                                BeforeUpload: function (up, file) {
                                    up.setOption({
                                        'multipart_params': getMultipartParams(file),
                                        'url': update_param.host,
                                    });
                                },
                                //全部文件上传完成
                                UploadComplete: function (up, files) {
                                    uploadEnd();
                                },
                                //单个文件上传完成
                                FileUploaded: function (up, file, result) {
                                    file_progress_num++;
                                    getUploadFileEnd(file, result);
                                },
                                //上传进度
                                UploadProgress: function (up, files) {
                                    uploadProgress(files.percent);
                                },
                                //返回错误
                                Error: function (up, err) {
                                    uploadEnd();
                                    if (err.message) {
                                        layer.msg(err.message);
                                    } else {
                                        layer.msg('上传失败');
                                    }
                                }
                            }
                        });
                        uploader.init();
                    }
                }
            });
        }
    }

    exports('plupload', obj);
});