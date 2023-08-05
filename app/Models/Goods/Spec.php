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
 * 商品规格
 */
class Spec extends BaseModel
{
    use SoftDeletes;

    protected $table = 'spec';
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    const TYPE_IMAGE_OFF = 0;
    const TYPE_IMAGE_ON = 1;
    const TYPE_IMAGE_DESC = [
        self::TYPE_IMAGE_OFF => '否',
        self::TYPE_IMAGE_ON => '是'
    ];

    /**
     * 获取规格值
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function specValue()
    {
        return $this->hasMany('App\Models\Goods\SpecValue');
    }

}
