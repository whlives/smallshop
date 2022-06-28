<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:33 PM
 */

namespace App\Models\Goods;

use App\Models\BaseModel;

/**
 * 商品评价图片
 */
class CommentUrl extends BaseModel
{

    //状态
    const TYPE_IMAGE = 1;
    const TYPE_VIDEO = 2;
    const TYPE_DESC = [
        self::TYPE_IMAGE => '图片',
        self::TYPE_VIDEO => '视频',
    ];

    protected $table = 'comment_url';
    protected $guarded = ['id'];

    public $timestamps = false;

}
