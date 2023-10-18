<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/22
 * Time: 2:21 PM
 */

namespace App\Http\Controllers\Admin\Market;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Goods\Category;
use App\Models\Goods\Goods;
use App\Models\Market\CouponsRule;
use App\Models\System\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponsRuleController extends BaseController
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
        $coupons_id = (int)$request->input('coupons_id');
        $type = (int)$request->input('type');
        $in_type = (int)$request->input('in_type');
        if (!$coupons_id) {
            api_error(__('admin.content_is_empty'));
        }
        $where[] = ['coupons_id', $coupons_id];
        if ($type) $where[] = ['type', $type];
        if ($in_type) $where[] = ['in_type', $in_type];
        $query = CouponsRule::query()->select('id', 'type', 'in_type', 'obj_id')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $goods_ids = $brand_ids = $category_ids = [];
        foreach ($res_list as $value) {
            switch ($value['type']) {
                case CouponsRule::TYPE_GOODS:
                    $goods_ids[] = $value['obj_id'];
                    break;
                case CouponsRule::TYPE_BRAND:
                    $brand_ids[] = $value['obj_id'];
                    break;
                case CouponsRule::TYPE_CATEGORY:
                    $category_ids[] = $value['obj_id'];
                    break;
            }
        }
        $goods = Goods::query()->whereIn('id', $goods_ids)->pluck('title', 'id');
        $brand = Brand::query()->whereIn('id', $brand_ids)->pluck('title', 'id');
        $category = Category::query()->whereIn('id', $category_ids)->pluck('title', 'id');
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            switch ($value['type']) {
                case CouponsRule::TYPE_GOODS:
                    $_item['title'] = $goods[$value['obj_id']] ?? '';
                    break;
                case CouponsRule::TYPE_BRAND:
                    $_item['title'] = $brand[$value['obj_id']] ?? '';
                    break;
                case CouponsRule::TYPE_CATEGORY:
                    $_item['title'] = $category[$value['obj_id']] ?? '';
                    break;
            }
            $_item['type'] = CouponsRule::TYPE_DESC[$value['type']];
            $_item['in_type'] = CouponsRule::IN_TYPE_DESC[$value['in_type']];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 添加编辑
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function save(Request $request)
    {
        //验证规则
        $validator = Validator::make($request->all(), [
            'coupons_id' => 'required|numeric',
            'type' => 'required|numeric',
            'in_type' => 'required|numeric',
            'obj_id' => 'required',
        ], [
            'coupons_id.required' => '优惠券不能为空',
            'coupons_id.numeric' => '优惠券只能是数字',
            'type.required' => '类型不能为空',
            'type.numeric' => '类型只能是数字',
            'in_type.required' => '条件不能为空',
            'in_type.numeric' => '条件只能是数字',
            'obj_id.required' => '对象id不能为空',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $coupons_id = (int)$request->input('coupons_id');
        $type = (int)$request->input('type');
        $in_type = (int)$request->input('in_type');
        $obj_id = format_number($request->input('obj_id'), true);
        $insert_data = [];
        foreach ($obj_id as $value) {
            $insert_data[] = [
                'coupons_id' => $coupons_id,
                'type' => $type,
                'in_type' => $in_type,
                'obj_id' => $value,
                'created_at' => get_date(),
                'updated_at' => get_date()
            ];
        }
        if ($insert_data) {
            CouponsRule::query()->whereIn('obj_id', $obj_id)->where(['coupons_id' => $coupons_id, 'type' => $type, 'in_type' => $in_type])->delete();
            $res = CouponsRule::query()->insert($insert_data);
        } else {
            api_error(__('admin.invalid_params'));
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
        $res = CouponsRule::query()->whereIn('id', $ids)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 类型
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function type(Request $request)
    {
        return $this->success(CouponsRule::TYPE_DESC);
    }

    /**
     * 条件类型
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function inType(Request $request)
    {
        return $this->success(CouponsRule::IN_TYPE_DESC);
    }

    /**
     * 搜索
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function search(Request $request)
    {
        $type = (int)$request->input('type');
        $seller_id = (int)$request->input('seller_id');
        $keyword = $request->input('keyword');
        if (!$type || !$seller_id || !$keyword) {
            api_error(__('admin.invalid_params'));
        }
        switch ($type) {
            case CouponsRule::TYPE_GOODS:
                $data_list = Goods::query()->select('id as value', 'title as name')->where([['seller_id', $seller_id], ['title', 'like', '%' . $keyword . '%']])->get();
                break;
            case CouponsRule::TYPE_BRAND:
                $data_list = Brand::query()->select('id as value', 'title as name')->where([['title', 'like', '%' . $keyword . '%']])->get();
                break;
            case CouponsRule::TYPE_CATEGORY:
                $data_list = Category::query()->select('id as value', 'title as name')->where([['title', 'like', '%' . $keyword . '%']])->get();
                break;
            default:
                $data_list = [];
                break;
        }
        if ($data_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        return $this->success($data_list);
    }

}
