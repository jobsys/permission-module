<?php

namespace Modules\Permission\Entities\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;
use Modules\Permission\Enums\Scope as ScopeEnum;


class AuthorisationScope implements Scope
{

    /**
     * All of the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected array $extensions = ['Authorise'];

    /**
     * @param Builder $builder
     * @param Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
    }


    /**
     * Extend the query builder with the needed functions.
     *
     * @param Builder $builder
     * @return void
     */
    public function extend(Builder $builder): void
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }


    /**
     * Add the restore extension to the builder.
     *
     * @param Builder $builder
     * @return void
     */
    protected function addAuthorise(Builder $builder): void
    {
        $builder->macro('authorise',

            function (Builder $builder, $authorise = null) {

                $scope = session('data_scopes_cache');
                $scope_content = session('data_scope_content_cache');
                $resources = collect(config('module.Permission.data_scope.resources'));

                $model = $builder->getModel();
                //如果定义了多对多关系，那就使用多对多关系来进行数据范围的限定
                $many_2_many = method_exists($model, 'departments');

                $resource = $resources->where('model', get_class($model))->first();
                $department_id = config('module.Permission.data_scope.department_key', 'department_id');
                if ($resource['name'] === 'department') {
                    $department_id = 'id';
                }

                $creator_id = config('module.Permission.data_scope.creator_key', 'creator_id');

                if (!$resource) {
                    $scope = $scope['default'];
                } else {
                    $scope = $scope[$resource['name']] ?? $scope['default'];
                }

                if ($scope == ScopeEnum::ALL->value) {
                    return $builder;
                } else if ($scope == ScopeEnum::DEPARTMENT_AND_SUBORDINATE->value) {
                    if ($many_2_many) {
                        return $builder->whereHas('departments', function ($query) use ($scope_content) {
                            $query->whereIn('id', $scope_content['department_nested']);
                        });
                    }
                    return $builder->whereIn($department_id, $scope_content['department_nested']);
                } else if ($scope == ScopeEnum::DEPARTMENT->value) {
                    if ($many_2_many) {
                        return $builder->whereHas('departments', function ($query) use ($scope_content) {
                            $query->whereIn('id', $scope_content['departments']);
                        });
                    }
                    return $builder->whereIn($department_id, $scope_content['departments']);
                } else if ($scope == ScopeEnum::SELF->value) {
                    return $builder->where($creator_id, $scope_content['user_id']);
                } else if ($scope == ScopeEnum::NONE->value) {
                    return $builder->where(DB::raw(1), '!=', DB::raw(1));
                }
                throw new \Exception('Scope type is not defined!');
            });
    }
}
