<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/14
 * Time: 2:45 PM
 */

namespace App\Models\Order;

use App\Models\BaseModel;
use App\Models\Member\Member;

/**
 * 售后日志
 */
class RefundLog extends BaseModel
{
    protected $table = 'refund_log';
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

    const ACTION_APPLY = 1;//申请
    const ACTION_AGREE = 2;//同意
    const ACTION_REFUSED = 3;//拒绝
    const ACTION_MEMBER_SEND = 4;//退货
    const ACTION_SELLER_SEND = 5;//商家发货
    const ACTION_COMPLETE = 6;//完成
    const ACTION_CANCEL = 7;//取消
    const ACTION_EDIT = 8;//修改
    const ACTION_DESC = [
        self::ACTION_APPLY => '申请',
        self::ACTION_AGREE => '同意',
        self::ACTION_REFUSED => '拒绝',
        self::ACTION_MEMBER_SEND => '退货',
        self::ACTION_SELLER_SEND => '商家发货',
        self::ACTION_COMPLETE => '完成',
        self::ACTION_CANCEL => '取消',
        self::ACTION_EDIT => '修改',
    ];

    /**
     * 获取售后日志
     * @param array $refund
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public static function getLog(array $refund)
    {
        $log_res = RefundLog::select('id', 'user_type', 'user_id', 'username', 'action', 'note', 'created_at')->where('refund_id', $refund['id'])->orderBy('id', 'desc')->get();
        if ($log_res->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        //查询售后图片
        $log_ids = array_column($log_res->toArray(), 'id');
        $refund_image_res = RefundImage::whereIn('log_id', $log_ids)->get();
        $refund_image = [];
        if (!$refund_image_res->isEmpty()) {
            foreach ($refund_image_res as $value) {
                $refund_image[$value['log_id']][]['image'] = $value['image'];
            }
        }
        $headimg = Member::where('id', $refund['m_id'])->value('headimg');
        $log = [];
        foreach ($log_res as $value) {
            $_item = $value;
            $_item['headimg'] = $value['user_type'] == RefundLog::USER_TYPE_MEMBER ? $headimg : get_custom_config('member_default_headimg');
            $_item['action'] = RefundLog::ACTION_DESC[$value['action']];
            $_item['user_type'] = RefundLog::USER_TYPE_DESC[$value['user_type']];
            if ($value['note']) $_item['note'] = json_decode($value['note'], true);
            $_item['image'] = $refund_image[$value['id']] ?? [];
            $log[] = $_item;
        }
        return $log;
    }

}
