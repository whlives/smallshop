/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 3:30 PM
 */

layui.define(function (exports) {
    let admin = layui.admin,
        $ = layui.$;

    /**
     * 获取随机字符
     * @param len
     * @returns {string}
     */
    function random_str(len) {
        len = len || 32;
        let chars = 'ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678';
        let maxPos = chars.length;
        let str = '';
        for (let i = 0; i < len; i++) {
            str += chars.charAt(Math.floor(Math.random() * maxPos));
        }
        return str;
    }

    /**
     * 阿里云上传
     * @param update_param 参数
     * @param client sdk信息
     * @param file 文件
     * @param insertFn 回调函数
     */
    function oss_upload(update_param, client, file, insertFn) {
        let name = random_str(32);
        let file_type = file['name'].split('.').pop();
        let new_file_name = update_param.dirname + name + '.' + file_type;
        client.put(new_file_name, file)
            .then(function (res) {
                //上传图片，返回结果，将图片插入到编辑器中
                insertFn(res.url)
            }).catch(function (err) {
        })
    }

    /**
     * 本地上传
     * @param file 文件
     * @param insertFn 回调函数
     */
    function local_upload(file, insertFn) {
        let formData = new FormData();
        formData.append('file', file)
        admin.req({
            url: layui.setter.apiHost + '/helper/upload',
            data: formData,
            contentType: false,
            processData: false,
            success: function (result) {
                if (result.code == 0) {
                    insertFn(result.data.url)
                } else {
                    layer.msg(result.msg);
                }
            }
        });
    }

    /**
     * 调用示例
     * 默认字段content，editor.init('默认内容'，'content');
     * <div class="editor">
     *     <div id="toolbar_editor_content" class="toolbar"></div>
     *     <div id="editor_content" class="content"></div>
     * </div>
     * <textarea name="content" id="content" class="layui-hide"></textarea>
     * 字段desc，editor.init('默认内容','desc');
     * <div class="editor">
     *     <div id="toolbar_editor_desc" class="toolbar"></div>
     *     <div id="editor_desc" class="content"></div>
     * </div>
     * <textarea name="desc" id="desc" class="layui-hide"></textarea>
     */

    let obj = {
        /**
         * 初始化编辑器
         * @param content 默认内容
         * @param editor_id 字段名称
         * @param mode 编辑器模式
         */
        init: function (content = '', editor_id = 'content', mode = 'default') {
            admin.req({
                url: layui.setter.apiHost + '/helper/aliyun_sts',
                data: {model: 'editor'},
                success: function (result) {
                    if (result.code == 0) {
                        let update_param = result.data;
                        let client;
                        if (update_param['upload_type'] == 1) {
                            //阿里云上传参数
                            client = new OSS({
                                region: update_param.region,
                                accessKeyId: update_param.access_key_id,
                                accessKeySecret: update_param.access_key_secret,
                                bucket: update_param.bucket,
                                stsToken: update_param.sts_token,
                                endpoint: update_param.domain,
                                cname: true,
                                secure: true,
                                refreshSTSTokenInterval: 1800000,//刷新临时访问凭证的时间间隔，单位为毫秒
                            });
                        }
                        const {createEditor, createToolbar} = window.wangEditor;
                        const editorConfig = {MENU_CONF: {}};
                        //同步内容到文本框
                        editorConfig.onChange = (editor) => {
                            const html = editor.getHtml();
                            $('[name="' + editor_id + '"]').val(html)
                        }
                        //自定义上传图片
                        editorConfig.MENU_CONF['uploadImage'] = {
                            maxFileSize: 2 * 1024 * 1024,//2M
                            // 自定义上传
                            async customUpload(file, insertFn) {
                                if (update_param['upload_type'] == 1) {
                                    oss_upload(update_param, client, file, insertFn);
                                } else {
                                    local_upload(file, insertFn);
                                }
                            }
                        }
                        //自定义上传视频
                        editorConfig.MENU_CONF['uploadVideo'] = {
                            maxFileSize: 100 * 1024 * 1024,//100M
                            allowedFileTypes: ['video/mp4'],
                            // 自定义上传
                            async customUpload(file, insertFn) {
                                if (update_param['upload_type'] == 1) {
                                    oss_upload(update_param, client, file, insertFn);
                                } else {
                                    local_upload(file, insertFn);
                                }
                            }
                        }
                        //创建编辑器
                        const editor = createEditor({
                            selector: '#editor_' + editor_id,
                            html: content,
                            config: editorConfig,
                            mode: mode //模式
                        })
                        //创建工具栏
                        const toolbar = createToolbar({
                            editor,
                            selector: '#toolbar_editor_' + editor_id,
                            //config: toolbarConfig,
                            mode: mode //模式
                        })
                    }
                }
            });
        }
    }

    exports('editor', obj);
});