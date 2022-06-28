<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 9:08 PM
 */

namespace App\Models\Admin;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 后台用户
 */
class Admin extends BaseModel
{
    use SoftDeletes;

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    protected $table = 'admin';
    protected $guarded = ['id'];
    protected $hidden = ['password', 'deleted_at'];

    protected $dates = ['deleted_at'];
}
