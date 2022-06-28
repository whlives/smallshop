<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/19
 * Time: 1:41 PM
 */

namespace App\Http\Controllers\V1\Member;

use App\Http\Controllers\V1\BaseController;
use App\Models\Goods\Comment;
use App\Models\Goods\CommentUrl;
use App\Models\Goods\Goods;
use App\Services\GoodsService;
use Illuminate\Http\Request;

class CommentController extends BaseController
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
        $where = [
            'm_id' => $this->m_id,
            'status' => Comment::STATUS_ON
        ];
        $query = Comment::select('id', 'goods_id', 'spec_value', 'level', 'content', 'created_at')
            ->where($where);
        $total = $query->count();//总条数
        $res_list = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        if ($res_list->isEmpty()) {
            api_error(__('api.content_is_empty'));
        }
        $res_list = $res_list->toArray();
        $goods_ids = array_column($res_list, 'goods_id');
        $comment_ids = array_column($res_list, 'id');
        //查询商品信息
        $goods_res = Goods::select('id', 'title', 'image')->whereIn('id', array_unique($goods_ids))->get();
        if (!$goods_res->isEmpty()) {
            $goods_res = array_column($goods_res->toArray(), null, 'id');
        }
        //查询图片、视频信息
        $image_url = $video_url = [];
        $url_res = CommentUrl::select('comment_id', 'url', 'type')->whereIn('comment_id', array_unique($comment_ids))->get();
        if (!$url_res->isEmpty()) {
            foreach ($url_res as $value) {
                if ($value['type'] == CommentUrl::TYPE_IMAGE) {
                    $image_url[$value['comment_id']][] = ['url' => $value['url']];
                } elseif ($value['type'] == CommentUrl::TYPE_VIDEO) {
                    $video_url[$value['comment_id']][] = ['url' => $value['url']];
                }
            }
        }
        $data_list = [];
        foreach ($res_list as $value) {
            $_item = $value;
            $_item['spec_value'] = GoodsService::formatSpecValue($value['spec_value']);
            $_item['goods_title'] = $goods_res[$value['goods_id']]['title'] ?? '';
            $_item['goods_image'] = $goods_res[$value['goods_id']]['image'] ?? '';
            $_item['image'] = $image_url[$value['id']] ?? [];
            $_item['video'] = $video_url[$value['id']] ?? [];
            $data_list[] = $_item;
        }
        $return = [
            'lists' => $data_list,
            'total' => $total,
        ];
        return $this->success($return);
    }

}