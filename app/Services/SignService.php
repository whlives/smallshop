<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/11
 * Time: 4:14 PM
 */

namespace App\Services;

class SignService
{
    /**
     * 验证签名
     * @param array $post_data
     * @return bool|void
     * @throws \App\Exceptions\ApiError
     */
    public static function checkSign(array $post_data)
    {
        if (!$post_data) return true;
        if (!isset($post_data['timestamp']) || !$post_data['timestamp']) {
            api_error(__('api.timestamp_error'));
        }
        if (time() - $post_data['timestamp'] > 20) {
            api_error(__('api.timestamp_out'));
        }
        //除去待签名参数数组中的空值和签名参数
        $filter_data = self::arrayFilter($post_data);
        $sign = self::buildSign($filter_data);//签名
        $post_sign = $post_data['sign'] ?? '';
        if (!$post_sign) $post_sign = request()->header('sign');
        if ($post_sign != $sign) {
            api_error(__('api.invalid_sign'));
        }
    }

    /**
     * 除去数组中的空值和签名参数
     * @param array $post_data
     * @return array
     */
    public static function arrayFilter(array $post_data): array
    {
        $filter_data = [];
        foreach ($post_data as $key => $val) {
            if ($key == "sign" || (!$val && $val != '0') || is_array($val)) {
                continue;
            } else {
                $filter_data[$key] = $val;
            }
        }
        ksort($filter_data);
        return $filter_data;
    }

    /**
     * 生成签名结果
     * @param array $filter_data 参数
     * @return string
     */
    public static function buildSign(array $filter_data): string
    {
        //把数组所有元素拼接成url格式
        $url_str = http_build_query($filter_data);
        $url_str = $url_str . '&key=' . get_api_key();//拼接key
        $md5_str = md5($url_str);
        return strtoupper($md5_str);
    }
}