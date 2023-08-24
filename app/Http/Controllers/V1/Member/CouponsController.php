<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:41 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Http\Controllers\V1\BaseController;
use App\Models\Market\Coupons;
use App\Models\Market\CouponsDetail;
use App\Models\Seller\Seller;
use Illuminate\Http\Request;

class CouponsController extends BaseController
{
    public int $m_id;

    public function __construct()
    {
        $this->m_id = $this->getUserId();
    }

    /**
     * 获取优惠券
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function obtain(Request $request)
    {
        $coupons_id = (int)$request->post('coupons_id');
        if (!$coupons_id) {
            api_error(__('api.missing_params'));
        }
        $res = CouponsDetail::obtain($coupons_id, $this->m_id, 1);
        if ($res === true) {
            return $this->success();
        } else {
            api_error($res);
        }
    }

    /**
     * 优惠券已使用列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function isUse(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $where = [
            'm_id' => $this->m_id,
            'is_use' => CouponsDetail::USE_ON
        ];
        $res_list = self::getCoupons($where, $offset, $limit);
        $total = CouponsDetail::query()->where($where)->count();
        $return = [
            'lists' => $res_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 优惠券未使用列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function normal(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $where = [
            ['m_id', $this->m_id],
            ['is_use', CouponsDetail::USE_OFF],
            ['status', CouponsDetail::STATUS_ON],
            ['start_at', '<=', get_date()],
            ['end_at', '>=', get_date()]
        ];
        $res_list = self::getCoupons($where, $offset, $limit);
        $total = CouponsDetail::query()->where($where)->count();
        $return = [
            'lists' => $res_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 优惠券已过期列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function overdue(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $where = [
            ['m_id', $this->m_id],
            ['is_use', CouponsDetail::USE_OFF],
            ['status', CouponsDetail::STATUS_ON],
            ['end_at', '<=', get_date()]
        ];
        $res_list = self::getCoupons($where, $offset, $limit);
        $total = CouponsDetail::query()->where($where)->count();
        $return = [
            'lists' => $res_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 获取优惠券信息
     * @param $where
     * @param $offset
     * @param $limit
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    private function getCoupons($where, $offset, $limit)
    {
        $res_list = CouponsDetail::query()->select('id', 'coupons_id', 'start_at', 'end_at')
            ->where($where)
            ->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $coupons_ids = array_column($res_list->toArray(), 'coupons_id');
        //获取优惠券信息
        if ($coupons_ids) {
            $coupons_res = Coupons::query()->select('id', 'title', 'image', 'type', 'amount', 'use_price', 'note', 'seller_id')->whereIn('id', array_unique($coupons_ids))->get();
            $seller_ids = array_column($coupons_res->toArray(), 'seller_id');
            $coupons_res = array_column($coupons_res->toArray(), null, 'id');
            //获取商家信息
            $seller_res = Seller::query()->whereIn('id', array_unique($seller_ids))->pluck('title', 'id');
        }
        $data_list = [];
        foreach ($res_list->toArray() as $value) {
            $_coupons = $coupons_res[$value['coupons_id']] ?? [];
            if ($_coupons) {
                $_item = [
                    'title' => $_coupons['title'],
                    'image' => $_coupons['image'],
                    'type' => $_coupons['type'],
                    'amount' => $_coupons['amount'],
                    'use_price' => $_coupons['use_price'],
                    'note' => $_coupons['note'],
                    'start_at' => $value['start_at'],
                    'end_at' => $value['end_at'],
                    'seller_title' => $seller_res[$_coupons['seller_id']] ?? ''
                ];
                $data_list[] = $_item;
            }
        }
        return $data_list;
    }
}
