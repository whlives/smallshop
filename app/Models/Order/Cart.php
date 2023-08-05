<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/14
 * Time: 2:45 PM
 */

namespace App\Models\Order;

use App\Models\BaseModel;

/**
 * 购物车
 */
class Cart extends BaseModel
{
    protected $table = 'cart';
    protected $guarded = ['id'];
    
    //购买类型
    const TYPE_CART = 1;
    const TYPE_NOW = 2;
    const TYPE_DESC = [
        self::TYPE_CART => '购物车',
        self::TYPE_NOW => '立即购买',
    ];

    /**
     * 删除购物车商品
     * @param int $m_id
     * @param array $cart
     * @return void
     */
    public static function delCart(int $m_id, array $cart)
    {
        $sku_id = array_column($cart, 'sku_id');
        self::where('m_id', $m_id)->whereIn('sku_id', $sku_id)->delete();
    }
}
