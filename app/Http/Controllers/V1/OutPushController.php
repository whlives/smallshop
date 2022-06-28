<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/13
 * Time: 10:49 PM
 */

namespace App\Http\Controllers\V1;

use App\Libs\Delivery;
use App\Models\System\Payment;
use App\Services\TradeService;
use Illuminate\Http\Request;

class OutPushController extends BaseController
{

    /**
     * 支付回调
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function payNotify(Request $request)
    {
        $payment_id = (int)$request->route('payment_id');
        $platform = $request->route('platform');
        //验证支付方式
        $payment = Payment::where(['id' => $payment_id, 'status' => Payment::STATUS_ON])->first();
        if (!$payment) {
            api_error(__('api.payment_error'));
        }
        $class_name = '\App\Libs\Payment\\' . $payment['class_name'];
        $pay = new $class_name($platform);
        $res_data = $pay->notify();
        //微信支付直接返回就可以
        if ($payment_id == Payment::PAYMENT_WECHAT) {
            return $res_data;
        }
        if (is_array($res_data)) {
            //修改交易单和订单状态
            $res = TradeService::updatePayStatus($res_data);
            if ($res) {
                return $pay->success();
            } else {
                return $pay->fail();
            }
        } else {
            return $pay->fail();
        }
    }

    /**
     * 物流回调
     * @return \Illuminate\Http\JsonResponse
     */
    public function deliveryNotify()
    {
        $delivery = new Delivery();
        $res = $delivery->notify();
        return response()->json($res);
    }


}