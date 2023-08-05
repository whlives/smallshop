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
 * 商品规格值
 */
class SpecValue extends BaseModel
{
    use SoftDeletes;

    protected $table = 'spec_value';
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

}
