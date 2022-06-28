<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/8
 * Time: 2:41 PM
 */

namespace App\Models\System;

use App\Models\BaseModel;

/**
 * 系统设置
 */
class Config extends BaseModel
{
    protected $table = 'config';
    protected $guarded = ['id'];
}
