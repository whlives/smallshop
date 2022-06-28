<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:47 PM
 */

namespace App\Models\Member;

use App\Models\BaseModel;

/**
 * 收藏
 */
class Favorite extends BaseModel
{

    //类型
    const TYPE_GOODS = 1;
    const TYPE_SELLER = 2;
    const TYPE_ARTICLE = 3;
    const TYPE_DESC = [
        self::TYPE_GOODS => '商品',
        self::TYPE_SELLER => '商家',
        self::TYPE_ARTICLE => '文章'
    ];

    protected $table = 'favorite';
    protected $guarded = ['id'];
}