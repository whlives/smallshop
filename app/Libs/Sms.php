<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/11
 * Time: 3:23 PM
 */

namespace App\Libs;

use App\Models\Member\Member;
use App\Models\System\SmsLog;
use App\Models\System\SmsTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class Sms
{
    /**
     * 发送验证码
     * @param string $mobile 手机号
     * @param string $type 类型
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public function captcha(string $mobile, string $type)
    {
        if (!isset(SmsTemplate::TYPE_DESC[$type])) {
            return __('api.captcha_type');
        }
        if (!check_mobile($mobile)) {
            return __('api.mobile_format');
        }
        //find_password、reset_password需要验证手机号码是否正确
        if (in_array($type, [SmsTemplate::TYPE_FIND_PASSWORD, SmsTemplate::TYPE_RESET_PASSWORD])) {
            if (!Member::where('username', $mobile)->exists()) {
                return __('api.user_mobile_error');
            }
        }
        //验证错误次数是否太多
        $check_error_num = self::checkErrorNum($mobile);
        if ($check_error_num !== true) {
            return $check_error_num;
        }
        //检测是否已经发送
        $cache_key = get_cache_key('captcha:', $mobile);
        $captcha = Cache::get($cache_key);
        $interval_time = get_custom_config('sms_interval_time');
        if ($captcha && $captcha['end_at'] > (time() - $interval_time)) {
            return __('api.sms_frequent');
        }
        $send_data = [
            'code' => rand(1000, 9999),
        ];
        $res = self::send($send_data, $type, $mobile);
        if ($res === true) {
            $log_data = [
                'mobile' => $mobile,
                'device' => get_device(),
                'code' => $send_data['code'],
                'end_at' => time()
            ];
            Cache::put($cache_key, $log_data, get_custom_config('cache_time'));
            return true;
        } else {
            return __('api.sms_send_fail');
        }
    }

    /**
     * 验证短信验证码
     * @param string $mobile 手机号
     * @param int $code 验证码
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public function checkCaptcha(string $mobile, int $code)
    {
        //测试环境不验证，默认不开启
        /*if (config('app.debug') && $code == '2580') {
            return true;
        }*/
        //验证错误次数是否太多
        $check_error_num = self::checkErrorNum($mobile);
        if ($check_error_num !== true) {
            return $check_error_num;
        }
        $cache_key = get_cache_key('captcha:', $mobile);
        $captcha = Cache::get($cache_key);
        $error = '';
        $out_time = get_custom_config('sms_out_time');
        if (!$captcha) {
            $error = __('api.sms_captcha_error');//错误
        } elseif ($captcha['end_at'] < (time() - $out_time)) {
            $error = __('api.sms_captcha_time_out');//超时
        } elseif ($captcha['mobile'] != $mobile) {
            $error = __('api.sms_captcha_error');//手机不匹配
        } elseif ($captcha['code'] != $code) {
            $error = __('api.sms_captcha_error');//错误
        } elseif ($captcha['device'] != get_device()) {
            $error = __('api.sms_captcha_error');//设备不匹配
        }
        if ($error) {
            self::setErrorNum($mobile);
            return $error;
        }
        Cache::forget($cache_key);//验证通过删除验证码
        return true;
    }

    /**
     * 记录错误次数
     * @param string $mobile 手机号
     * @return bool
     */
    public function setErrorNum(string $mobile)
    {
        $redis_key = 'captcha_error:' . $mobile;
        Redis::incr($redis_key);
        Redis::expire($redis_key, 300);
        return true;
    }

    /**
     * 验证错误次数
     * @param string $mobile 手机号
     * @return array|bool|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public function checkErrorNum(string $mobile)
    {
        $redis_key = 'captcha_error:' . $mobile;
        $error_num = Redis::get($redis_key);
        if ($error_num >= 5) {
            return __('api.error_num_max');
        }
        return true;
    }

    /**
     * 发送短信
     * @param array $data 参数
     * @param string $type 类型
     * @param string $mobile 手机号
     * @return string
     */
    public function send(array $data, string $type, string $mobile)
    {
        $content = self::getTemplate($data, $type);
        if (!$content) return '模板不存在';
        $res = self::sendTo($content, $mobile);
        //添加发送记录
        $log_data = [
            'mobile' => $mobile,
            'content' => $content,
            'error_msg' => $res
        ];
        SmsLog::create($log_data);
        return $res;
    }

    /**
     * 获取短信模板并组装参数
     * @param array $data 参数
     * @param string $type 类型
     * @return string
     */
    public function getTemplate(array $data, string $type)
    {
        $template = SmsTemplate::where('type', $type)->value('content');
        if (!$template) return '';
        $find = $replace = [];
        foreach ($data as $key => $val) {
            $find[] = '{$' . $key . '}';
            $replace[] = $val;
        }
        $content = str_replace($find, $replace, $template);
        return trim($content);
    }

    /**
     * 请求发送短信接口
     * @param string $content
     * @param string $mobile
     * @return bool|mixed|string
     */
    public function sendTo(string $content, string $mobile)
    {
        $post_data = [
            'account' => get_custom_config('sms_account'),
            'password' => get_custom_config('sms_password'),
            'msg' => urlencode($content),
            'phone' => $mobile,
            'report' => true
        ];
        $url = 'http://smssh1.253.com/msg/send/json';
        $post_data = json_encode($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=utf-8'   //json版本需要填写  Content-Type: application/json;
            ]
        );
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); //若果报错 name lookup timed out 报错时添加这一行代码
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $ret = curl_exec($ch);
        if (false == $ret) {
            $result = curl_error($ch);
        } else {
            $rsp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 " . $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
        curl_close($ch);
        if (!is_null(json_decode($result))) {
            $output = json_decode($result, true);
            if (isset($output['code']) && $output['code'] == '0') {
                return true;
            } else {
                return $output['errorMsg'];
            }
        } else {
            return $result;
        }
    }
}