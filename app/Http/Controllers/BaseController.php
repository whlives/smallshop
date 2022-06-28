<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 8:54 PM
 */

namespace App\Http\Controllers;

use App\Services\TokenService;

class BaseController extends Controller
{

    /**
     * 返回空对象
     * @return \Illuminate\Http\JsonResponse
     */
    protected function emptyObjectContent()
    {
        return self::success(new \stdClass());
    }

    /**
     * 格式化返回参数
     * @param mixed $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success(mixed $data = '')
    {
        $return = [
            'code' => '0',
            'status_code' => '200',
            'msg' => '',
            'data' => true
        ];
        $return['data'] = $this->formatResponse($data);
        return response()->json($return, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 转换返回数据
     * @param mixed $data
     * @return mixed
     */
    protected function formatResponse(mixed $data)
    {
        if (is_object($data) && count((array)$data)) {
            $data = $data->toArray();
        }
        if (is_array($data) && !empty($data)) {
            foreach ($data as $key => $val) {
                unset($data[$key]);
                if (is_array($val) || is_object($val)) {
                    $data[$key] = $this->formatResponse($val);
                } elseif (!isset($val)) {
                    $data[$key] = '';
                } else {
                    $data[$key] = (string)$val;//全部返回字符串
                }
            }
        }
        return $data;
    }
}
