<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/8
 * Time: 2:51 PM
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 公共model
 */
class BaseModel extends Model
{
    /**
     * 为数组/ JSON序列化准备一个日期。
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
