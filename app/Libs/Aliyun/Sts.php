<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/11
 * Time: 4:35 PM
 */

namespace App\Libs\Aliyun;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use App\Libs\Upload;

class Sts
{
    public array $config = [];
    public string $role_name = '';
    public string $img_domain = '';

    public function __construct()
    {
        $custom_config = get_custom_config_all();
        $this->config = [
            'aliyun_key_id' => $custom_config['aliyun_key_id'],
            'aliyun_key_secret' => $custom_config['aliyun_key_secret'],
            'aliyun_sts_rolearn' => $custom_config['aliyun_sts_rolearn'],
            'aliyun_oss_bucket' => $custom_config['aliyun_oss_bucket'],
            'aliyun_oss_endpoint' => $custom_config['aliyun_oss_endpoint'],
            'aliyun_oss_region' => $custom_config['aliyun_oss_region'],
        ];
        $this->role_name = get_platform() ?: 'admin';
        $this->img_domain = $custom_config['img_domain'];
    }

    /**
     * 获取oss sts
     * @param string|null $model
     * @return array|false
     * @throws ClientException
     */
    public function getOssSts(string|null $model = ''): bool|array
    {
        $upload = new Upload();
        AlibabaCloud::accessKeyClient($this->config['aliyun_key_id'], $this->config['aliyun_key_secret'])
            ->regionId('cn-hangzhou')
            ->asDefaultClient();
        try {
            $result = AlibabaCloud::rpc()
                ->product('Sts')
                ->scheme('https') // https | http
                ->version('2015-04-01')
                ->action('AssumeRole')
                ->method('POST')
                ->host('sts.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => "cn-hangzhou",
                        'RoleArn' => $this->config['aliyun_sts_rolearn'],
                        'RoleSessionName' => $this->role_name,
                        'DurationSeconds' => 1800
                    ],
                ])
                ->request();
            $res = $result->toArray();
            if (is_array($res)) {
                $credentials = $res['Credentials'];
                $dir = $upload->getDir($model);
                $policy = self::getPolicy($dir, $credentials['Expiration']);
                $signature = self::getSignature($policy, $credentials['AccessKeySecret']);
                $sts_data = [
                    'access_key_id' => $credentials['AccessKeyId'],
                    'access_key_secret' => $credentials['AccessKeySecret'],
                    'expiration' => $credentials['Expiration'],
                    'sts_token' => $credentials['SecurityToken'],
                    'bucket' => $this->config['aliyun_oss_bucket'],
                    'endpoint' => $this->config['aliyun_oss_endpoint'],
                    'region' => $this->config['aliyun_oss_region'],
                    'host' => 'https://' . $this->config['aliyun_oss_bucket'] . '.' . $this->config['aliyun_oss_endpoint'],
                    'policy' => $policy,
                    'signature' => $signature,
                    'dirname' => $dir,
                    'domain' => $this->img_domain
                ];
                return $sts_data;
            } else {
                return false;
            }
        } catch (ClientException $e) {
            return false;
            //echo $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            return false;
            //echo $e->getErrorMessage() . PHP_EOL;
        }
    }

    /**
     * 权限策略
     * @param string $dir
     * @param string $expiration
     * @return string
     */
    public function getPolicy(string $dir, string $expiration): string
    {
        //最大文件大小.用户可以自己设置
        $condition = [0 => 'content-length-range', 1 => 0, 2 => 1048576000];
        $conditions[] = $condition;
        //表示用户上传的数据，必须是以$dir开始，不然上传会失败，这一步不是必须项，只是为了安全起见，防止用户通过policy上传到别人的目录。
        $start = [0 => 'starts-with', 1 => '$key', 2 => $dir];
        $conditions[] = $start;
        $arr = ['expiration' => $expiration, 'conditions' => $conditions];
        $policy = json_encode($arr);
        return base64_encode($policy);
    }

    /**
     * 签名
     * @param string $policy
     * @param string $access_key_secret
     * @return string
     */
    public function getSignature(string $policy, string $access_key_secret): string
    {
        return base64_encode(hash_hmac('sha1', $policy, $access_key_secret, true));
    }
}