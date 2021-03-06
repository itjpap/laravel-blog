<?php


namespace App\Api\Resources;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;


/**
 * 统一资源返回处理类
 * Class Resource
 * @package App\Api\Resources
 * Created by lujianjin
 * DataTime: 2021/1/12 15:30
 */
class Resource extends JsonResource
{

    protected static $availableIncludes = [];

    private static $relationLoaded = false;

    public $preserveKeys = true;

    public function __construct($resource)
    {
        parent::__construct($resource);

        if (self::$relationLoaded && $resource instanceof Model) {
            // 自动加载关联表关系
            $resource->loadMissing(self::getRequestIncludes());
            self::$relationLoaded = true;
        }
    }

    public static function collection($resource)
    {
        if (!self::$relationLoaded) {
            if (is_object($resource)) {
                try {
                    $resource->loadMissing(self::getRequestIncludes());

                } catch (Throwable $exception) {
                    Log::channel('single')->error('获得关联关系失败');
                }
            }

            self::$relationLoaded = true;
        }
        return parent::collection($resource); // TODO: Change the autogenerated stub
    }


    /**
     * Notes:通过请求中的include参数 获得表关联关系
     *
     * @author: lujianjin
     * datetime: 2021/1/12 20:48
     * @return array
     */
    public static function getRequestIncludes()
    {
        $includes = array_intersect(parse_includes(static::$availableIncludes), parse_includes());
        $relations = [];

        foreach ($includes as $relation) {
            $method = Str::camel(str_replace('.','_',$relation)).'Query';  //字符串转化驼峰形式
            if (method_exists(static::class,$method)) {
                $relation[$relation] = function ($query) use ($method) {
                    forward_static_call([static::class,$method],$query);
                };
                continue;
            }
            $relations[] = $relation;
        }

        return $relations;
    }


}
