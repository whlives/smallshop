<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/1
 * Time: 4:30 PM
 */

namespace App\Models\Member;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 会员组
 */
class MemberGroup extends BaseModel
{
    use SoftDeletes;

    protected $table = 'member_group';
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
