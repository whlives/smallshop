<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\Market;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Market\Promotion;
use App\Models\Seller\Seller;
use Illuminate\Http\Request;
use Validator;

class PromotionController extends BaseController
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
        $query = Promotion::query()->select('id', 'title', 'use_price', 'type', 'rule_type', 'seller_id', 'start_at', 'end_at', 'status', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $seller_ids = array_column($res_list->toArray(), 'seller_id');
        if ($seller_ids) {
            $seller_data = Seller::query()->whereIn('id', array_unique($seller_ids))->pluck('title', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['rule_type'] = Promotion::RULE_TYPE_DESC[$value['rule_type']];
            $_item['username'] = $seller_data[$value['seller_id']] ?? '';
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
        $data = Promotion::query()->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        $data['user_group'] = explode(',', $data['user_group']);
        if ($data['type'] == Promotion::AMOUNT_TYPE_COUPONS || $data['type'] == Promotion::REG_TYPE_COUPONS) {
            $data['coupons_id'] = $data['type_value'];
            $data['type_value'] = '';
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
            'title' => 'required',
            'seller_id' => 'required|numeric',
            'rule_type' => 'required|numeric',
            'use_price' => 'nullable|price',
            'start_at' => 'required|date_format:"Y-m-d H:i:s"',
            'end_at' => 'required|date_format:"Y-m-d H:i:s"',
            'user_group' => 'required',
            'type' => 'required|numeric',
        ], [
            'title.required' => '标题不能为空',
            'seller_id.required' => '商家不能为空',
            'seller_id.numeric' => '商家只能是数字',
            'rule_type.required' => '规则类型不能为空',
            'rule_type.numeric' => '规则只能是数字',
            'use_price.price' => '起用金额格式错误',
            'start_at.required' => '开始时间不能为空',
            'start_at.date_format' => '开始时间格式错误',
            'end_at.required' => '结束时间不能为空',
            'end_at.date_format' => '结束时间格式错误',
            'user_group.required' => '用户组不能为空',
            'type.required' => '类型不能为空',
            'type.numeric' => '类型只能是数字',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $use_price = $request->input('use_price');
        $type = (int)$request->input('type');
        $type_value = (int)$request->input('type_value');
        $coupons_id = (int)$request->input('coupons_id');
        switch ($type) {
            case Promotion::AMOUNT_TYPE_REDUCTION:
                if (!is_numeric($type_value) || $type_value <= 0 || $type_value > $use_price) {
                    api_error('满减金额只能在0到' . $use_price . '之间');
                }
                break;
            case Promotion::AMOUNT_TYPE_DISCOUNT:
                if (!$type_value || $type_value <= 0 || $type_value > 100) {
                    api_error(__('admin.promotion_pct_error'));
                }
                break;
            case Promotion::AMOUNT_TYPE_POINT:
            case Promotion::REG_TYPE_POINT:
                if (!is_numeric($type_value) || $type_value <= 0) {
                    api_error(__('admin.promotion_point_error'));
                }
                break;
            case Promotion::AMOUNT_TYPE_COUPONS:
            case Promotion::REG_TYPE_COUPONS:
                if (!$coupons_id) {
                    api_error(__('admin.promotion_coupons_id_error'));
                }
                $type_value = $coupons_id;
                break;
        }
        $save_data = [];
        foreach ($request->only(['title', 'rule_type', 'use_price', 'seller_id', 'start_at', 'end_at', 'type', 'content']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $save_data['type_value'] = $type_value;
        $save_data['user_group'] = implode(',', $request->input('user_group'));
        if ($id) {
            $res = Promotion::query()->where('id', $id)->update($save_data);
        } else {
            $res = Promotion::query()->create($save_data);
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
        if (!isset(Promotion::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Promotion::query()->whereIn('id', $ids)->update(['status' => $status]);
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
        $res = Promotion::query()->whereIn('id', $ids)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 获取类型列表
     * @param Request $request
     * @return array
     * @throws \App\Exceptions\ApiException
     */
    public function getType(Request $request)
    {
        $rule_type = (int)$request->input('rule_type');
        $type = [];
        if ($rule_type == Promotion::RULE_TYPE_AMOUNT) {
            $type = Promotion::AMOUNT_TYPE_DESC;
        } elseif ($rule_type == Promotion::RULE_TYPE_REG) {
            $type = Promotion::REG_TYPE_DESC;
        }
        return $this->success($type);
    }

}
