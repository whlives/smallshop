<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/20
 * Time: 2:06 PM
 */

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\BaseController;
use App\Models\System\Menu;
use Illuminate\Http\Request;
use Validator;

class MenuController extends BaseController
{
    /**
     * 菜单管理
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $menu = Menu::getAll();
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
        $data = Menu::find($id);
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
            'position' => 'required|numeric|max:9999',
            'url' => 'required',
        ], [
            'title.required' => '菜单名称不能为空',
            'parent_id.numeric' => '上级只能是数字',
            'position.required' => '排序不能为空',
            'position.numeric' => '排序只能是数字',
            'position.max' => '排序数字不能超过9999',
            'url.required' => '链接地址不能为空'
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title', 'icon', 'parent_id', 'position', 'url', 'parameter']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $id = (int)$request->input('id');
        if ($id) {
            $res = Menu::where('id', $id)->update($save_data);
        } else {
            $res = Menu::create($save_data);
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
        if (!isset(Menu::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = Menu::whereIn('id', $ids)->update(['status' => $status]);
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
        $sub_menu = Menu::where('parent_id', $id)->count();
        if ($sub_menu > 0) {
            api_error(__('admin.menu_child_no_empty'));
        }
        $res = Menu::where('id', $id)->delete();
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
        $data = Menu::getSelect(0, true);
        return $this->success($data);
    }

    /**
     * 获取下拉列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function select(Request $request)
    {
        $parent_id = $request->input('parent_id', 0);
        $data = Menu::getSelect($parent_id);
        return $this->success($data);
    }
}
