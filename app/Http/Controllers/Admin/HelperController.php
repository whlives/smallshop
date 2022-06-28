<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 10:32 AM
 */

namespace App\Http\Controllers\Admin;

use App\Libs\Aliyun\Sts;
use App\Libs\Upload;
use App\Models\Areas;
use Illuminate\Http\Request;

class HelperController extends BaseController
{

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
                api_error(__('admin.fail'));
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
            api_error(__('admin.fail'));
        }
        $model = $request->post('model');
        if (!$request->hasFile('file')) {
            api_error(__('admin.upload_file_exists'));
        }
        $upload = new Upload();
        $file = $request->file('file');
        $check_type = $upload->checkMimeType($file);
        if (!$check_type) {
            api_error(__('admin.upload_file_type_error'));
        }
        $url = $upload->uploadLocal($file, $model);
        return $this->success(['url' => $url]);
    }

    /**
     * 获取地区
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function area(Request $request)
    {
        $parent_id = (int)$request->input('parent_id');
        $area_list = Areas::getArea($parent_id);
        return $this->success($area_list);
    }

}