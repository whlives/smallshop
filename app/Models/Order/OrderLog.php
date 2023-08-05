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
 * 订单日志
 */
class OrderLog extends BaseModel
{
    protected $table = 'order_log';
    protected $guarded = ['id'];
    
    //用户类型
    const USER_TYPE_MEMBER = 0;
    const USER_TYPE_SYSTEM = 1;
    const USER_TYPE_ADMIN = 2;
    const USER_TYPE_SELLER = 3;
    const USER_TYPE_DESC = [
        self::USER_TYPE_MEMBER => '用户',
        self::USER_TYPE_SYSTEM => '系统',
        self::USER_TYPE_ADMIN => '管理员',
        self::USER_TYPE_SELLER => '商家',
    ];

    const ACTION_PAY = 1;//支付
    const ACTION_SEND = 2;//发货
    const ACTION_UN_SEND = 3;//取消发货
    const ACTION_CONFIRM = 4;//确认
    const ACTION_COMPLETE = 5;//完成
    const ACTION_CANCEL = 6;//取消
    const ACTION_EDIT = 7;//修改
    const ACTION_DESC = [
        self::ACTION_PAY => '支付',
        self::ACTION_SEND => '发货',
        self::ACTION_UN_SEND => '取消发货',
        self::ACTION_CONFIRM => '确认',
        self::ACTION_COMPLETE => '完成',
        self::ACTION_CANCEL => '取消',
        self::ACTION_EDIT => '修改',
    ];

}
