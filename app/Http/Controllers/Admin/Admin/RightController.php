<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/11
 * Time: 3:55 PM
 */

namespace App\Http\Controllers\Admin\Admin;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Admin\AdminRight;
use App\Models\System\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class RightController extends BaseController
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
        $menu_child = (int)$request->input('menu_child');
        if ($title) $where[] = ['title', 'like', '%' . $title . '%'];
        if ($menu_child) $where[] = ['menu_child', $menu_child];
        $query = AdminRight::query()->select('id', 'title', 'menu_top', 'menu_child', 'status', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $menu_ids = [];
        foreach ($res_list as $value) {
            $menu_ids[] = $value['menu_top'];
            $menu_ids[] = $value['menu_child'];
        }
        if ($menu_ids) {
            $menu = Menu::query()->whereIn('id', array_unique($menu_ids))->pluck('title', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['menu_top_name'] = $menu[$value['menu_top']] ?? '';
            $_item['menu_child_name'] = $menu[$value['menu_child']] ?? '';
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
        $data = AdminRight::query()->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        $data['button'] = $data['button'] ? array_to_br_textarea(json_decode($data['button'], true)) : '';
        $data['right'] = $data['right'] ? array_to_br_textarea(json_decode($data['right'], true)) : '';
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
            'menu_top' => 'required|numeric',
            'menu_child' => 'required|numeric',
            'right' => 'required'
        ], [
            'title.required' => '标题不能为空',
            'menu_top.required' => '菜单栏目不能为空',
            'menu_top.numeric' => '菜单栏目不能为空',
            'menu_child.required' => '菜单栏目不能为空',
            'menu_child.numeric' => '菜单栏目不能为空',
            'right.required' => '权限码不能为空'
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title', 'menu_top', 'menu_child', 'button', 'right']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $button = $request->input('button');
        if ($button) {
            $buttons = textarea_br_to_array($button);
            $save_data['button'] = json_encode($buttons, JSON_UNESCAPED_UNICODE);
        }
        $right = $request->input('right');
        if ($right) {
            $rights = textarea_br_to_array($right);
            $save_data['right'] = json_encode($rights, JSON_UNESCAPED_UNICODE);
        }
        if ($id) {
            $res = AdminRight::query()->where('id', $id)->update($save_data);
        } else {
            $res = AdminRight::query()->create($save_data);
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
        if (!isset(AdminRight::STATUS_DESC[$status])) {
            api_error(__('admin.missing_params'));
        }
        $res = AdminRight::query()->whereIn('id', $ids)->update(['status' => $status]);
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
        $res = AdminRight::query()->whereIn('id', $ids)->delete();
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.del_error'));
        }
    }

    /**
     * 权限列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function rights(Request $request)
    {
        $right_list = [];
        //获取权限列表
        $rights = AdminRight::query()->where('status', AdminRight::STATUS_ON)->get();
        if ($rights) {
            $menu_ids = $role_right = [];
            foreach ($rights as $right) {
                $menu_ids[] = $right['menu_top'];
                $menu_ids[] = $right['menu_child'];
                $role_right[$right['menu_top']][$right['menu_child']][] = $right;
            }
            //菜单名称
            $menus = [];
            if ($menu_ids) {
                $menu_res = Menu::query()->whereIn('id', array_unique($menu_ids))->get();
                if (!$menu_res->isEmpty()) {
                    $menus = array_column($menu_res->toArray(), 'title', 'id');
                }
            }
            foreach ($role_right as $key => $value) {
                $_item['name'] = $menus[$key] ?? '';
                $_item['id'] = $key;
                $child = [];
                foreach ($value as $k => $v) {
                    $_item_child['name'] = $menus[$k] ?? '';
                    $_item_child['id'] = $k;
                    $_item_child['right'] = $v;
                    $child[] = $_item_child;
                }
                $_item['right'] = $child;
                $right_list[] = $_item;
            }
        }
        if ($right_list) {
            return $this->success($right_list);
        } else {
            api_error(__('admin.content_is_empty'));
        }
    }

    /**
     * 获取后台所有路由
     * @return \Illuminate\Http\JsonResponse
     */
    public function routes()
    {
        $routes = Route::getRoutes();
        $url_arr = [];
        foreach ($routes as $route) {
            if (str_contains($route->uri, 'admin/')) {
                $prefix = explode('/', $route->uri);
                if (isset($prefix[2])) {
                    $key = $prefix[1] . '/' . $prefix[2];
                } else {
                    $key = 'public';
                }
                $url_arr[$key][] = $route->uri;
            }
        }
        foreach ($url_arr as $key => $val) {
            if (count($val) < 1) {
                unset($url_arr[$key]);
            }
        }
        return $this->success($url_arr);
    }
}
