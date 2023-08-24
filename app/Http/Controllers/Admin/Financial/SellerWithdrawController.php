<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/24
 * Time: 2:32 PM
 */

namespace App\Http\Controllers\Admin\Financial;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Financial\SellerBalance;
use App\Models\Financial\SellerBalanceDetail;
use App\Models\Financial\SellerWithdraw;
use App\Models\Seller\Seller;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerWithdrawController extends BaseController
{

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
        $where = [];
        $export = (int)$request->input('export');
        $username = $request->input('username');
        $status = $request->input('status');
        if ($username) {
            $m_id = Seller::query()->where('username', $username)->value('id');
            if ($m_id) {
                $where[] = ['m_id', $m_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        if (is_numeric($status)) $where[] = ['seller_withdraw.status', $status];
        [$start_at, $end_at] = get_time_range();
        if ($start_at && $end_at) {
            $where[] = ['seller_withdraw.created_at', '>=', $start_at];
            $where[] = ['seller_withdraw.created_at', '<=', $end_at];
        }
        if ($export) {
            ExportService::sellerWithdraw($request, ['where' => $where], $start_at, $end_at);//导出数据
            exit;
        }
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
        $m_ids = array_column($res_list->toArray(), 'm_id');
        if ($m_ids) {
            $member_data = Seller::query()->whereIn('id', array_unique($m_ids))->pluck('username', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['username'] = $member_data[$value['m_id']] ?? '';
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

    /**
     * 审核
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function audit(Request $request)
    {
        $id = (int)$request->input('id');
        $status = (int)$request->input('status');
        $note = $request->input('note');
        if (!$id || !isset(SellerWithdraw::STATUS_DESC[$status]) || (in_array($status, [SellerWithdraw::STATUS_REFUND, SellerWithdraw::STATUS_DEDUCT]) && !$note)) {
            api_error(__('admin.missing_params'));
        }
        $withdraw = SellerWithdraw::query()->where(['id' => $id, 'status' => SellerWithdraw::STATUS_OFF])->first();
        if (!$withdraw) {
            api_error(__('admin.missing_params'));
        }
        $update_data = [
            'status' => $status,
            'refuse_note' => $note,
            'done_at' => get_date()
        ];
        try {
            DB::transaction(function () use ($withdraw, $update_data) {
                SellerWithdraw::query()->where(['id' => $withdraw['id'], 'status' => SellerWithdraw::STATUS_OFF])->update($update_data);
                if ($update_data['status'] == SellerWithdraw::STATUS_REFUND) {
                    SellerBalance::updateAmount($withdraw['m_id'], $withdraw['amount'], SellerBalanceDetail::EVENT_WITHDRAW_REFUND, $withdraw['id']);
                }
            });
            $res = true;
        } catch (\Exception $e) {
            $res = false;
        }
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
    }

}
