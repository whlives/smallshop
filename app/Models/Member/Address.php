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
 * 地址
 */
class Address extends BaseModel
{
    protected $table = 'address';
    protected $guarded = ['id'];
    
    //是否默认
    const DEFAULT_OFF = 0;
    const DEFAULT_ON = 1;
    const DEFAULT_DESC = [
        self::DEFAULT_OFF => '否',
        self::DEFAULT_ON => '是'
    ];

}
