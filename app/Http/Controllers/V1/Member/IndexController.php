<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:41 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Exceptions\ApiError;
use App\Http\Controllers\V1\BaseController;
use App\Libs\Sms;
use App\Libs\Weixin\MiniProgram;
use App\Models\Member\Member;
use App\Models\Member\MemberAuth;
use App\Models\Member\MemberProfile;
use App\Models\Order\Order;
use App\Models\Order\Refund;
use EasyWeChat\Kernel\Exceptions\BadResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class IndexController extends BaseController
{
    public int $m_id;

    public function __construct()
    {
        $this->m_id = $this->getUserId();
    }

    /**
     * 首页
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function index(Request $request)
    {
        $member_data = Member::query()->find($this->m_id);
        if (!$member_data) {
            api_error(__('api.invalid_token'));
        }
        $member_profile = $member_data->profile;
        //待付款订单
        $wait_pay_num = Order::query()->where(['m_id' => $this->m_id, 'status' => Order::STATUS_WAIT_PAY])->count();
        //待发货订单
        $wait_send_num = Order::query()->where(['m_id' => $this->m_id, 'status' => Order::STATUS_PAID])->count();
        //待收货订单
        $wait_received_num = Order::query()->where(['m_id' => $this->m_id])->whereIn('status', [Order::STATUS_SHIPMENT, Order::STATUS_PART_SHIPMENT])->count();
        //待评价订单
        $wait_comment_num = Order::query()->where(['m_id' => $this->m_id])->whereIn('status', [Order::STATUS_DONE, Order::STATUS_COMPLETE])->whereNull('comment_at')->count();
        //售后订单
        $refund_num = Refund::query()->where(['m_id' => $this->m_id])->whereNotIn('status', [Refund::STATUS_DONE, Refund::STATUS_CUSTOMER_CANCEL])->count();
        $auth_type = MemberAuth::query()->where(['m_id' => $this->m_id])->pluck('type')->toArray();
        //获取分享小程序码
        $mini_qrcode = $member_profile['mini_qrcode'];
        if (!$mini_qrcode) {
            $qrcode_param = ['m_id' => $this->m_id];
            $mini_program = new MiniProgram();
            $mini_qrcode = $mini_program->createShareQrcode($qrcode_param);
            if ($mini_qrcode) {
                MemberProfile::query()->where(['member_id' => $this->m_id])->update(['mini_qrcode' => $mini_qrcode]);
            }
        }
        $return = [
            'id' => $member_data['id'],
            'username' => $member_data['username'],
            'nickname' => $member_data['nickname'],
            'headimg' => $member_data['headimg'],
            'group_title' => Member::group($member_data['group_id']),
            'wait_pay_num' => $wait_pay_num,
            'wait_send_num' => $wait_send_num,
            'wait_received_num' => $wait_received_num,
            'wait_comment_num' => $wait_comment_num,
            'refund_num' => $refund_num,
            'qrcode' => $mini_qrcode,
            'is_set_pay_pw' => $member_data['pay_password'] ? 1 : 0,
            'is_bind_wechat' => in_array(MemberAuth::TYPE_WECHAT, $auth_type) ? 1 : 0,
            'is_bind_weibo' => in_array(MemberAuth::TYPE_WEIBO, $auth_type) ? 1 : 0,
            'is_bind_qq' => in_array(MemberAuth::TYPE_QQ, $auth_type) ? 1 : 0,
            'is_bind_mobile' => check_mobile($member_data['username']) ? 1 : 0,
            'is_audit' => get_custom_config('is_audit'),
        ];
        return $this->success($return);
    }

    /**
     * 个人信息
     * @param Request $request
     * @return JsonResponse
     * @throws ApiError
     */
    public function info(Request $request)
    {
        $member_data = Member::query()->find($this->m_id);
        if (!$member_data) {
            api_error(__('api.invalid_token'));
        }
        $profile = $member_data->profile;
        $user_info = [
            'id' => $member_data['id'],
            'username' => $member_data['username'],
            'nickname' => $member_data['nickname'],
            'headimg' => $member_data['headimg'],
            'group_title' => Member::group($member_data['group_id']),
            'email' => $profile['email'],
            'sex' => MemberProfile::SEX_DESC[$profile['sex']]
        ];
        return $this->success($user_info);
    }

    /**
     * 修改个人资料
     * @param Request $request
     * @return JsonResponse
     */
    public function saveInfo(Request $request)
    {
        $member_data = $profile_data = [];
        foreach ($request->only(['nickname', 'headimg', 'full_name']) as $key => $value) {
            $member_data[$key] = $value;
        }
        foreach ($request->only(['email', 'sex']) as $key => $value) {
            $profile_data[$key] = $value;
        }
        if ($member_data) {
            Member::query()->where('id', $this->m_id)->update($member_data);
        }
        if ($profile_data) {
            MemberProfile::query()->where('member_id', $this->m_id)->update($profile_data);
        }
        return $this->success();
    }

    /**
     * 修改用户密码
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function upPassword(Request $request)
    {
        $old_password = $request->post('old_password');
        $new_password = $request->post('new_password');
        if (!$old_password || !$new_password) {
            api_error(__('api.missing_params'));
        }
        $member_data = Member::query()->find($this->m_id);
        if (!Hash::check($old_password, $member_data['password'])) {
            api_error(__('api.old_password_error'));
        }
        $update_data['password'] = Hash::make($new_password);
        $res = Member::query()->where('id', $this->m_id)->update($update_data);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 设置用户支付密码
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function setPayPassword(Request $request)
    {
        $password = $request->post('password');
        if (!$password) {
            api_error(__('api.missing_params'));
        }
        $member_data = Member::query()->find($this->m_id);
        if ($member_data['pay_password']) {
            api_error(__('api.pay_password_isset'));
        }
        $update_data['pay_password'] = Hash::make($password);
        $res = Member::query()->where('id', $this->m_id)->update($update_data);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 修改用户支付密码
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function upPayPassword(Request $request)
    {
        $old_password = $request->post('old_password');
        $new_password = $request->post('new_password');
        if (!$old_password || !$new_password) {
            api_error(__('api.missing_params'));
        }
        $member_data = Member::query()->find($this->m_id);
        if (!Hash::check($old_password, $member_data['pay_password'])) {
            api_error(__('api.old_pay_password_error'));
        }
        $update_data['pay_password'] = Hash::make($new_password);
        $res = Member::query()->where('id', $this->m_id)->update($update_data);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 重置用户支付密码
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function resetPayPassword(Request $request)
    {
        $password = $request->post('password');
        $code = (int)$request->post('code');
        if (!$password || !$code) {
            api_error(__('api.missing_params'));
        }
        $member_data = Member::query()->find($this->m_id);
        $sms = new Sms();
        $check_captcha = $sms->checkCaptcha($member_data['username'], $code);
        if ($check_captcha !== true) {
            api_error($check_captcha);
        }
        $update_data['pay_password'] = Hash::make($password);
        $res = Member::query()->where('id', $this->m_id)->update($update_data);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 第三方解绑
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function removeAuthBind(Request $request)
    {
        $type = $request->post('type');
        if (!isset(MemberAuth::TYPE_DESC[$type])) {
            api_error(__('api.missing_params'));
        }
        $res = MemberAuth::query()->where(['m_id' => $this->m_id, 'type' => $type])->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 绑定上级
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     */
    public function bindParent(Request $request)
    {
        $parent_id = (int)$request->post('parent_id');
        if (!$this->m_id || !$parent_id) {
            api_error(__('api.missing_params'));
        }
        $res = Member::bindParent($this->m_id, $parent_id);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 绑定微信手机号码
     * @param Request $request
     * @return JsonResponse|void
     * @throws ApiError
     * @throws BadResponseException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function miniProgramBindMobile(Request $request)
    {
        $code = $request->post('code');
        if (!$code) {
            api_error(__('api.missing_params'));
        }
        $mini_program = new MiniProgram();
        $auth_info = $mini_program->getPhoneNumber($code);
        $mobile = $auth_info['purePhoneNumber'] ?? '';
        if (!$mobile) {
            api_error(__('api.user_mobile_get_fail'));
        }
        //查询手机号码是否已经绑定其他账号
        if (Member::where('username', $mobile)->exists()) {
            api_error(__('api.user_mobile_is_bind'));
        }
        $res = Member::where('id', $this->m_id)->update(['username' => $mobile]);
        if ($res) {
            return $this->success(true);
        } else {
            api_error(__('api.fail'));
        }
    }

}
