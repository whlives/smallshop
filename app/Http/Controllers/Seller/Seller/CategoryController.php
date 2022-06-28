<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 3:31 PM
 */

namespace App\Http\Controllers\Seller\Seller;

use App\Http\Controllers\Seller\BaseController;
use App\Models\Seller\SellerCategory;
use Illuminate\Http\Request;
use Validator;

class CategoryController extends BaseController
{
    public int $seller_id;

    public function __construct()
    {
        $this->seller_id = $this->getUserId();
    }

    /**
     * 分类管理
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $menu = SellerCategory::getAll($this->seller_id);
        return $this->success($menu);
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
        $data = SellerCategory::where('seller_id', $this->seller_id)->find($id);
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
        //验证规则
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'parent_id' => 'numeric',
            'position' => 'required|numeric',
        ], [
            'title.required' => '分类名称不能为空',
            'parent_id.numeric' => '上级只能是数字',
            'position.required' => '排序不能为空',
            'position.numeric' => '排序只能是数字'
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title', 'image', 'parent_id', 'position']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $id = (int)$request->input('id');
        if ($id) {
            $res = SellerCategory::where(['id' => $id, 'seller_id' => $this->seller_id])->update($save_data);
        } else {
            $save_data['seller_id'] = $this->seller_id;
            $res = SellerCategory::create($save_data);
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
        if (!isset(SellerCategory::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = SellerCategory::whereIn('id', $ids)->where('seller_id', $this->seller_id)->update(['status' => $status]);
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
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.invalid_params'));
        }
        //查询是否存在下级分类
        $sub_menu = SellerCategory::where('parent_id', $id)->count();
        if ($sub_menu > 0) {
            api_error(__('admin.category_child_no_empty'));
        }
        $res = SellerCategory::where(['id' => $id, 'seller_id' => $this->seller_id])->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 获取包含下级的下拉列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectAll(Request $request)
    {
        $data = SellerCategory::getSelect($this->seller_id, 0, true);
        return $this->success($data);
    }
}