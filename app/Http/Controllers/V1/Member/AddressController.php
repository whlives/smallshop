<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:41 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Http\Controllers\V1\BaseController;
use App\Models\Areas;
use App\Models\Member\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AddressController extends BaseController
{
    public int $m_id;

    public function __construct()
    {
        $this->m_id = $this->getUserId();
    }

    /**
     * 列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        $where = [
            'm_id' => $this->m_id
        ];
        $res_list = Address::query()->select('id', 'full_name', 'tel', 'prov_name', 'city_name', 'area_name', 'address', 'default')
            ->where($where)
            ->orderBy('id', 'desc')
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        return $this->success($res_list);
    }

    /**
     * 详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $id = (int)$request->post('id');
        if (!$id) {
            api_error(__('api.missing_params'));
        }
        $address = Address::query()->select('id', 'full_name', 'tel', 'prov_id', 'city_id', 'area_id', 'address', 'default')->where(['id' => $id, 'm_id' => $this->m_id])->first();
        if (!$address) {
            api_error(__('api.address_error'));
        }
        $area_name = Areas::getAreaName([$address['prov_id'], $address['city_id'], $address['area_id']]);
        $address['prov_name'] = $area_name[$address['prov_id']] ?? '';
        $address['city_name'] = $area_name[$address['city_id']] ?? '';
        $address['area_name'] = $area_name[$address['area_id']] ?? '';
        return $this->success($address);
    }

    /**
     * 修改保存
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function save(Request $request)
    {
        $id = (int)$request->input('id');
        //验证规则
        $validator = Validator::make($request->all(), [
            'full_name' => 'required',
            'tel' => 'required|mobile',
            'prov_id' => 'required|numeric',
            'city_id' => 'required|numeric',
            'area_id' => 'required|numeric',
            'address' => 'required',
            'default' => 'required|numeric',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(__('api.missing_params'));
        }
        $save_data = [
            'm_id' => $this->m_id
        ];
        foreach ($request->only(['full_name', 'tel', 'prov_id', 'city_id', 'area_id', 'address', 'default']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $area_name = Areas::getAreaName([$save_data['prov_id'], $save_data['city_id'], $save_data['area_id']]);
        $save_data['prov_name'] = $area_name[$save_data['prov_id']] ?? '';
        $save_data['city_name'] = $area_name[$save_data['city_id']] ?? '';
        $save_data['area_name'] = $area_name[$save_data['area_id']] ?? '';
        //如果是设置默认先把其他的全部取消默认
        if ($save_data['default'] == Address::DEFAULT_ON) {
            Address::query()->where('m_id', $this->m_id)->update(['default' => Address::DEFAULT_OFF]);
        }
        if ($id) {
            $res = Address::query()->where('id', $id)->update($save_data);
        } else {
            $res = Address::query()->create($save_data);
        }
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 删除
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function delete(Request $request)
    {
        $id = (int)$request->post('id');
        if (!$id) {
            api_error(__('api.missing_params'));
        }
        $res = Address::query()->where(['id' => $id, 'm_id' => $this->m_id])->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }

    /**
     * 设置默认
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function default(Request $request)
    {
        $id = (int)$request->post('id');
        if (!$id) {
            api_error(__('api.missing_params'));
        }
        Address::query()->where('m_id', $this->m_id)->update(['default' => Address::DEFAULT_OFF]);
        $res = Address::query()->where(['id' => $id, 'm_id' => $this->m_id])->update(['default' => Address::DEFAULT_ON]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('api.fail'));
        }
    }
}
