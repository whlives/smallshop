<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 8:53 PM
 */

namespace App\Http\Controllers\Admin;

use App\Libs\Sms;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminLoginLog;
use App\Models\Admin\AdminRole;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Mews\Captcha\Facades\Captcha;
use Validator;

class LoginController extends BaseController
{
    public TokenService $token_service;
    public Sms $sms;

    public function __construct()
    {
        $this->sms = new Sms();
        $this->token_service = new TokenService();
        $this->token_service->setTime(3600 * 2);
    }

    /**
     * 登陆
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        //验证规则Validator
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
            'captcha_code' => 'required',
            'captcha_key' => 'required',
        ], [
            'username.required' => '用户名不能为空',
            'password.required' => '密码不能为空',
            'captcha_code.required' => '验证码不能为空',
            'captcha_key.required' => '验证码不能为空',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $admin_login_sms_captcha = get_custom_config('admin_login_sms_captcha');
        if ($admin_login_sms_captcha) {
            //需要发送短信验证
            $code = $request->post('code');
            if (!$code) {
                //需要发送短信
                return $this->success(['sms_captcha' => $admin_login_sms_captcha]);
            }
            $admin_data = self::checkUsername();
            //验证短信验证码
            $check_captcha = $this->sms->checkCaptcha($admin_data['tel'], $code);
            if ($check_captcha !== true) {
                api_error($check_captcha);
            }
        } else {
            //只需要验证图片验证码
            if (!captcha_api_check($request->input('captcha_code'), $request->input('captcha_key'), 'flat')) {
                api_error(__('admin.captcha_error'));
            }
            $admin_data = self::checkUsername();
        }
        return self::loginSuccess($admin_data);
    }

    /**
     * 获取短信验证码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function smsCaptcha(Request $request)
    {
        //验证规则Validator
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ], [
            'username.required' => '用户名不能为空',
            'password.required' => '密码不能为空',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        //验证图片验证码
        if (!captcha_api_check($request->input('captcha_code'), $request->input('captcha_key'), 'flat')) {
            api_error(__('admin.captcha_error'));
        }
        //未开启短信验证的时候不允许发送短信
        $admin_login_sms_captcha = get_custom_config('admin_login_sms_captcha');
        if (!$admin_login_sms_captcha) {
            api_error(__('admin.invalid_request'));
        }
        $admin_data = self::checkUsername();
        $res = $this->sms->captcha($admin_data['tel'], 'admin_login');
        if ($res === true) {
            $mobile = substr($admin_data['tel'], 0, 3) . '****' . substr($admin_data['tel'], 7, 4);
            return $this->success(['mobile' => $mobile]);
        } else {
            api_error($res);
        }
    }

    /**
     * 验证用户信息
     * @return mixed
     * @throws \App\Exceptions\ApiError
     */
    private function checkUsername()
    {
        $username = request()->post('username');
        $password = request()->post('password');
        $admin_data = Admin::where('username', $username)->first();
        if (!$admin_data) {
            api_error(__('admin.admin_user_error'));
        } elseif (!Hash::check($password, $admin_data['password'])) {
            api_error(__('admin.admin_password_error'));
        } elseif ($admin_data['status'] != Admin::STATUS_ON) {
            api_error(__('admin.admin_in_blacklist'));
        }
        return $admin_data->toArray();
    }

    /**
     * 登陆成功处理
     * @param array $admin_data
     * @return \Illuminate\Http\JsonResponse
     */
    private function loginSuccess(array $admin_data)
    {
        $ip = request()->getClientIp();
        $user_agent = request()->userAgent();
        $data = [
            'type' => 'admin',
            'id' => $admin_data['id'],
            'username' => $admin_data['username'],
            'role_id' => $admin_data['role_id'],
            'ip' => $ip,
            'user_agent' => $user_agent
        ];
        $token = $this->token_service->setToken($data);
        //非测试环境发送登录验证码(需要后台先开启提示)
        if (!config('app.debug') && get_custom_config('admin_login_sms_notice')) {
            //判断ip和设备是否和上一条有变化，有变化需要发送短信
            $admin_login_log = AdminLoginLog::where('m_id', $admin_data['id'])->orderBy('id', 'desc')->first();
            if (!$admin_login_log || $admin_login_log['ip'] != $ip || $admin_login_log['user_agent'] != $user_agent) {
                $send_data = [
                    'username' => $admin_data['username'],
                    'platform' => get_custom_config('web_name'),
                    'ip' => $ip
                ];
                $this->sms->send($send_data, 'admin_login_notice', $admin_data['tel']);
            }
        }
        //记录登录日志(这里需要在发送短信之后，否则设备判断失效)
        $log = [
            'token' => $token,
            'm_id' => $admin_data['id'],
            'user_agent' => $user_agent,
            'ip' => $ip
        ];
        AdminLoginLog::create($log);
        $role_right = AdminRole::adminRight($admin_data['role_id'], true);//获取最新的权限
        $return = [
            'username' => $admin_data['username'],
            'role_id' => $admin_data['role_id'],
            'token' => $token,
            'button' => $role_right['button'],
            'expire' => $this->token_service->getTime(),
        ];
        return $this->success($return);
    }

    /**
     * 验证码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function captcha(Request $request)
    {
        $aa = Captcha::create('flat', true);
        return $this->success($aa);
    }

    /**
     * 刷新token
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken(Request $request)
    {
        $token_name = $this->token_service->getTokenName();
        $this->token_service->refreshToken();
        $return = [
            'token' => $token_name,
            'expire' => $this->token_service->getTime(),
        ];
        return $this->success($return);
    }

    /**
     * 退出登陆
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logOut(Request $request)
    {
        $token = $this->token_service->getToken();
        AdminLoginLog::removeLoginStatus($token['id']);
        $this->token_service->delToken();
        return $this->success();
    }
}
