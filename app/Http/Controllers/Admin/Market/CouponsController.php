<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\Market;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Market\Coupons;
use App\Models\Seller\Seller;
use Illuminate\Http\Request;
use Validator;

class CouponsController extends BaseController
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
            $seller_id = Seller::where('title', $seller_title)->value('id');
            if ($seller_id) {
                $where[] = ['seller_id', $seller_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        $query = Coupons::query()->select('id', 'title', 'image', 'type', 'amount', 'use_price', 'seller_id', 'status', 'start_at', 'end_at', 'created_at')
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
            $seller_data = Seller::query()->whereIn('id', array_unique($seller_ids))->pluck('username', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
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
        $data = Coupons::query()->find($id);
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
            'title' => 'required',
            'seller_id' => 'required|numeric',
            'type' => 'required|numeric',
            'is_buy' => 'required|numeric',
            'open' => 'required|numeric',
            'limit' => 'required|numeric',
            'amount' => 'required|price',
            'use_price' => 'required|price',
            'day_num' => 'nullable|numeric',
            'start_at' => 'nullable|date_format:"Y-m-d H:i:s"',
            'end_at' => 'nullable|date_format:"Y-m-d H:i:s"',
        ], [
            'title.required' => '标题不能为空',
            'seller_id.required' => '商家不能为空',
            'seller_id.numeric' => '商家只能是数字',
            'type.required' => '类型不能为空',
            'type.numeric' => '类型只能是数字',
            'is_buy.required' => '可否购买不能为空',
            'is_buy.numeric' => '可否购买只能是数字',
            'open.required' => '开放领取不能为空',
            'open.numeric' => '开放领取只能是数字',
            'limit.required' => '领取张数不能为空',
            'limit.numeric' => '领取张数只能是数字',
            'amount.required' => '优惠值不能为空',
            'amount.price' => '优惠值格式错误',
            'use_price.required' => '起用金额不能为空',
            'use_price.price' => '起用金额格式错误',
            'day_num.numeric' => '有效天数只能是数字',
            'start_at.date_format' => '开始时间格式错误',
            'end_at.date_format' => '结束时间格式错误',

        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $use_price = $request->input('use_price');
        $type = (int)$request->input('type');
        $amount = $request->input('amount');
        switch ($type) {
            case Coupons::TYPE_REDUCTION:
                if (!is_numeric($amount) || $amount < 0 || $amount > $use_price) {
                    api_error('满减金额只能在0到' . $use_price . '之间');
                }
                break;
            case Coupons::TYPE_DISCOUNT:
                if (!$amount || $amount < 0 || $amount > 100) {
                    api_error(__('admin.coupons_pct_error'));
                }
                break;
        }
        $save_data = [];
        foreach ($request->only(['title', 'type', 'is_buy', 'open', 'limit', 'use_price', 'amount', 'seller_id', 'day_num', 'start_at', 'end_at', 'image', 'note']) as $key => $value) {
            $save_data[$key] = $value;
        }
        if (!$save_data['day_num'] && (!$save_data['start_at'] || !$save_data['end_at'])) {
            api_error(__('admin.coupons_at_error'));
        }
        if ((int)$save_data['day_num'] > 0) {
            $save_data['start_at'] = null;
            $save_data['end_at'] = null;
        }
        if ($id) {
            $res = Coupons::query()->where('id', $id)->update($save_data);
        } else {
            $res = Coupons::query()->create($save_data);
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
        if (!isset(Coupons::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Coupons::query()->whereIn('id', $ids)->update(['status' => $status]);
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
        $res = Coupons::query()->whereIn('id', $ids)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 获取列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function select(Request $request)
    {
        $seller_id = (int)$request->input('seller_id');
        if (!$seller_id) {
            api_error(__('admin.missing_params'));
        }
        $where = [
            ['status', Coupons::STATUS_ON],
            ['seller_id', $seller_id],
        ];
        $res_list = Coupons::query()->select('id', 'title')->where($where)
            ->where(function ($query) {
                $query->where([['end_at', '>', get_date()]])->orWhere([['day_num', '>', 0]]);
            })
            ->orderBy('id', 'desc')
            ->get();
        return $this->success($res_list);
    }

}
