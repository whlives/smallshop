<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 3:39 PM
 */

namespace App\Http\Controllers\V1;

use App\Libs\Weixin\MiniProgram;
use App\Libs\Weixin\Mp;
use App\Models\Member\Member;
use App\Models\Member\MemberAuth;
use App\Models\Member\MemberLoginLog;
use App\Services\LoginService;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends BaseController
{
    /**
     * 账号密码登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        $username = $request->post('username');
        $password = $request->post('password');
        if (!$username || !$password) {
            api_error(__('api.missing_params'));
        }
        $member_data = Member::where('username', $username)->first();
        if (!$member_data) {
            api_error(__('api.user_error'));
        } elseif (!Hash::check($password, $member_data['password'])) {
            api_error(__('api.password_error'));
        } else {
            $res = LoginService::loginSuccess($member_data->toArray());
            return $this->success($res);
        }
    }


    /**
     * 手机验证码登录，没有注册的默认注册
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function speed(Request $request)
    {
        $mobile = $request->post('mobile');
        if (!$mobile) {
            api_error(__('api.missing_params'));
        } elseif (!check_mobile($mobile)) {
            api_error(__('api.invalid_params'));
        }
        $member_data = Member::where('username', $mobile)->first();
        if (!$member_data) {
            //没有注册的直接注册
            $register = LoginService::register(['username' => $mobile]);
            if ($register) {
                $member_data = Member::where('username', $mobile)->first();
            } else {
                api_error(__('api.fail'));
            }
        }
        $res = LoginService::loginSuccess($member_data->toArray());
        return $this->success($res);
    }

    /**
     * 微信公众号、开放平台登陆
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function wechat(Request $request)
    {
        $code = $request->post('code');
        if (!$code) {
            api_error(__('api.missing_params'));
        }
        $mp = new Mp();
        $auth_info = $mp->userInfo($code);
        if (isset($auth_info['openid'])) {
            $union_id = $auth_info['unionid'] ?? $auth_info['openid'];
            $user_data = [
                'union_id' => $union_id,
                'openid' => $auth_info['openid'],
                'type' => MemberAuth::TYPE_WECHAT,
                'nickname' => $auth_info['nickname'],
                'headimg' => $auth_info['headimgurl']
            ];
            $res = LoginService::authCheck($user_data);
            return $this->success($res);
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 小程序登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function miniProgram(Request $request)
    {
        $code = $request->post('code');
        if (!$code) {
            api_error(__('api.missing_params'));
        }
        $mini_program = new MiniProgram();
        $auth_info = $mini_program->sessionKey($code);
        if (isset($auth_info['openid'])) {
            $union_id = $auth_info['unionid'] ?? $auth_info['openid'];
            $user_data = [
                'union_id' => $union_id,
                'openid' => $auth_info['openid'],
                'type' => MemberAuth::TYPE_WECHAT,
                'nickname' => '',
                'headimg' => ''
            ];
            $res = LoginService::authCheck($user_data);
            return $this->success($res);
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 第三方登陆
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function auth(Request $request)
    {
        $type = (int)$request->post('type');
        $union_id = $request->post('union_id');
        $nickname = $request->post('nickname');
        $headimg = $request->post('headimg');
        if (!$type || !$union_id) {
            api_error(__('api.missing_params'));
        }
        if (!$headimg) $headimg = get_custom_config('member_default_headimg');
        if (!$nickname) $nickname = Str::random(10);
        if (!isset(MemberAuth::TYPE_DESC[$type])) {
            api_error(__('api.auth_type_error'));
        }
        $user_data = [
            'union_id' => $union_id,
            'type' => MemberAuth::TYPE_WECHAT,
            'nickname' => $nickname,
            'headimg' => $headimg
        ];
        $res = LoginService::authCheck($user_data);
        return $this->success($res);
    }

    /**
     * 绑定手机号
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function bindMobile(Request $request)
    {
        $mobile = $request->post('mobile');
        if (!$mobile) {
            api_error(__('api.missing_params'));
        }
        $res = LoginService::bindMobile($mobile);
        return $this->success($res);
    }

    /**
     * 小程序手机号绑定
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     * @throws \EasyWeChat\Kernel\Exceptions\BadResponseException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function miniProgramBindMobile(Request $request)
    {
        $code = $request->post('code');
        $headimg = $request->post('headimg');
        $nickname = $request->post('nickname');
        if (!$code) {
            api_error(__('api.missing_params'));
        }
        $mini_program = new MiniProgram();
        $auth_info = $mini_program->getPhoneNumber($code);
        $mobile = $auth_info['purePhoneNumber'] ?? '';
        if (!$mobile) {
            api_error(__('api.user_mobile_get_fail'));
        }
        $member_data = [
            'headimg' => $headimg,
            'nickname' => $nickname
        ];
        $res = LoginService::bindMobile($mobile, $member_data);
        return $this->success($res);
    }

    /**
     * 找回密码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function findPassword(Request $request)
    {
        $mobile = $request->post('mobile');
        $password = $request->post('password');
        if (!$mobile || !$password) {
            api_error(__('api.missing_params'));
        }
        $update_data['password'] = Hash::make($password);
        $res = Member::where('username', $mobile)->update($update_data);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 刷新token
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken(Request $request)
    {
        $token_service = new TokenService();
        $token_name = $token_service->getTokenName();
        $token_service->refreshToken();
        $return = [
            'token' => $token_name,
            'expire' => $token_service->getTime(),
        ];
        return $this->success($return);
    }

    /**
     * 退出登陆
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function out(Request $request)
    {
        $token_service = new TokenService();
        $token_name = $token_service->getTokenName();
        MemberLoginLog::where('token', $token_name)->update(['status' => MemberLoginLog::STATUS_OFF]);//修改登录状态
        $token_service->delToken();
        return $this->success();
    }

}