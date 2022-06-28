<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/7
 * Time: 4:12 PM
 */

namespace App\Http\Controllers\Admin;

use App\Models\Admin\AdminRole;
use App\Models\System\Menu;

class IndexController extends BaseController
{

    /**
     * 后台右侧首页
     * @return \Illuminate\Http\JsonResponse
     */
    public function main()
    {
        $domain = $_SERVER["HTTP_HOST"];
        $auth = curl('http://www.shop168.com.cn/wp-content/auth_site.php?domain=' . $domain);
        $auth = json_decode($auth, true);
        $data = [
            [
                'name' => '网站域名',
                'value' => $_SERVER["HTTP_HOST"]
            ],
            [
                'name' => '系统时间',
                'value' => get_date()
            ],
            [
                'name' => '文件上传限制',
                'value' => ini_get("file_uploads") ? ini_get("upload_max_filesize") : "Disabled"
            ],
            [
                'name' => '授权信息',
                'value' => $auth['buy'] ?? '授权信息异常'
            ],
            [
                'name' => '关于版权',
                'value' => $auth['copyright'] ?? '授权信息异常'
            ],
        ];
        return $this->success($data);
    }

    /**
     * 获取管理菜单
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function leftMenu()
    {
        $user_data = $this->getUserInfo();
        $menus = Menu::getMenu();
        //读取菜单权限
        if ($user_data['role_id'] == 1) {
            $admin_menu = $menus;
        } else {
            $_menus = [];
            $role_right = AdminRole::adminRight($user_data['role_id']);//权限
            foreach ($menus as $menu_top) {
                if (in_array($menu_top['id'], $role_right['menu_top'])) {
                    $_menu_child = [];
                    if (isset($menu_top['children'])) {
                        foreach ($menu_top['children'] as $menu_child) {
                            if (in_array($menu_child['id'], $role_right['menu_child'])) {
                                $_menu_child[] = $menu_child;
                            }
                        }
                        $menu_top['children'] = $_menu_child;
                    }
                    $_menus[] = $menu_top;
                }
            }
            $admin_menu = $_menus;
        }
        return $this->success($admin_menu);
    }
}
