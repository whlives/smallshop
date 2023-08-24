<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:36 PM
 */

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Order\Order;
use App\Models\Order\OrderDelivery;
use App\Models\Order\OrderGoods;
use App\Services\ExportService;
use Illuminate\Http\Request;

class DeliveryController extends BaseController
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
        $order_id = (int)$request->input('order_id');
        $order_no = $request->input('order_no');
        $code = $request->input('code');
        if ($order_id) $where[] = ['order_id', $order_id];
        if ($order_no) {
            $order_id = Order::query()->where('order_no', $order_no)->value('id');
            if ($order_id) {
                $where[] = ['order_id', $order_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        if ($code) $where[] = ['code', $code];
        [$start_at, $end_at] = get_time_range();
        if ($start_at && $end_at) {
            $where[] = ['order_delivery.created_at', '>=', $start_at];
            $where[] = ['order_delivery.created_at', '<=', $end_at];
        }
        if ($export) {
            ExportService::delivery($request, ['where' => $where], $start_at, $end_at);//导出数据
            exit;
        }
        $query = OrderDelivery::query()->select('id', 'order_id', 'company_name', 'code', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $res_list = $res_list->toArray();
        $order_ids = array_column($res_list, 'order_id');
        if ($order_ids) {
            $order_data = Order::query()->whereIn('id', array_unique($order_ids))->pluck('order_no', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['order_no'] = $order_data[$value['order_id']] ?? '';
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 发货单详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $delivery = OrderDelivery::query()->find($id);
        if (!$delivery) {
            api_error(__('admin.content_is_empty'));
        }
        $order_goods_id = json_decode($delivery['order_goods_id'], true);
        $order_goods = OrderGoods::getGoodsForId($order_goods_id);
        if (!$order_goods) {
            api_error(__('admin.content_is_empty'));
        }
        return $this->success(['goods' => $order_goods]);
    }
}
