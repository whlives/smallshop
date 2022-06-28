<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 4:11 PM
 */

namespace App\Libs;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use App\Models\System\FileLog;
use Illuminate\Support\Str;
use OSS\Core\OssException;
use OSS\OssClient;

class Upload
{
    public array $config = [];
    public array $model_type = [];
    public string $role_name = '';
    public string $img_domain = '';

    public function __construct()
    {
        $this->role_name = get_platform() ?: 'admin';
        $this->img_domain = get_custom_config('img_domain');
        $this->model_type = ['image', 'editor', 'head', 'goods', 'comment', 'video'];//文件上传类型
    }

    /**
     * 检测文件类型
     * @param $file
     * @return bool
     */
    public function checkMimeType($file): bool
    {
        $file_types = [
            'image/jpeg', 'image/png', 'image/x-ms-bmp',
            'video/mp4',
            'application/zip', 'application/x-rar', 'application/x-tar', 'application/x-compressed-tar', 'application/x-bzip-compressed-tar',
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        if (in_array($file->getMimeType(), $file_types)) {
            return true;
        }
        return false;
    }

    /**
     * 上传本地文件
     * @param $file 文件内容
     * @param string|null $model 类型
     * @return string
     */
    public function uploadLocal($file, string|null $model = ''): string
    {
        $dir = self::getDir($model);//获取存储地址
        $path = $file->store(trim($dir, '/'));//存储文件
        $url = get_custom_config('img_domain') . '/' . $path;
        $title = $file->getClientOriginalName();
        FileLog::create(['title' => $title, 'url' => $url, 'type' => FileLog::TYPE_FILE]);
        return $url;
    }

    /**
     * 存储目录
     * @param string|null $model
     * @return string
     */
    public function getDir(string|null $model = ''): string
    {
        if (!in_array($model, $this->model_type)) {
            $model = 'other';
        }
        $dev_dir = config('app.debug') ? 'dev_upload' : 'upload';
        $file_dir = md5(time() . Str::random(10));
        return $dev_dir . '/' . $model . '/' . substr($file_dir, 0, 2) . '/' . substr($file_dir, 2, 2) . '/' . substr($file_dir, 4, 2) . '/';
    }

    /**
     * 获取小程序二维码的存储目录(根据二维码名称可以保证相同的二维码路径不变)
     * @param string $filename
     * @return string
     */
    public function getQrcodeDir(string $filename): string
    {
        $model = 'qrcode';
        $dev_dir = config('app.debug') ? 'dev_upload' : 'upload';
        $file_dir = md5($filename);
        return $dev_dir . '/' . $model . '/' . substr($file_dir, 0, 2) . '/' . substr($file_dir, 2, 2) . '/' . substr($file_dir, 4, 2) . '/';
    }
}
