<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/23
 * Time: 4:17 PM
 */

namespace App\Http\Controllers\Admin\Log;

use App\Http\Controllers\Admin\BaseController;
use App\Models\System\FileLog;
use Illuminate\Http\Request;
use Validator;

class FileController extends BaseController
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
        $title = $request->input('title');
        if ($title) $where[] = ['title', $title];
        $query = FileLog::query()->select('id', 'title', 'url', 'type', 'created_at')
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
            $_item['type'] = FileLog::TYPE_DESC[$value['type']];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

    /**
     * 添加
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function save(Request $request)
    {
        //验证规则
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'url' => 'required'
        ], [
            'title.required' => '名称不能为空',
            'url.required' => '地址不能为空',
        ]);
        $error = $validator->errors()->all();
        if ($error) {
            api_error(current($error));
        }
        $save_data = [];
        foreach ($request->only(['title', 'url']) as $key => $value) {
            $save_data[$key] = $value;
        }
        $res = FileLog::query()->create($save_data);
        if ($res) {
            return $this->success();
        } else {
            api_error(__('admin.save_error'));
        }
    }
}
