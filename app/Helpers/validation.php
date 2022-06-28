<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/8
 * Time: 4:03 PM
 */

if (!function_exists('check_mobile')) {
    /**
     * 验证手机号码格式
     * @param string $mobile
     * @return bool
     */
    function check_mobile(string $mobile): bool
    {
        if (!is_numeric($mobile)) {
            return false;
        }
        if (preg_match("/^1[3456789]{1}\d{9}$/", $mobile)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('check_price')) {
    /**
     * 验证价格格式
     * @param $price
     * @return bool
     */
    function check_price($price): bool
    {
        if (preg_match("/(^[-]?[1-9]([0-9]+)?(\.[0-9]{1,2})?$)|(^(0){1}$)|(^[-]?[0-9]\.[0-9]([0-9])?$)/", $price)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('check_date_time')) {
    /**
     * 验证日期时间格式
     * @param $date_time
     * @return bool
     */
    function check_date_time($date_time): bool
    {
        if (preg_match("/^[1-9]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\s+(20|21|22|23|[0-1]\d):[0-5]\d:[0-5]\d$/", $date_time)) {
            return true;
        }
        return false;
    }
}
