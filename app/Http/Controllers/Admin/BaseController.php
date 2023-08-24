<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 8:58 PM
 */

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiError;
use App\Models\Admin\Admin;
use App\Services\TokenService;

class BaseController extends \App\Http\Controllers\BaseController
{

    /**
     * 获取用户id
     * @return int
     * @throws ApiError
     */
    public function getUserId(): int
    {
        $token_service = new TokenService();
        $token = $token_service->getToken();
        if (isset($token['id']) && $token['id']) {
            return $token['id'];
        } else {
            api_error(__('admin.invalid_token'));
        }
    }

    /**
     * 获取用户信息
     * @return array
     * @throws ApiError
     */
    public function getUserInfo(): array
    {
        $user_id = $this->getUserId();
        $user_data = Admin::query()->find($user_id);
        return $user_data->toArray();
    }

    /**
     * 批量修改或编辑的时候验证id
     * @return array
     * @throws ApiError
     */
    public function checkBatchId(): array
    {
        $id = request()->input('id');
        $ids = [];
        if (is_array($id)) {
            foreach ($id as $val) {
                $_id = (int)$val;
                if ($_id) {
                    $ids[] = $_id;
                }
            }
        } else {
            $ids = [(int)$id];
        }
        if (!$ids) {
            api_error(__('admin.invalid_params'));
        }
        return $ids;
    }
}
