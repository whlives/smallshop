<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/8
 * Time: 4:06 PM
 */

if (!function_exists('get_device')) {
    /**
     * 获取设备号
     * @return string
     */
    function get_device(): string
    {
        $device = request()->cookie('device');
        if (!$device) {
            $device = request()->input('device');
            if (!$device) {
                $device = request()->header('device');
            }
        }
        return $device;
    }
}

if (!function_exists('get_platform')) {
    /**
     * 获取平台类型
     * @return string
     */
    function get_platform(): string
    {
        //类型同步\App\Models\System\Payment CLIENT_TYPE
        $platform = request()->cookie('platform');
        if (!$platform) {
            $platform = request()->input('platform');
            if (!$platform) {
                $platform = request()->header('platform');
            }
        }
        return strtolower($platform);
    }
}

if (!function_exists('get_mobile_model')) {
    /**
     * 获取手机型号
     * @return string
     */
    function get_mobile_model(): string
    {
        $mobile_model = request()->cookie('mobile_model');
        if (!$mobile_model) {
            $mobile_model = request()->input('mobile_model');
            if (!$mobile_model) {
                $mobile_model = request()->header('mobile_model');
            }
        }
        return strtolower($mobile_model);
    }
}

if (!function_exists('get_version')) {
    /**
     * 获取app版本
     * @return string
     */
    function get_version(): string
    {
        $version = request()->cookie('version');
        if (!$version) {
            $version = request()->input('version');
            if (!$version) {
                $version = request()->header('version');
            }
        }
        return strtolower($version);
    }
}

if (!function_exists('get_system')) {
    /**
     * 获取手机系统版本
     * @return string
     */
    function get_system(): string
    {
        $system = request()->cookie('system');
        if (!$system) {
            $system = request()->input('system');
            if (!$system) {
                $system = request()->header('system');
            }
        }
        return strtolower($system);
    }
}

if (!function_exists('get_api_key')) {
    /**
     * 获取apikey
     * @return string
     */
    function get_api_key(): string
    {
        $api_key = '';
        $platform = get_platform();
        if (in_array($platform, [\App\Models\System\Payment::CLIENT_TYPE_IOS, \App\Models\System\Payment::CLIENT_TYPE_ANDROID])) {
            $api_key = get_custom_config('api_key');
        } elseif (in_array($platform, [\App\Models\System\Payment::CLIENT_TYPE_WEB, \App\Models\System\Payment::CLIENT_TYPE_H5])) {
            $api_key = get_custom_config('api_key');
        } elseif ($platform == \App\Models\System\Payment::CLIENT_TYPE_WECHAT) {
            $api_key = get_custom_config('api_key');
        } elseif ($platform == \App\Models\System\Payment::CLIENT_TYPE_MP) {
            $api_key = get_custom_config('api_key');
        }
        return $api_key;
    }
}

if (!function_exists('get_user_group')) {
    /**
     * 获取用户用户组
     * @return array
     */
    function get_user_group(): array
    {
        $return = [
            'group_id' => 0,
            'pct' => '',
            'title' => ''
        ];
        $token_service = new \App\Services\TokenService();
        $token = $token_service->getToken();
        $m_id = (isset($token['id']) && $token['id']) ?: 0;
        if ($m_id) {
            $group_id = \App\Models\Member\Member::where('id', $m_id)->value('group_id');
            if ($group_id) {
                $group = \App\Models\Member\MemberGroup::where(['id' => $group_id, 'status' => \App\Models\Member\MemberGroup::STATUS_ON])->first();
                if ($group) {
                    $pct = format_price($group['pct'] / 100);
                    if ($pct > 1) $pct = 1;
                    if ($pct <= 0) $pct = '';
                    $return['group_id'] = $group_id;
                    $return['pct'] = $pct;
                    $return['title'] = $group['title'];
                }
            }
        }
        return $return;
    }
}

