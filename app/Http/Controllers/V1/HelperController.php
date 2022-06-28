<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 3:39 PM
 */

namespace App\Http\Controllers\V1;

use App\Libs\Aliyun\Sts;
use App\Libs\Sms;
use App\Libs\Upload;
use App\Libs\Weixin\Mp;
use App\Models\Areas;
use App\Models\System\ExpressCompany;
use App\Models\Tool\Adv;
use App\Models\Tool\AdvGroup;
use Illuminate\Http\Request;

class HelperController extends BaseController
{
    /**
     * 获取验证码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function captcha(Request $request)
    {
        $type = $request->post('type');
        $mobile = $request->post('mobile');
        if (!$type || !$mobile) {
            api_error(__('api.missing_params'));
        }
        $sms = new Sms();
        $res = $sms->captcha($mobile, $type);
        if ($res === true) {
            return $this->success();
        } else {
            api_error($res);
        }
    }

    /**
     * 获取aliyun oss sts
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \AlibabaCloud\Client\Exception\ClientException
     * @throws \App\Exceptions\ApiError
     */
    public function aliyunSts(Request $request)
    {
        $model = $request->post('model');
        $upload_type = get_custom_config('upload_type');
        if ($upload_type == 1) {
            $sts = new Sts();
            $sts_data = $sts->getOssSts($model);
            if ($sts_data) {
                $sts_data['upload_type'] = $upload_type;
                return $this->success($sts_data);
            } else {
                api_error(__('api.fail'));
            }
        } else {
            return $this->success(['upload_type' => $upload_type]);
        }
    }

    /**
     * 上传本地文件
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function upload(Request $request)
    {
        $upload_type = get_custom_config('upload_type');
        if ($upload_type) {
            api_error(__('api.fail'));
        }
        $model = $request->post('model');
        if (!$request->hasFile('file')) {
            api_error(__('api.upload_file_exists'));
        }
        $upload = new Upload();
        $file = $request->file('file');
        $check_type = $upload->checkMimeType($file);
        if (!$check_type) {
            api_error(__('api.upload_file_type_error'));
        }
        $url = $upload->uploadLocal($file, $model);
        return $this->success(['url' => $url]);
    }

    /**
     * 获取广告信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function adv(Request $request)
    {
        $code = (int)$request->route('code');
        if (!$code) {
            api_error(__('api.missing_params'));
        }
        $group_where = [
            ['code', $code],
            ['status', AdvGroup::STATUS_ON]
        ];
        $group_id = AdvGroup::where($group_where)->value('id');
        if (!$group_id) {
            api_error(__('api.content_is_empty'));
        }
        $adv_where = [
            ['group_id', $group_id],
            ['status', Adv::STATUS_ON],
            ['start_at', '<=', get_date()],
            ['end_at', '>=', get_date()]
        ];
        $res_list = Adv::select('title', 'image', 'target_type', 'target_value')
            ->where($adv_where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        return $this->success($res_list);
    }

    /**
     * 获取地区
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function area(Request $request)
    {
        $parent_id = (int)$request->route('parent_id', 0);
        $res_list = Areas::getArea($parent_id);
        return $this->success($res_list);
    }

    /**
     * 快递公司
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function expressCompany(Request $request)
    {
        $where = [
            'status' => ExpressCompany::STATUS_ON
        ];
        $res_list = ExpressCompany::select('id', 'title')
            ->where($where)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        return $this->success($res_list);
    }

    /**
     * 微信jssdk
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function wxJssdk(Request $request)
    {
        $url = $request->input('url');
        if (!$url) {
            api_error(__('api.missing_params'));
        }
        $mp = new Mp();
        $jssdk = $mp->jsSdk($url);
        return $this->success($jssdk);
    }

}