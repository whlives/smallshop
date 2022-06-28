<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/27
 * Time: 11:19 AM
 */

namespace App\Http\Controllers\V1;

use App\Models\Market\Coupons;
use Illuminate\Http\Request;

class CouponsController extends BaseController
{
    /**
     * 列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function seller(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $seller_id = (int)$request->route('seller_id');
        $where = [
            ['seller_id', $seller_id],
            ['status', Coupons::STATUS_ON],
            ['open', Coupons::OPEN_ON],
        ];
        $where_date = [
            ['start_at', '<=', get_date()],
            ['end_at', '>=', get_date()]
        ];
        $query = Coupons::select('id', 'title', 'image', 'type', 'amount', 'use_price', 'start_at', 'end_at', 'day_num', 'note')
            ->where($where)
            ->where(function ($query) use ($where_date) {
                $query->where($where_date)
                    ->orWhere('day_num', '>', 0);
            });
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $return = [
            'lists' => $res_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

}