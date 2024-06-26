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
use App\Services\TokenService;
use Illuminate\Http\Request;

class AdminLoginController extends BaseController
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
            $member_id = Admin::query()->where('username', $username)->value('id');
            if ($member_id) {
                $where[] = ['m_id', $member_id];
            } else {
                api_error(__('admin.content_is_empty'));
            }
        }
        $query = AdminLoginLog::query()->select('id', 'm_id', 'user_agent', 'ip', 'status', 'created_at')
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
            $admin_data = Admin::query()->whereIn('id', array_unique($m_ids))->pluck('username', 'id');
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['username'] = $admin_data[$value['m_id']] ?? '';
            $_item['status_text'] = AdminLoginLog::STATUS_DESC[$value['status']];
            $data_list[] = $_item;
        }
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
        $data = AdminLoginLog::query()->find($id);
        if (!$data) {
            api_error(__('admin.content_is_empty'));
        }
        AdminLoginLog::query()->where('id', $id)->update(['status' => AdminLoginLog::STATUS_OFF]);
        $token_service = new TokenService();
        $token_service->delToken($data['token']);
        return $this->success();
    }
}
