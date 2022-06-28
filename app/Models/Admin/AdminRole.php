<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 9:45 PM
 */

namespace App\Models\Admin;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Cache;
use function config;
use function get_cache_key;

/**
 * 后台用户角色
 */
class AdminRole extends BaseModel
{
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    protected $table = 'admin_role';
    protected $guarded = ['id'];

    /**
     * 获取用户组名称
     * @param int|array $id
     * @return string
     */
    static function getRoleTitle(int|array $id, $string = false): string|array
    {
        if (!is_array($id)) {
            $id = [$id];
        }
        $title = self::whereIn('id', $id)->pluck('title')->toArray();
        if ($string) {
            return is_array($title) ? join(',', $title) : '';
        }
        return $title;
    }

    /**
     * 获取用户角色的具体权限
     * @param string $role_id 角色id
     * @param bool $is_refresh 是否刷新
     * @return array
     */
    public static function adminRight(string $role_id, bool $is_refresh = false): array
    {
        $role_id = explode(',', $role_id);
        if (!$role_id) return false;
        $cache_key = get_cache_key('admin_role', $role_id);
        $return = Cache::get($cache_key);
        if (!$return || $is_refresh) {
            $return = [
                'menu_top' => [],//一级菜单
                'menu_child' => [],//二级菜单
                'right' => [],//权限码
                'button' => []//按钮
            ];
            $res_list = self::select('right')->whereIn('id', $role_id)->get();
            if ($res_list->isEmpty()) {
                return $return;
            }
            $right_ids = $menu_top = $menu_child = [];
            //组装菜单id和权限码id
            foreach ($res_list as $value) {
                $role_right = json_decode($value['right'], true);
                foreach ($role_right as $top_key => $top_menu) {
                    $menu_top[] = $top_key;
                    foreach ($top_menu as $child_key => $child_menu) {
                        $menu_child[] = $child_key;
                        $right_ids = array_merge($child_menu, $right_ids);
                    }
                }
            }
            $right = $button = [];
            //查询具体的权限和按钮
            if ($right_ids) {
                $res_right = AdminRight::select('right', 'button')->where('status', AdminRight::STATUS_ON)->whereIn('id', $right_ids)->get();
                if (!$res_right->isEmpty()) {
                    foreach ($res_right as $val) {
                        $_right = json_decode($val['right'], true);
                        if ($_right) $right = array_merge($_right, $right);
                        $_button = json_decode($val['button'], true);
                        if ($_button) {
                            foreach ($_button as $_button_detail) {
                                if ($_button_detail) {
                                    $_button_detail = explode('/', $_button_detail);
                                    $button[$_button_detail[0] . '/' . $_button_detail[1]][] = $_button_detail[2];
                                }
                            }
                        }
                    }
                    //对按钮去重
                    if ($button) {
                        foreach ($button as $k => $v) {
                            $button[$k] = array_unique($v);
                        }
                    }
                }
            }
            $return['menu_top'] = array_unique($menu_top);
            $return['menu_child'] = array_unique($menu_child);
            $return['right'] = array_unique($right);
            $return['button'] = $button;
            Cache::put($cache_key, $return, get_custom_config('cache_time'));
        }
        return $return;
    }
}
