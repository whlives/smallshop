<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:36 PM
 */

namespace App\Http\Controllers\Admin\Goods;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Goods\Delivery;
use App\Models\Seller\Seller;
use Illuminate\Http\Request;
use Validator;

class DeliveryController extends BaseController
{
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
        $where = [];
        $title = $request->input('title');
        $seller_title = $request->input('seller_title');
        if ($title) $where[] = ['title', 'like', '%' . $title . '%'];
        if ($seller_title) {
            $seller_id = Seller::query()->where('title', $seller_title)->value('id');
            if ($seller_id) {
                $where[] = ['seller_id', $seller_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        $query = Delivery::query()->select('id', 'title', 'open_default', 'price_type', 'status', 'created_at')
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
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['open_default'] = Delivery::OPEN_DEFAULT_DESC[$value['open_default']];
            $_item['price_type'] = Delivery::PRICE_TYPE_DESC[$value['price_type']];
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
        $data = Delivery::query()->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        //分组地区信息
        $select_area_id = [];
        $group_data = [];
        $group_area_id = json_decode($data['group_area_id'], true);
        $group_json = json_decode($data['group_json'], true);
        if ($group_area_id && $group_json) {
            foreach ($group_json as $key => $value) {
                $value['list_id'] = $key;
                $value['prov_id'] = $group_area_id[$key];
                $select_area_id = array_merge($select_area_id, $group_area_id[$key]);
                $group_data[] = $value;
            }
        }
        $data['group_area_id'] = $select_area_id;
        $data['group_json'] = $group_data;
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
            'title' => 'required',
            'type' => 'required|numeric',
            'free_type' => 'required|numeric',
            'free_price' => 'required|price',
            'first_weight' => 'required|numeric',
            'first_price' => 'required|price',
            'second_weight' => 'required|numeric',
            'second_price' => 'required|price',
            'price_type' => 'required|numeric',
        ], [
            'title.required' => '标题不能为空',
            'type.required' => '类型不能为空',
            'type.numeric' => '类型只能是数字',
            'free_type.required' => '包邮类型不能为空',
            'free_type.numeric' => '包邮类型只能是数字',
            'free_price.required' => '包邮金额/件不能为空',
            'free_price.price' => '包邮金额/件格式错误',
            'first_weight.required' => '首重/件数不能为空',
            'first_weight.numeric' => '首重/件数只能是数字',
            'first_price.required' => '首重/件费用不能为空',
            'first_price.price' => '首重/件费用格式错误',
            'second_weight.required' => '续重/件数不能为空',
            'second_weight.numeric' => '续重/件数只能是数字',
            'second_price.required' => '续重/件费用不能为空',
            'second_price.price' => '续重/件费用格式错误',
            'price_type.required' => '费用类型不能为空',
            'price_type.numeric' => '费用类型只能是数字',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title', 'seller_id', 'type', 'free_type', 'free_price', 'first_weight', 'first_price', 'second_weight', 'second_price', 'price_type', 'open_default', 'content']) as $key => $value) {
            $save_data[$key] = $value;
        }
        if (!isset($save_data['open_default'])) $save_data['open_default'] = Delivery::OPEN_DEFAULT_OFF;
        //组装其他地区
        $group_data = [];
        foreach ($request->only(['group_area_id', 'group_type', 'group_free_type', 'group_free_price', 'group_first_weight', 'group_first_price', 'group_second_weight', 'group_second_price']) as $key => $value) {
            $group_data[$key] = $value;
        }
        $group_area_id = [];
        $group_json = [];
        if (isset($group_data['group_area_id'])) {
            foreach ($group_data['group_area_id'] as $key => $value) {
                if ($value) {
                    $group_area_id[] = array_values($value);
                    $_item = [
                        'type' => $group_data['group_type'][$key],
                        'free_type' => $group_data['group_free_type'][$key],
                        'free_price' => $group_data['group_free_price'][$key],
                        'first_weight' => $group_data['group_first_weight'][$key],
                        'first_price' => $group_data['group_first_price'][$key],
                        'second_weight' => $group_data['group_second_weight'][$key],
                        'second_price' => $group_data['group_second_price'][$key],
                    ];
                    $group_json[] = $_item;
                }
            }
        }
        $save_data['group_area_id'] = json_encode($group_area_id);
        $save_data['group_json'] = json_encode($group_json);
        $save_data['status'] = Delivery::STATUS_ON;

        if ($id) {
            $res = Delivery::query()->where('id', $id)->update($save_data);
        } else {
            $res = Delivery::query()->create($save_data);
        }
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
    }

    /**
     * 修改状态
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function status(Request $request)
    {
        $ids = $this->checkBatchId();
        $status = (int)$request->input('status');
        if (!isset(Delivery::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Delivery::query()->whereIn('id', $ids)->update(['status' => $status]);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.fail'));
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
        $res = Delivery::query()->whereIn('id', $ids)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

}
