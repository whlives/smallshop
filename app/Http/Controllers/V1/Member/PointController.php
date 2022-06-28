<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:41 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Http\Controllers\V1\BaseController;
use App\Models\Financial\PointDetail;
use Illuminate\Http\Request;

class PointController extends BaseController
{
    public int $m_id;

    public function __construct()
    {
        $this->m_id = $this->getUserId();
    }

    /**
     * 列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function index(Request $request)
    {
        [$limit, $offset] = get_page_params();
        $event = (int)$request->post('event');
        $where = [
            'm_id' => $this->m_id
        ];
        if (isset(PointDetail::EVENT_DESC[$event])) $where['event'] = $event;
        $query = PointDetail::select('id', 'type', 'event', 'detail_no', 'amount', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['event'] = PointDetail::EVENT_DESC[$value['event']];
            $_item['amount'] = ($value['type'] == PointDetail::TYPE_INCR ? '+' : '-') . $value['amount'];
            unset($_item['type']);
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function detail(Request $request)
    {
        $id = (int)$request->post('id');
        if (!$id) {
            api_error(__('api.missing_params'));
        }
        $res_list = PointDetail::select('event', 'detail_no', 'amount', 'balance', 'note', 'created_at')->where(['id' => $id, 'm_id' => $this->m_id])->first();
        if (!$res_list) {
            api_error(__('api.content_is_empty'));
        }
        $res_list['event'] = PointDetail::EVENT_DESC[$res_list['event']];
        return $this->success($res_list);
    }

}