<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/3
 * Time: 4:45 PM
 */

namespace App\Models\Market;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 拼团
 */
class PromoGroup extends BaseModel
{
    use SoftDeletes;

    protected $table = 'promo_group';
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    /**
     * 验证拼团信息
     * @param int $goods_id
     * @param int $group_order_id
     * @return mixed
     * @throws \App\Exceptions\ApiError
     */
    public static function checkGroup(int $goods_id, int $group_order_id = 0)
    {
        $group = self::select('group_num', 'status', 'start_at', 'end_at')->where('goods_id', $goods_id)->first();
        if (!$group) {
            api_error(__('api.group_error'));
        } elseif ($group['status'] != self::STATUS_ON) {
            api_error(__('api.group_status_error'));
        } elseif ($group['start_at'] > get_date()) {
            api_error(__('api.group_not_start'));
        } elseif ($group['end_at'] < get_date()) {
            api_error(__('api.group_is_end'));
        }
        //存在拼团订单的时候验证订单
        if ($group_order_id) {
            $group_order = PromoGroupOrder::where('group_order_id', $group_order_id)->first();
            if (!$group_order) {
                api_error(__('api.group_order_id_error'));
            } elseif ($group_order['group_id'] != $group['id']) {
                api_error(__('api.group_error'));
            } elseif ($group_order['status'] == PromoGroupOrder::STATUS_SUCCESS) {
                api_error(__('api.group_is_success'));
            } elseif ($group_order['status'] != PromoGroupOrder::STATUS_WAIT_SUCCESS) {
                api_error(__('api.group_error'));
            }
        }
        $group = $group->toArray();
        $group['group_order_id'] = $group_order_id;
        return $group;
    }
}
