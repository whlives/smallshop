<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/2/22
 * Time: 2:14 PM
 */

namespace App\Models\Tool;

use App\Models\BaseModel;

/**
 * 广告
 */
class Adv extends BaseModel
{
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    //跳转连接类型
    const TARGET_TYPE_URL = 1;
    const TARGET_TYPE_ARTICLE = 2;
    const TARGET_TYPE_THEME = 3;
    const TARGET_TYPE_GOODS = 4;
    const TARGET_TYPE_DESC = [
        self::TARGET_TYPE_URL => '链接',
        self::TARGET_TYPE_ARTICLE => '文章',
        self::TARGET_TYPE_THEME => '专题',
        self::TARGET_TYPE_GOODS => '商品'
    ];

    protected $table = 'adv';
    protected $guarded = ['id'];

}