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
 * 商品属性
 */
class Attribute extends BaseModel
{
    use SoftDeletes;

    const INPUT_TYPE_SELECT = 'select';
    const INPUT_TYPE_CHECKBOX = 'checkbox';
    const INPUT_TYPE_RADIO = 'radio';
    const INPUT_TYPE_TEXT = 'text';
    const INPUT_TYPE_DESC = [
        self::INPUT_TYPE_SELECT => '下拉框',
        self::INPUT_TYPE_CHECKBOX => '多选',
        self::INPUT_TYPE_RADIO => '单选',
        self::INPUT_TYPE_TEXT => '文本框'
    ];

    protected $table = 'attribute';
    protected $guarded = ['id'];

    protected $dates = ['deleted_at'];

    /**
     * 获取属性值
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function attrValue()
    {
        return $this->hasMany('App\Models\Goods\AttributeValue');
    }

}
