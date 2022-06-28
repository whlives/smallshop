<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/24
 * Time: 2:32 PM
 */

namespace App\Http\Controllers\Seller\Financial;

use App\Http\Controllers\Seller\BaseController;
use App\Models\Financial\SellerBalance;
use App\Models\Financial\SellerBalanceDetail;
use App\Models\Financial\SellerWithdraw;
use App\Models\Seller\Seller;
use Illuminate\Http\Request;
use Validator;

class BalanceController extends BaseController
{

    public int $seller_id;

    public function __construct()
    {
        $this->seller_id = $this->getUserId();
    }

    /**
     * 列表获取
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        //搜索
        $where = [
            ['m_id', $this->seller_id]
        ];
        $query = SellerBalanceDetail::select('id', 'm_id', 'type', 'event', 'detail_no', 'amount', 'balance', 'note', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['event'] = SellerBalanceDetail::EVENT_DESC[$value['event']];
            $_item['amount'] = ($value['type'] == SellerBalanceDetail::TYPE_RECR ? '-' : '+') . $value['amount'];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 提现
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function withdraw(Request $request)
    {
        $type = (int)$request->post('type');
        $amount = $request->post('amount');
        $name = $request->post('name');
        $bank_name = $request->post('bank_name');
        $pay_number = $request->post('pay_number');
        if (!$amount || !check_price($amount) || !$name || !$pay_number) {
            api_error(__('admin.missing_params'));
        } elseif (!check_price($amount) || !isset(SellerWithdraw::TYPE_DESC[$type])) {
            api_error(__('admin.invalid_params'));
        }
        if ($type == SellerWithdraw::TYPE_BANK && !$bank_name) {
            api_error(__('admin.missing_params'));
        }
        $withdraw_no = SellerWithdraw::getWithdrawNo();
        //获取姓名如果需要开启实名认证这里就需要加上姓名
        $res = SellerBalance::updateAmount($this->seller_id, -$amount, SellerBalanceDetail::EVENT_WITHDRAW, $withdraw_no);
        if ($res['status']) {
            $withdraw_data = [
                'm_id' => $this->seller_id,
                'withdraw_no' => $withdraw_no,
                'type' => $type,
                'amount' => $amount,
                'name' => $name,
                'bank_name' => $bank_name,
                'pay_number' => $pay_number,
            ];
            $add = SellerWithdraw::create($withdraw_data);
            if ($add) {
                return $this->success();
            } else {
                api_error(__('api.fail'));
            }
        } else {
            api_error($res['message']);
        }
    }

    /**
     * 类型
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function type(Request $request)
    {
        return $this->success(SellerWithdraw::TYPE_DESC);
    }

}