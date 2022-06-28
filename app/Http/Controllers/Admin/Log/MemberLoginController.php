<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/23
 * Time: 4:17 PM
 */

namespace App\Http\Controllers\Admin\Log;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminLoginLog;
use App\Models\Member\Member;
use App\Models\Member\MemberLoginLog;
use App\Services\TokenService;
use Illuminate\Http\Request;

class MemberLoginController extends BaseController
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
        $username = $request->input('username');
        if ($username) {
            $member_id = Member::where('username', $username)->value('id');
            if ($member_id) {
                $where[] = ['m_id', $member_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        $query = MemberLoginLog::select('id', 'm_id', 'ip', 'platform', 'version', 'system', 'mobile_model', 'status', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('admin.content_is_empty'));
        }
        $m_ids = array_column($res_list->toArray(), 'm_id');
        if ($m_ids) {
            $member_data = Member::whereIn('id', array_unique($m_ids))->pluck('username', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['username'] = $member_data[$value['m_id']] ?? '';
            $_item['status_text'] = MemberLoginLog::STATUS_DESC[$value['status']];
            $data_list[] = $_item;
        };
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 退出指定用户登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function loginOut(Request $request)
    {
        $id = (int)$request->input('id');
        if (!$id) {
            api_error(__('admin.missing_params'));
        }
        $data = MemberLoginLog::find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        MemberLoginLog::where('id', $id)->update(['status' => MemberLoginLog::STATUS_OFF]);
        $token_service = new TokenService();
        $token_service->delToken($data['token']);
        return $this->success();
    }
}
