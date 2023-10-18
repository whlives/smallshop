<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Seller\Seller;

use App\Http\Controllers\Seller\BaseController;
use App\Models\Seller\Seller;
use App\Models\Seller\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SellerController extends BaseController
{
    public int $seller_id;

    public function __construct()
    {
        $this->seller_id = $this->getUserId();
    }

    /**
     * 当前用户信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function info(Request $request)
    {
        $seller = Seller::query()->find($this->seller_id);
        $seller_profile = SellerProfile::query()->where('seller_id', $this->seller_id)->first();
        return $this->success(array_merge($seller->toArray(), $seller_profile->toArray()));
    }

    /**
     * 修改信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function infoUpdate(Request $request)
    {
        //验证规则
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'image' => 'required',
            'invoice' => 'numeric|required',
            'email' => 'nullable|email',
            'prov_id' => 'numeric',
            'city_id' => 'numeric',
            'area_id' => 'numeric'
        ], [
            'image.required' => 'logo不能为空',
            'invoice.numeric' => '发票只能是数字',
            'invoice.required' => '发票不能为空',
            'email.email' => 'email格式错误',
            'prov_id.numeric' => '省份只能是数字',
            'city_id.numeric' => '城市只能是数字',
            'area_id.numeric' => '地区只能是数字',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $member_data = [];
        foreach ($request->only(['title', 'image', 'invoice']) as $key => $value) {
            $member_data[$key] = $value;
        }
        $profile_data = [];
        foreach ($request->only(['tel', 'email', 'prov_id', 'city_id', 'area_id', 'address', 'content']) as $key => $value) {
            $profile_data[$key] = $value;
        }
        //判断密码是否有了
        $password = $request->input('password');
        if ($password) {
            $member_data['password'] = md5($password);
        }
        $res = Seller::saveData($member_data, $profile_data, $this->seller_id);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
    }
}
