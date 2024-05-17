<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/11
 * Time: 4:35 PM
 */

namespace App\Libs\Aliyun;

use App\Libs\Upload;
use Illuminate\Support\Str;
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

    /**
     * 获取web上传token
     * @param string|null $model
     * @return array
     */
    public function getWebToken(string|null $model = 'images')
    {
        $host = 'https://' . $this->config['aliyun_oss_bucket'] . '.' . $this->config['aliyun_oss_endpoint'];
        //过期时间
        $end_time = time() + 300;
        $expiration = date("c", $end_time);
        $pos = strpos($expiration, '+');
        $expiration = mb_substr($expiration, 0, $pos);
        $expiration = $expiration . "Z";
        //前缀
        $file_name = md5(time() . Str::random(10));
        $img_dir = 'upload';
        if (config('app.debug')) {
            $img_dir = 'dev_upload';
        }
        $dir = $img_dir . '/' . $model . '/' . mb_substr($file_name, 0, 2) . '/' . mb_substr($file_name, 2, 2) . '/' . mb_substr($file_name, 4, 2) . '/';
        $condition = [0 => 'content-length-range', 1 => 0, 2 => 1048576000];
        $conditions[] = $condition;

        //表示用户上传的数据,必须是以$dir开始, 不然上传会失败,这一步不是必须项,只是为了安全起见,防止用户通过policy上传到别人的目录
        $start = [0 => 'starts-with', 1 => '$key', 2 => $dir];
        $conditions[] = $start;
        $arr = ['expiration' => $expiration, 'conditions' => $conditions];

        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->config['aliyun_key_secret'], true));

        $response = [];
        $response['accessid'] = $this->config['aliyun_key_id'];
        $response['host'] = $host;
        $response['policy'] = $base64_policy;
        $response['signature'] = $signature;
        $response['dir'] = $dir;
        $response['domain'] = $this->img_domain . '/';//图片网址
        return $response;
    }
}
