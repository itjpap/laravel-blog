<?php


namespace App\Api\Resources;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;

class CommentResource extends Resource
{
    protected static $availableIncludes = [
        'user',
        'parent.user',
        'children.user',
        'children.parent.user',
        'commentable'
    ];

    public function toArray($request)
    {
        if ($this->resource->relationLoaded('upvoters')) {
           $this->resource->append(['has_up_voted']);
        }

        if ($this->resource->relationLoaded('downvotes')) {
            $this->resource->append(['has_down_voted']);
        }

        if (
            $this->resource->relationLoaded('parent') &&
            $this->resource->parent &&
            ($request->routeIs('user.comments') || !!$this->resource->parent->root_id)
        ) {
            $this->resource->content->combine_markdown = sprintf(
              '%s //[@%s](%s)：%s',
              $this->resource->content->markdown,
              $this->resource->parent->user->username,
              $this->resource->parent->user->url,
              $this->resource->parent->content->markdown
            );
        }

        $data = parent::toArray($request);

        return array_merge($data,[
            'content' => new ContentResource($this->resource->content),
            'user' => new UserResource($this->whenLoaded('user')),
            'commentable' => new ArticleResource($this->whenLoaded('commentable')),
            'parent' => new CommentResource($this->whenLoaded('parent')),  // 对于性能的考虑 在响应请求的时候当parent关系加载了才返回资源
            'children' => CommentResource::collection($this->whenLoaded('children')),
            'upvoters' => $this->when(false,null),
            'downvoters' => $this->when(false,null),
        ]);

    }

    public static function childrenQuery(HasMany $builder)
    {
        $subBuilder = clone $builder;

        $order = 'heat desc, id asc';
        if ($id = request('top_comment')) {
            $order = "id = $id desc, " .$order;
        }
        $subSql = $subBuilder
            ->selectRaw("*, row_number() over(partition by root_id order by $order) as`rank`")
            ->toSql();

        $databaseQuery = $builder->getQuery();
        $databaseQuery->wheres = [];
        $databaseQuery->columns = ['*'];
        $databaseQuery->orders = [];

        $builder->fromSub($subSql,'new_comments')->withoutGlobalScopes()->where('new_comments.rank','<=',3);

        $builder->where(Auth::id(),function (Builder $builder,$id) {
           $builder->with(Auth::id(),function (Builder $builder,$id){
             $builder->with([
                 'upvoters' => function(MorphToMany $builder) use ($id){
                    $builder->where('user_id',$id);
                 },
                 'downvotes' => function(MorphToMany $builder) use ($id) {
                    $builder->where('user_id',$id);
                 },
             ]);
           });
        });
    }

}
