<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Seller\Seller;

use App\Http\Controllers\Seller\BaseController;
use App\Models\Areas;
use App\Models\Seller\SellerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AddressController extends BaseController
{
    public int $seller_id;

    public function __construct()
    {
        $this->seller_id = $this->getUserId();
    }

    /**
     * 列表获取
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        //搜索
        $where = [
            ['seller_id', $this->seller_id]
        ];
        $query = SellerAddress::query()->select('id', 'seller_id', 'full_name', 'tel', 'prov_name', 'city_name', 'area_name', 'address', 'default')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list->toArray() as $value) {
            $_item = $value;
            $_item['address'] = $value['prov_name'] . $value['city_name'] . $value['area_name'] . $value['address'];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 根据id获取信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $data = SellerAddress::query()->where('seller_id', $this->seller_id)->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        return $this->success($data);
    }

    /**
     * 添加编辑
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
            'tel' => 'required',
            'prov_id' => 'required|numeric',
            'city_id' => 'required|numeric',
            'area_id' => 'required|numeric',
            'address' => 'required'
        ], [
            'full_name.required' => '发货人不能为空',
            'tel.required' => '电话不能为空',
            'prov_id.numeric' => '省份只能是数字',
            'city_id.numeric' => '城市只能是数字',
            'area_id.numeric' => '地区只能是数字',
            'address.required' => '详细地址不能为空',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['full_name', 'tel', 'prov_id', 'city_id', 'area_id', 'address', 'default']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $area_name = Areas::getAreaName([$save_data['prov_id'], $save_data['city_id'], $save_data['area_id']]);
        $save_data['prov_name'] = $area_name[$save_data['prov_id']] ?? '';
        $save_data['city_name'] = $area_name[$save_data['city_id']] ?? '';
        $save_data['area_name'] = $area_name[$save_data['area_id']] ?? '';
        //如果是设置默认先把其他的全部取消默认
        if ($save_data['default'] == SellerAddress::DEFAULT_ON) {
            SellerAddress::query()->where('seller_id', $this->seller_id)->update(['default' => SellerAddress::DEFAULT_OFF]);
        }
        if ($id) {
            $res = SellerAddress::query()->where(['id' => $id, 'seller_id' => $this->seller_id])->update($save_data);
        } else {
            $save_data['seller_id'] = $this->seller_id;
            $res = SellerAddress::query()->create($save_data);
        }
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
    }

    /**
     * 删除数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function delete(Request $request)
    {
        $ids = $this->checkBatchId();
        $res = SellerAddress::query()->whereIn('id', $ids)->where('seller_id', $this->seller_id)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 获取地址
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function select(Request $request)
    {
        $where = ['seller_id' => $this->seller_id];
        $res_list = SellerAddress::query()->select('id', 'full_name', 'tel', 'prov_name', 'city_name', 'area_name', 'address')
            ->where($where)
            ->orderBy('default', 'desc')
            ->orderBy('id', 'asc')
            ->get();
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = [
                'id' => $value['id'],
                'address' => $value['full_name'] . ' ' . $value['tel'] . ' ' . $value['prov_name'] . ' ' . $value['city_name'] . ' ' . $value['area_name'] . ' ' . $value['address']
            ];
            $data_list[] = $_item;
        }
        return $this->success($data_list);
    }

}
