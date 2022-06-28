<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:33 PM
 */

namespace App\Models\Goods;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 商品属性值
 */
class AttributeValue extends BaseModel
{
    use SoftDeletes;

    protected $table = 'attribute_value';
    protected $guarded = ['id'];

    protected $dates = ['deleted_at'];

}
