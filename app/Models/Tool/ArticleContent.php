<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 9:45 PM
 */

namespace App\Models\Tool;

use App\Models\BaseModel;

/**
 * 文章内容
 */
class ArticleContent extends BaseModel
{
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',

    ];

    protected $table = 'article_content';
    protected $guarded = [];

    public $timestamps = false;

}
