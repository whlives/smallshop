<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/7/26
 * Time: 14:21 PM
 */

namespace App\Models\Goods;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 套餐包
 */
class GoodsPackage extends BaseModel
{
    use SoftDeletes;

    protected $table = 'goods_package';
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

}
