<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/11
 * Time: 4:35 PM
 */

namespace App\Libs\Aliyun;

use App\Libs\Upload;
use OSS\Core\OssException;
use OSS\OssClient;

class Oss
{
    public array $config = [];
    public string $img_domain = '';

    public function __construct()
    {
        $custom_config = get_custom_config_all();
        $this->config = [
            'aliyun_key_id' => $custom_config['aliyun_key_id'],
            'aliyun_key_secret' => $custom_config['aliyun_key_secret'],
            'aliyun_oss_endpoint' => $custom_config['aliyun_oss_endpoint'],
            'aliyun_oss_bucket' => $custom_config['aliyun_oss_bucket'],
        ];
        $this->img_domain = $custom_config['img_domain'];
    }

    /**
     * 上传图片到oss
     * @param string $oss_file_name oss文件名
     * @param string $tmp_file_name 本地文件地址
     * @return false|string
     */
    public function uploadOss(string $oss_file_name, string $tmp_file_name): bool|string
    {
        try {
            $ossClient = new OssClient($this->config['aliyun_key_id'], $this->config['aliyun_key_secret'], $this->config['aliyun_oss_endpoint']);
            $ossClient->uploadFile($this->config['aliyun_oss_bucket'], $oss_file_name, $tmp_file_name);
            return $this->img_domain . '/' . $oss_file_name;
        } catch (OssException $e) {
            return false;
        }
    }
}