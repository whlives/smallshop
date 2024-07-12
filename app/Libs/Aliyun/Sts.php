<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/11
 * Time: 4:35 PM
 */

namespace App\Libs\Aliyun;

use AlibabaCloud\SDK\Sts\V20150401\Models\AssumeRoleRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use App\Libs\Upload;
use Darabonba\OpenApi\Models\Config;
use Symfony\Component\HttpClient\Exception\ClientException;

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

    public function createClient()
    {
        $config = new Config([
            "accessKeyId" => $this->config['aliyun_key_id'],
            "accessKeySecret" => $this->config['aliyun_key_secret']
        ]);
        $config->endpoint = 'sts.aliyuncs.com';
        return new \AlibabaCloud\SDK\Sts\V20150401\Sts($config);
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
        $client = self::createClient();
        $assumeRoleRequest = new AssumeRoleRequest([
            "durationSeconds" => 1800,
            "policy" => '{"Statement": [{"Action": ["*"],"Effect": "Allow","Resource": ["*"]}],"Version":"1"}',
            "roleArn" => $this->config['aliyun_sts_rolearn'],
            "roleSessionName" => $this->role_name
        ]);
        $runtime = new RuntimeOptions([]);
        try {
            // 复制代码运行请自行打印 API 的返回值
            $res = $client->assumeRoleWithOptions($assumeRoleRequest, $runtime);
            $credentials = $res->body->credentials;
            $dir = $upload->getDir($model);
            $policy = self::getPolicy($dir, $credentials->expiration);
            $signature = self::getSignature($policy, $credentials->accessKeySecret);
            return [
                'access_key_id' => $credentials->accessKeyId,
                'access_key_secret' => $credentials->accessKeySecret,
                'expiration' => $credentials->expiration,
                'sts_token' => $credentials->securityToken,
                'bucket' => $this->config['aliyun_oss_bucket'],
                'endpoint' => $this->config['aliyun_oss_endpoint'],
                'region' => $this->config['aliyun_oss_region'],
                'host' => 'https://' . $this->config['aliyun_oss_bucket'] . '.' . $this->config['aliyun_oss_endpoint'],
                'policy' => $policy,
                'signature' => $signature,
                'dirname' => $dir,
                'domain' => $this->img_domain
            ];
        } catch (\Exception $error) {
            return false;
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
        $arr = [
            'expiration' => $expiration,
            'conditions' => [
                ['bucket' => $this->config['aliyun_oss_bucket']],
                ['content-length-range', 0, 1048576000],//最大文件大小.用户可以自己设置
                ['starts-with', '$key', $dir],//用户上传必须是以$dir开始，不然上传会失败，这一步不是必须项，只是为了安全起见，防止用户通过policy上传到别人的目录。
            ]
        ];
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
