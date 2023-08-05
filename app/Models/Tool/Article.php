<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/10
 * Time: 9:45 PM
 */

namespace App\Models\Tool;

use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

/**
 * 文章管理
 */
class Article extends BaseModel
{
    protected $table = 'article';
    protected $guarded = ['id'];
    
    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',

    ];

    /**
     * 获取详情
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function content(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne('App\Models\Tool\ArticleContent');
    }

    /**
     * 保存数据
     * @param int $id
     * @param array $save_data
     * @return bool
     */
    public static function saveData(int $id = 0, array $save_data): bool
    {
        if (!$save_data) return false;
        try {
            DB::transaction(function () use ($id, $save_data) {
                $content = $save_data['content'];
                unset($save_data['content']);
                if ($id) {
                    self::where('id', $id)->update($save_data);
                    ArticleContent::where('article_id', $id)->update(['content' => $content]);
                } else {
                    $result = self::create($save_data);
                    $res_id = $result->id;
                    ArticleContent::create(['article_id' => $res_id, 'content' => $content]);
                }
            });
            $res = true;
        } catch (\Exception $e) {
            $res = false;
        }
        return $res;
    }

    /**
     * 删除数据
     * @param int $id
     * @return bool
     */
    public static function deleteData(array $id = []): bool
    {
        try {
            DB::transaction(function () use ($id) {
                self::whereIn('id', $id)->delete();
                ArticleContent::whereIn('article_id', $id)->delete();
            });
            $res = true;
        } catch (\Exception $e) {
            $res = false;
        }
        return $res;
    }
}
