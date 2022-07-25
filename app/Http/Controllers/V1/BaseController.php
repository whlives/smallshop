<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 8:58 PM
 */

namespace App\Http\Controllers\V1;

use App\Exceptions\ApiError;
use App\Models\Member\Member;
use App\Services\TokenService;

class BaseController extends \App\Http\Controllers\BaseController
{
    /**
     * 仅获取用户id，不验证（适用场景比如商品收藏）
     * @return int
     * @throws ApiError
     */
    public function getOnlyUserId(): int
    {
        $token_service = new TokenService();
        $token = $token_service->getToken();
        if (isset($token['id']) && $token['id']) {
            return $token['id'];
        } else {
            return false;
        }
    }


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
            api_error(__('api.invalid_token'));
        }
    }

    /**
     * 获取用户信息
     * @return array
     * @throws ApiError
     */
    public function getUserInfo(): array
    {
        $m_id = $this->getUserId();
        $member = Member::find($m_id);
        if (!$member) {
            api_error(__('api.invalid_token'));
        }
        return [
            'id' => $member['id'],
            'username' => $member['username'],
            'nickname' => $member['nickname'],
            'headimg' => $member['headimg'],
            'full_name' => $member['full_name'],
            'group_id' => $member['group_id'],
        ];
    }

}
