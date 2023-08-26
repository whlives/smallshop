<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/7
 * Time: 4:21 PM
 */

use App\Exceptions\ApiError;

if (!function_exists('api_error')) {
    /**
     * 接口错误返回
     * @param string $error_info 错误信息
     * @return mixed
     * @throws ApiError
     */
    function api_error(string $error_info = ''): mixed
    {
        if (!$error_info) {
            $error_info = '无效的请求';
        }
        throw new ApiError($error_info);
    }
}

if (!function_exists('get_date')) {
    /**
     * 获取格式化的时间（默认当前时间）
     * @param string $time 时间戳
     * @return string
     */
    function get_date(string $time = ''): string
    {
        if (!$time) {
            $time = time();
        }
        return date('Y-m-d H:i:s', $time);
    }
}

if (!function_exists('get_custom_config_all')) {
    /**
     * 获取所有自定义配置信息
     * @param bool $del 是否删除缓存
     * @return array|false
     */
    function get_custom_config_all(bool $del = false): array|false
    {
        if (!config('app.is_slb')) {
            //单机部署
            return config('custom');
        } else {
            //分布式环境下
            $cache_key = 'custom_config:' . config('app.key');
            if ($del) {
                \Illuminate\Support\Facades\Cache::forget($cache_key);
                return false;
            }
            $custom_config = \Illuminate\Support\Facades\Cache::get($cache_key);
            if (!$custom_config) {
                $custom_config = \App\Models\System\Config::query()->select('key_name', 'value')->pluck('value', 'key_name')->toArray();
                \Illuminate\Support\Facades\Cache::put($cache_key, $custom_config, 3600 * 24 * 20);//注意memcache最大缓存时间是30天
            }
        }
        return $custom_config;
    }
}

if (!function_exists('get_custom_config')) {
    /**
     * 获取后台自定义配置信息
     * @param string $key_name 配置key
     * @return mixed
     */
    function get_custom_config(string $key_name = ''): mixed
    {
        if (!config('app.is_slb')) {
            //单机部署
            return config('custom.' . $key_name);
        } else {
            $custom_config = get_custom_config_all();
            if ($key_name && isset($custom_config[$key_name])) {
                return $custom_config[$key_name];
            }
        }
        return '';
    }
}

if (!function_exists('get_page_params')) {
    /**
     * 获取分页信息
     * @return array
     */
    function get_page_params(): array
    {
        $page = (int)request()->page;
        $limit = (int)request()->limit;
        if (!$page) $page = 1;
        if (!$limit) $limit = 20;
        if ($limit > 100) $limit = 100;
        $offset = $limit * ($page - 1);
        return [$limit, $offset, $page];
    }
}

if (!function_exists('get_time_range')) {
    /**
     * 获取时间范围
     * @param string|null $time_range
     * @return array
     * @throws ApiError
     */
    function get_time_range(string|null $time_range = ''): array
    {
        if (!$time_range) $time_range = request()->input('time_range');
        if (!$time_range) return ['', ''];
        $time_range = explode(' ~ ', $time_range);
        $start_at = $time_range[0] . ' 00:00:00';
        $end_at = $time_range[1] . ' 23:59:59';
        return [$start_at, $end_at];
    }
}

if (!function_exists('format_number')) {
    /**
     * 格式化用逗号分隔的数字检测是否数字
     * @param array|string|int|null $number 要分隔的字符串
     * @param bool $must_array 只有一个值的时候是否格式化为数组
     * @return array|int
     */
    function format_number(array|string|int|null $number, bool $must_array = false): array|int
    {
        if (!is_array($number)) {
            $number = str_replace('，', ',', $number);
            if (str_contains($number, ',') || $must_array) {
                $number = explode(',', $number);
            }
        }
        if (is_array($number)) {
            $numbers = [];
            foreach ($number as $val) {
                $_number = (int)$val;
                if ($_number) {
                    $numbers[] = $_number;
                }
            }
            $numbers = array_unique($numbers);
        } else {
            $numbers = (int)$number;
            if ($must_array) $numbers[] = (int)$numbers;
        }
        return $numbers;
    }
}

if (!function_exists('format_price')) {
    /**
     * 格式化价格
     * @param float $price 需要格式化的价格
     * @param int $num 保留位数
     * @param bool $is_rounded 是否四舍五入
     * @return float
     */
    function format_price(float $price, int $num = 2, bool $is_rounded = true): float
    {
        if (!$is_rounded) {
            $is_abs = 0;//如果是负数需要先转正数计算完了在转为负数
            if ($price < 0) {
                $is_abs = 1;
                $price = abs($price);
            }
            $divisor = pow(10, $num);
            $return_price = floor(strval($price * $divisor)) / $divisor;
            if ($is_abs == 1) {
                $return_price = -$return_price;
            }
        } else {
            $bd_price = round($price, $num);
            $return_price = sprintf("%." . $num . "f", $bd_price);
        }
        $return_price = floatval($return_price);
        if (empty($return_price)) {
            $return_price = '0';
        }
        return $return_price;
    }
}

