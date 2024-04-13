<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/7
 * Time: 4:12 PM
 */

namespace App\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends BaseController
{

    /**
     * 配置
     * @return JsonResponse
     */
    public function apiConfig(Request $request)
    {
        $custom_config = get_custom_config_all();
        $return = [
            'is_audit' => $custom_config['is_audit'],//小程序审核模式
        ];
        return $this->success($return);
    }
}
