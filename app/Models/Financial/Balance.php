<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/24
 * Time: 11:09 AM
 */

namespace App\Models\Financial;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * 用户资金
 */
class Balance extends BaseModel
{
    use SoftDeletes;

    protected $table = 'balance';
    protected $guarded = ['id'];

    /**
     * 修改现金账户并记录详情
     * @param int $m_id 用户id
     * @param float $amount 金额
     * @param int $event 类型
     * @param string|null $detail_no 单号
     * @param string|null $note 备注
     * @return array
     */
    public static function updateAmount(int $m_id, float $amount, int $event, string|null $detail_no = '', string|null $note = ''): array
    {
        $return = [
            'status' => false,
            'message' => __('api.fail')
        ];
        if (!$m_id || !$amount || !$event) {
            $return['message'] = __('api.missing_params');
            return $return;
        }
        if (!isset(BalanceDetail::EVENT_DESC[$event])) {
            $return['message'] = __('api.balance_event_error');
            return $return;
        }
        //变动详情
        $detail = [
            'm_id' => $m_id,
            'type' => $amount >= 0 ? BalanceDetail::TYPE_INCR : BalanceDetail::TYPE_RECR,
            'event' => $event,
            'amount' => abs($amount),
            'balance' => 0,
            'detail_no' => $detail_no,
            'note' => $note
        ];
        try {
            $res = DB::transaction(function () use ($m_id, $amount, $detail) {
                $res_data = self::where('m_id', $m_id)->lockForUpdate()->first();//查询用户余额并锁定
                if ($amount < 0 && (!isset($res_data['amount']) || ($res_data['amount'] + $amount) < 0)) {
                    return __('api.balance_insufficient');
                } else {
                    //数据存在的时候直接修改
                    if ($res_data) {
                        $where[] = ['m_id', $m_id];
                        //减少的时候加上条件
                        if ($amount < 0) {
                            $where[] = ['amount', '>=', abs($amount)];
                        }
                        $res = self::where($where)->increment('amount', $amount);
                    } else {
                        $res_data['amount'] = 0;
                        $res = self::create(['m_id' => $m_id, 'amount' => $amount]);
                    }
                    $detail['balance'] = $res_data['amount'] + $amount;
                    BalanceDetail::create($detail);
                    if ($res) {
                        return true;
                    }
                }
            });
        } catch (\Exception $e) {
            $res = false;
        }
        if ($res === true) {
            $return['status'] = true;
            $return['message'] = '';
            return $return;
        } elseif ($res) {
            $return['message'] = $res;
        }
        return $return;
    }

}