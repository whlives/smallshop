<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/3
 * Time: 4:45 PM
 */

namespace App\Models\Market;

use App\Models\BaseModel;
use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;

/**
 * 拼团订单
 */
class PromoGroupOrder extends BaseModel
{
    //状态
    const STATUS_WAIT_PAY = 0;
    const STATUS_WAIT_SUCCESS = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_CANCEL = 3;
    const STATUS_DESC = [
        self::STATUS_WAIT_PAY => '待支付',
        self::STATUS_WAIT_SUCCESS => '待成团',
        self::STATUS_SUCCESS => '已成团',
        self::STATUS_CANCEL => '已取消',
    ];

    //是否团长
    const IS_HEAD_NO = 0;
    const IS_HEAD_YES = 1;
    const IS_HEAD_DESC = [
        self::IS_HEAD_NO => '否',
        self::IS_HEAD_YES => '是',
    ];

    protected $table = 'promo_group_order';
    protected $guarded = ['id'];

    /**
     * 提交拼团订单
     * @param array $order
     * @param array $promo_data
     * @return mixed
     */
    public static function submit(array $order, array $promo_data)
    {
        $group = PromoGroup::withTrashed()->where(['goods_id' => $promo_data['goods_id']])->first();
        $group_num = 0;
        if ($promo_data['group_order_id']) {
            $group_num = self::where(['group_id' => $group['id'], 'group_order_id' => $promo_data['group_order_id'], 'status' => self::STATUS_WAIT_SUCCESS])->count();//查询待成团的订单数
            $order_end_at = self::where('id', $promo_data['group_order_id'])->value('end_at');
        }
        //设置拼团结束时间
        $end_at = get_date(time() + ($group['hour'] * 3600));
        if (isset($order_end_at) && $order_end_at) $end_at = $order_end_at;//是加入别人的拼团的时候用别人的时间
        //结束时间大于活动结束时间的时候直接用活动结束时间
        if ($end_at > $group['end_at']) $end_at = $group['end_at'];
        //添加拼团订单
        $group_order = [
            'm_id' => $order['m_id'],
            'group_id' => $group['id'],
            'group_order_id' => $promo_data['group_order_id'],
            'order_id' => $order['id'],
            'status' => self::STATUS_WAIT_PAY,
            'end_at' => $end_at,
            'is_head' => $group_num > 0 ? self::IS_HEAD_NO : self::IS_HEAD_YES,//还没有订单的时候自己就是团长
        ];
        try {
            $res = DB::transaction(function () use ($group_order) {
                $res = self::create($group_order);
                $group_order_id = $res->id;
                if ($group_order['is_head'] == self::IS_HEAD_YES) {
                    self::where('id', $group_order_id)->update(['group_order_id' => $group_order_id]);
                }
                return true;
            });
        } catch (\Exception $e) {
            $res = false;
        }
        return $res;
    }

    /**
     * 取消拼团订单
     * @param int $order_id
     * @return false|mixed
     */
    public static function cancel(int $order_id)
    {
        $group_order = self::where('order_id', $order_id)->first();
        $update_data = [
            'status' => self::STATUS_CANCEL,
            'is_head' => self::IS_HEAD_NO,
        ];
        $next_head = '';//是否需要指定新团长
        if ($group_order['is_head'] == self::IS_HEAD_YES) {
            //如果是开团的人，需要更换开团人员为下一位
            $next_head = self::where(['group_order_id' => $group_order['group_order_id'], 'status' => self::STATUS_WAIT_SUCCESS])->orderBy('id', 'asc')->first();
        }
        try {
            $res = DB::transaction(function () use ($group_order, $update_data, $next_head) {
                self::where('id', $group_order['id'])->update($update_data);
                if (isset($next_head['id']) && $next_head['id']) {
                    self::where('id', $next_head['id'])->update(['is_head' => self::IS_HEAD_YES]);//修改拼团团长
                    self::where('group_order_id', $group_order['group_order_id'])->update(['group_order_id' => $next_head['id']]);//修改开团订单id
                }
                return true;
            });
        } catch (\Exception $e) {
            $res = false;
        }
        return $res;
    }

    /**
     * 支付拼团订单处理是否成团
     * @param int $order_id
     * @return false|mixed
     */
    public static function pay(int $order_id)
    {
        $group_order = self::where('order_id', $order_id)->first();
        $group_head = self::where(['group_order_id' => $group_order['group_order_id']])->first();//开团订单
        $group = PromoGroup::withTrashed()->where('id', $group_order['group_id'])->first();//这里可能已经删除了，所以需要查询软删除的
        try {
            if ($group_head['status'] != self::STATUS_WAIT_SUCCESS) {
                //这种是拼团已经取消或者成功了，自己只能变成新开的
                $end_at = get_date(time() + ($group['hour'] * 3600));
                if ($end_at > $group['end_at']) $end_at = $group['end_at'];
                $update_order = [
                    'group_order_id' => $group_order['id'],
                    'status' => self::STATUS_WAIT_SUCCESS,
                    'is_head' => self::IS_HEAD_YES,
                    'end_at' => $end_at
                ];
                $res = DB::transaction(function () use ($order_id, $update_order) {
                    self::where('order_id', $order_id)->update($update_order);
                    Order::where('id', $order_id)->update(['status' => Order::STATUS_WAIT_GROUP]);
                    return true;
                });
            } else {
                $group_num = self::where(['group_order_id' => $group_order['group_order_id'], 'status' => self::STATUS_WAIT_SUCCESS])->count();
                if (($group_num + 1) < $group['group_num']) {
                    //这种肯定是还没有达到的，改成待成团
                    $res = DB::transaction(function () use ($order_id) {
                        self::where('order_id', $order_id)->update(['status' => self::STATUS_WAIT_SUCCESS]);
                        Order::where('id', $order_id)->update(['status' => Order::STATUS_WAIT_GROUP]);
                        return true;
                    });
                } else {
                    //这种是已经成功的
                    $res = DB::transaction(function () use ($order_id, $group_order) {
                        self::where('order_id', $order_id)->update(['status' => self::STATUS_WAIT_SUCCESS]);//先修改自己的状态
                        $order_ids = self::where(['group_order_id' => $group_order['group_order_id'], 'status' => self::STATUS_WAIT_SUCCESS])->pluck('order_id')->toArray();//查询所有带成团的拼团订单
                        Order::where('id', $order_ids)->update(['status' => Order::STATUS_PAID]);//修改订单状态
                        self::whereIn('order_id', $order_ids)->update(['status' => self::STATUS_SUCCESS]);//修改拼团订单状态
                        return true;
                    });
                }
            }
        } catch (\Exception $e) {
            $res = false;
        }
        return $res;
    }

    /**
     * 取消超时的拼团订单
     * @param int $order_id
     * @return false|mixed
     */
    public static function timeOut(array $id)
    {
        $update_data = [
            'status' => self::STATUS_CANCEL,
        ];
        $res = self::whereIn('id', $id)->update($update_data);
        if ($res) {
            return true;
        } else {
            return false;
        }
    }

}