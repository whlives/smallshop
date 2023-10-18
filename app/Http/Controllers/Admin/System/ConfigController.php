<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/20
 * Time: 2:06 PM
 */

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Admin\BaseController;
use App\Models\System\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class ConfigController extends BaseController
{
    /**
     * 站点设置
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $config = [];
        $res_config = Config::query()->orderBy('position', 'asc')->orderBy('id', 'asc')->get();
        if (!$res_config->isEmpty()) {
            foreach ($res_config as $val) {
                if (in_array($val['input_type'], ['radio', 'select'])) {
                    $val['select_value'] = explode(',', $val['select_value']);
                }
                $config[$val['tab_name']][] = $val;
            }
        }
        return $this->success($config);
    }

    /**
     * 添加配置
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function save(Request $request)
    {
        //验证规则
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'key_name' => [
                'required',
                Rule::unique('config')->ignore($request->id)
            ],
            'value' => 'required',
            'input_type' => 'required',
            'tab_name' => 'required',
            'position' => 'numeric'
        ], [
            'title.required' => '名称不能为空',
            'key_name.required' => '参数key名称不能为空',
            'key_name.unique' => '参数key名称已经存在',
            'value.required' => '参数值不能为空',
            'input_type.required' => '类型不能为空',
            'tab_name.required' => 'tab名称不能为空',
            'position.numeric' => '排序必须是数字'
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }

        $save_data = [];
        foreach ($request->only(['title', 'key_name', 'value', 'input_type', 'tab_name', 'position']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $select_value = $request->input('select_value');
        if ($select_value) {
            $select_value = textarea_br_to_array($select_value);
            $save_data['select_value'] = join(',', $select_value);
        }
        $res = Config::query()->create($save_data);
        if ($res) {
            self::updateConfig();//更新配置
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
    }

    /**
     * 站点设置修改
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $config = $request->input('config');
        if ($config) {
            foreach ($config as $id => $value) {
                Config::query()->where('id', $id)->update(['value' => $value]);
            }
            self::updateConfig();//更新配置
        }
        return $this->success();
    }

    /**
     * 更新配置
     * @return void
     * @throws \App\Exceptions\ApiError
     */
    private function updateConfig()
    {
        if (!config('app.is_slb')) {
            //单机部署
            $file_path = app_path() . "/../config/custom.php";
            $res_config = Config::query()->select('key_name', 'value')->get();
            if ($res_config->isEmpty()) {
                api_error(__('admin.content_is_empty'));
            }
            $str = "<?php\r\n";
            $str .= "return [\r\n";
            foreach ($res_config as $val) {
                $str .= "'" . $val['key_name'] . "' => " . "'" . $val['value'] . "',\r\n";
            }
            $str .= "];\r\n";
            file_put_contents($file_path, $str);
            if (!config('app.debug')) {
                //非测试环境更新缓存
                Artisan::call('config:cache');
            }
        } else {
            //分布式环境下
            get_custom_config_all(true);
        }
    }
}
