<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/7
 * Time: 4:12 PM
 */

namespace App\Http\Controllers\Seller;

use App\Models\System\MenuSeller;

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
        $menus = MenuSeller::getMenu();
        //以后可以在这里加权限
        $admin_menu = $menus;
        return $this->success($admin_menu);
    }
}