if (!function_exists('textarea_br_to_array')) {
    /**
     * 多行文本换行转换到数组
     * @param string $string 需要转换的文本
     * @return array
     */
    function textarea_br_to_array(string $string): array
    {
        if (!$string) return [];
        $data = explode(chr(10), $string);
        $return = [];
        foreach ($data as $val) {
            $_item = str_replace(chr(13), '', $val);
            if ($_item && !in_array($_item, $return)) {
                $return[] = $_item;
            }
        }
        return $return;
    }
}

if (!function_exists('array_to_br_textarea')) {
    /**
     * 数组转换到多行文本换行
     * @param array|string|null $data 需要转换的数组
     * @param string $glue 分隔符
     * @return string
     */
    function array_to_br_textarea(array|string $data, string $glue = ','): string
    {
        if (!$data) return '';
        if (is_array($data)) $data = join(',', $data);
        return str_replace($glue, chr(10), $data);
    }
}

if (!function_exists('get_cache_key')) {
    /**
     * 组装缓存key
     * @param string $name 名称
     * @param array|string $where 附加条件
     * @return string
     */
    function get_cache_key(string $name, array|string $where = ''): string
    {
        if (!$where) return $name;
        if (is_array($where)) {
            $key_where = join('_', $where);
        } else {
            $key_where = $where;
        }
        return $name . ':' . $key_where;
    }
}
if (!function_exists('empty_object')) {
    /**
     * 返回一个空对象
     * @return stdClass
     */
    function empty_object()
    {
        return new \stdClass();
    }
}

if (!function_exists('resize_images')) {
    /**
     * 等比缩放图片，限定在矩形框内
     * @param string|null $image_url 图片地址
     * @param int $w 宽度
     * @param int $h 高度
     * @return string|null
     */
    function resize_images(string|null $image_url, int $w = 0, int $h = 0): string|null
    {
        if (!$image_url) return $image_url;
        if (!str_starts_with($image_url, 'http')) {
            return $image_url;
        } else {
            return $w ? $image_url . '?x-oss-process=image/resize,m_lfit,' . 'h_' . $h . ',w_' . $w : $image_url;
        }
    }
}

if (!function_exists('remove_xss')) {
    /**
     * xss过滤函数
     * @param string|null $string $string
     * @return string
     */
    function remove_xss(string|null $string = ''): string
    {
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $string);
        $parm1 = ['javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base'];
        $parm2 = ['onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload'];
        $parm = array_merge($parm1, $parm2);
        for ($i = 0; $i < sizeof($parm); $i++) {
            $pattern = '/';
            for ($j = 0; $j < strlen($parm[$i]); $j++) {
                if ($j > 0) {
                    $pattern .= '(';
                    $pattern .= '(&#[x|X]0([9][a][b]);?)?';
                    $pattern .= '|(&#0([9][10][13]);?)?';
                    $pattern .= ')?';
                }
                $pattern .= $parm[$i][$j];
            }
            $pattern .= '/i';
            $string = preg_replace($pattern, '', $string);
        }
        return $string;
    }
}

if (!function_exists('curl')) {
    /**
     * curl请求
     * @param string $url 网址
     * @param array $params 请求参数
     * @param bool $is_post 请求方式get、post
     * @param bool $https 是否https协议
     * @param array $header header头信息
     * @return bool|string
     */
    function curl(string $url, array $params = [], bool $is_post = false, bool $https = false, array $header = []): bool|string
    {
        $http_info = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.118 Safari/537.36');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($https) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//从证书中检查SSL加密算法是否存在
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if ($is_post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                if (is_array($params)) {
                    $params = http_build_query($params);
                }
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);
        if ($response === false) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $http_info = array_merge($http_info, curl_getinfo($ch));
        curl_close($ch);
        return $response;
    }
}
if (!function_exists('get_sql_debug')) {
    /**
     * sql debug输出
     * @return void
     */
    function get_sql_debug()
    {
        \DB::listen(function ($sql) {
            dump($sql);
            $singleSql = $sql->sql;
            if ($sql->bindings) {
                foreach ($sql->bindings as $replace) {
                    $value = is_numeric($replace) ? $replace : "'" . $replace . "'";
                    $singleSql = preg_replace('/\?/', $value, $singleSql, 1);
                }
                dump($singleSql);
            } else {
                dump($singleSql);
            }
        });
    }
}
