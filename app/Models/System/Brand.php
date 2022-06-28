<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 2:14 PM
 */

namespace App\Models\System;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 品牌
 */
class Brand extends BaseModel
{
    use SoftDeletes;

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    protected $table = 'brand';
    protected $guarded = ['id'];
    protected $hidden = ['deleted_at'];

    protected $dates = ['deleted_at'];
}