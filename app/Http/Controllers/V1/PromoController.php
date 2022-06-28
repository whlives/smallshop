<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 3:39 PM
 */

namespace App\Http\Controllers\V1;

use App\Models\Goods\Goods;
use App\Models\Market\PromoGroup;
use App\Models\Market\PromoGroupOrder;
use App\Models\Market\PromoSeckill;
use App\Models\Member\Member;
use Illuminate\Http\Request;

class PromoController extends BaseController
{
    /**
     * 拼团活动列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function group(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $where = [
            ['status', PromoGroup::STATUS_ON],
            ['start_at', '<', get_date()],
            ['end_at', '>', get_date()]
        ];
        $query = PromoGroup::select('id', 'title', 'goods_id', 'group_num', 'start_at', 'end_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $goods_ids = array_column($res_list->toArray(), 'goods_id');
        $goods = Goods::select('id', 'title', 'image')->whereIn('id', $goods_ids)->get();
        if (!$goods->isEmpty()) {
            $goods = array_column($goods->toArray(), null, 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = [
                'id' => $value['id'],
                'title' => $value['title'],
                'group_num' => $value['group_num'],
                'start_at' => $value['start_at'],
                'end_at' => $value['end_at'],
                'goods_title' => $goods[$value['goods_id']]['title'] ?? '',
                'goods_image' => $goods[$value['goods_id']]['image'] ?? '',
            ];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 商品开团（待成团的）列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function groupOrder(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $goods_id = (int)$request->route('goods_id');
        if (!$goods_id) {
            api_error(__('api.missing_params'));
        }
        $group = PromoGroup::checkGroup($goods_id);
        $where = [
            'group_id' => $group['id'],
            'is_head' => PromoGroupOrder::IS_HEAD_YES,
            'status' => PromoGroupOrder::STATUS_WAIT_SUCCESS
        ];
        $query = PromoGroupOrder::select('id', 'm_id', 'group_id', 'end_at', 'is_head')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('is_head', 'desc')
            ->orderBy('id', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $m_ids = array_column($res_list->toArray(), 'm_id');
        $member = Member::select('id', 'headimg', 'nickname')->whereIn('id', $m_ids)->get();
        if (!$member->isEmpty()) {
            $member = array_column($member->toArray(), null, 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = [
                'id' => $value['id'],
                'end_at' => $value['end_at'],
                'headimg' => $member[$value['m_id']]['headimg'] ?? '',
                'nickname' => $member[$value['m_id']]['nickname'] ?? '',
            ];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 商品拼团进度列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function groupOrderDetail(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $group_order_id = (int)$request->route('group_order_id');
        if (!$group_order_id) {
            api_error(__('api.missing_params'));
        }
        $where = [
            'group_order_id' => $group_order_id,
        ];
        $query = PromoGroupOrder::select('id', 'm_id', 'end_at', 'is_head')
            ->where($where)
            ->whereIn('status', [PromoGroupOrder::STATUS_WAIT_SUCCESS, PromoGroupOrder::STATUS_SUCCESS]);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('is_head', 'desc')
            ->orderBy('id', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $m_ids = array_column($res_list->toArray(), 'm_id');
        $member = Member::select('id', 'headimg', 'nickname')->whereIn('id', $m_ids)->get();
        if (!$member->isEmpty()) {
            $member = array_column($member->toArray(), null, 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = [
                'id' => $value['id'],
                'is_head' => $value['is_head'],
                'end_at' => $value['end_at'],
                'headimg' => $member[$value['m_id']]['headimg'] ?? '',
                'nickname' => $member[$value['m_id']]['nickname'] ?? '',
            ];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 秒杀活动列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function seckill(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $where = [
            ['status', PromoSeckill::STATUS_ON],
            ['start_at', '<', get_date()],
            ['end_at', '>', get_date()]
        ];
        $query = PromoSeckill::select('id', 'title', 'goods_id', 'start_at', 'end_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $goods_ids = array_column($res_list->toArray(), 'goods_id');
        $goods = Goods::select('id', 'title', 'image')->whereIn('id', $goods_ids)->get();
        if (!$goods->isEmpty()) {
            $goods = array_column($goods->toArray(), null, 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = [
                'id' => $value['id'],
                'title' => $value['title'],
                'start_at' => $value['start_at'],
                'end_at' => $value['end_at'],
                'goods_title' => $goods[$value['goods_id']]['title'] ?? '',
                'goods_image' => $goods[$value['goods_id']]['image'] ?? '',
            ];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

}