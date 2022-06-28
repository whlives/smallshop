<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/2
 * Time: 3:23 PM
 */

namespace App\Models\Seller;

use App\Models\BaseModel;

/**
 * 商家地址
 */
class SellerAddress extends BaseModel
{
    //是否默认
    const DEFAULT_OFF = 0;
    const DEFAULT_ON = 1;
    const DEFAULT_DESC = [
        self::DEFAULT_OFF => '否',
        self::DEFAULT_ON => '是'
    ];

    protected $table = 'seller_address';
    protected $guarded = ['id'];
}