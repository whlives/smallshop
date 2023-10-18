<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/24
 * Time: 2:32 PM
 */

namespace App\Http\Controllers\Seller\Financial;

use App\Http\Controllers\Seller\BaseController;
use App\Models\Financial\SellerWithdraw;
use Illuminate\Http\Request;

class WithdrawController extends BaseController
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
            'm_id' => $this->seller_id
        ];
        $status = $request->input('status');
        if (is_numeric($status)) $where[] = ['status', $status];
        $query = SellerWithdraw::query()->select('id', 'm_id', 'type', 'amount', 'name', 'bank_name', 'pay_number', 'refuse_note', 'status', 'created_at', 'done_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('status', 'asc')
            ->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['type'] = SellerWithdraw::TYPE_DESC[$value['type']];
            $_item['status_text'] = SellerWithdraw::STATUS_DESC[$value['status']];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 状态
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus(Request $request)
    {
        return $this->success(SellerWithdraw::STATUS_DESC);
    }

}
