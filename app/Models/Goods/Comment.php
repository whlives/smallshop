<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:33 PM
 */

namespace App\Models\Goods;

use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

/**
 * 商品评价
 */
class Comment extends BaseModel
{

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '待审核',
        self::STATUS_ON => '已审核',
    ];

    protected $table = 'comment';
    protected $guarded = ['id'];

    /**
     * 获取图片
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function image()
    {
        return $this->hasMany('App\Models\Goods\CommentUrl');
    }

}
