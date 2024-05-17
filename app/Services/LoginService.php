<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/14
 * Time: 10:29 AM
 */

namespace App\Services;

use App\Jobs\MemberReg;
use App\Models\Member\Member;
use App\Models\Member\MemberAuth;
use App\Models\Member\MemberLoginLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LoginService
{
    /**
     * 注册用户
     * @param array $data
     * @return bool|mixed
     * @throws \App\Exceptions\ApiError
     */
    public static function register(array $data)
    {
        $username = $data['username'];
        if (!$username) {
            api_error(__('api.missing_params'));
        }
        //如果不存在直接注册
        $member_data = [
            'username' => $username,
            'password' => $data['password'] ?? Str::random(10),
            'nickname' => $data['nickname'] ?? mb_substr($username, 0, 3) . '****' . mb_substr($username, -4, 4),
            'headimg' => $data['headimg'] ?? get_custom_config('member_default_headimg'),
        ];
        $profile_data = [];
        $res = Member::saveData($member_data, $profile_data);
        if ($res) {
            MemberReg::dispatch($username);//将用户注册加入队列处理后续的
        }
        return $res;
    }

    /**
     * 登陆成功操作
     * @param array $member_data
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public static function loginSuccess(array $member_data): array
    {
        if ($member_data['status'] != Member::STATUS_ON) {
            api_error(__('api.user_freeze'));
        }
        $token_service = new TokenService();
        $platform = get_platform();
        $token_data = [
            'type' => 'api',
            'id' => $member_data['id'],
            'username' => $member_data['username'],
            'openid' => $member_data['openid'] ?? '',
            'platform' => $platform,
            'device' => get_device()
        ];
        $token_name = $token_service->setToken($token_data);
        if (!$token_name) {
            api_error(__('api.fail'));
        }
        //查询账号登陆记录并删除以前的token(限制单设备登陆)
        MemberLoginLog::removeLoginUser($token_data['id'], $platform);
        //记录token到数据库
        $log = [
            'token' => $token_name,
            'm_id' => $token_data['id'],
            'ip' => request()->getClientIp(),
            'platform' => $platform,
            'version' => get_version(),
            'system' => get_system(),
            'mobile_model' => get_mobile_model()
        ];
        MemberLoginLog::query()->create($log);
        return [
            'id' => $member_data['id'],
            'username' => $member_data['username'],
            'headimg' => $member_data['headimg'],
            'nickname' => $member_data['nickname'],
            'group_id' => $member_data['group_id'],
            'token' => $token_name,
            'expire' => $token_service->getTime(),
        ];
    }

    /**
     * 校验第三方登录的
     * @param array $user_data
     * @return array|int[]
     * @throws \App\Exceptions\ApiError
     */
    public static function authCheck(array $user_data)
    {
        //查询账号是否已经存在
        $m_id = MemberAuth::query()->where(['type' => $user_data['type'], 'union_id' => $user_data['union_id']])->value('m_id');
        if ($m_id) {
            //查询用户信息
            $member_data = Member::query()->find($m_id);
            if (!$member_data) {
                api_error(__('api.fail'));
            }
            $member_data['openid'] = $user_data['openid'] ?? '';//为了方便支付的时候获取openid，这里直接在登陆的时间存到token
            return self::loginSuccess($member_data->toArray());
        } else {
            //没有绑定的缓存临时授权信息
            $cache_key = 'app_auth_info:' . get_device();
            Cache::put($cache_key, $user_data, get_custom_config('cache_time'));
            return ['id' => 0];
        }
    }

    /**
     * 绑定手机号
     * @param string $mobile
     * @param array $param
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public static function bindMobile(string $mobile = '', array $param = [])
    {
        if ($mobile && !check_mobile($mobile)) {
            api_error(__('api.missing_params'));
        }
        //获取第三方信息
        $cache_key = 'app_auth_info:' . get_device();
        $auth_data = Cache::get($cache_key);
        if (!$auth_data) {
            api_error(__('api.auth_data_error'));
        }
        $platform = get_platform();
        //没有手机号的时候是小程序直接注册
        if (!$mobile) $mobile = $platform . '_' . $auth_data['openid'];
        $member_data = Member::query()->where('username', $mobile)->first();
        if ($member_data) {
            //手机号已经注册，查询是否绑定了第三方
            if (MemberAuth::query()->where(['type' => $auth_data['type'], 'm_id' => $member_data['id']])->exists()) {
                api_error(__('api.user_mobile_is_bind'));
            }
        } else {
            //手机号还没注册过
            $member_insert_data = [
                'username' => $mobile,
                'nickname' => isset($param['nickname']) && $param['nickname'] ? $param['nickname'] : $auth_data['nickname'],
                'headimg' => isset($param['headimg']) && $param['headimg'] ? $param['headimg'] : $auth_data['headimg']
            ];
            $register = self::register($member_insert_data);
            if ($register) {
                $member_data = Member::query()->where('username', $mobile)->first();
            } else {
                api_error(__('api.fail'));
            }
        }
        //绑定第三方登录
        $member_auth_data = [
            'm_id' => $member_data['id'],
            'union_id' => $auth_data['union_id'],
            'type' => $auth_data['type'],
        ];
        $res = MemberAuth::query()->create($member_auth_data);
        if (!$res) {
            api_error(__('api.user_mobile_bind_fail'));
        }
        $member_data['openid'] = $auth_data['openid'] ?? '';//为了方便支付的时候获取openid，这里直接在登陆的
        Cache::forget($cache_key);//删除保存的授权信息
        return self::loginSuccess($member_data->toArray());
    }
}
