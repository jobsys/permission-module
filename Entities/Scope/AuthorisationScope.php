<?php

namespace Modules\Permission\Entities\Scope;

use App\Boot\BootPermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;
use Modules\Permission\Enums\Scope as ScopeEnum;
use Modules\Permission\Interfaces\WithAuthorisationRule;
use Modules\Permission\Interfaces\WithCustomizeAuthorisation;


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

			function (Builder $builder) {

				if (auth()->user()?->isSuperAdmin()) {
					return $builder;
				}

				$scope = session('data_scopes_cache');
				$scope_content = session('data_scope_content_cache');
				$resources = collect(BootPermission::dataScopes()['resources']);

				$model = $builder->getModel();

				//完全自定义权重最高
				if ($model instanceof WithCustomizeAuthorisation) {
					return $model->getCustomAuthorisationRule($builder);
				} else if ($model instanceof WithAuthorisationRule) {
					//规则管理
					$authorise_rule = $model->getAuthorisationRule();
					$rule_name = $authorise_rule[0] ?? '';
					$rule_conditions = $authorise_rule[1] ?? [];
					if (!$rule_name) {
						throw new \Exception('Authorise rule for ' . get_class($model) . ' not defined');
					}

					//规则类型的，如果没有定义就没有数据
					$scope = $scope[$rule_name] ?? ScopeEnum::NONE->value;

					if ($scope == ScopeEnum::ALL->value) {
						return $builder;
					} else if ($scope == ScopeEnum::NONE->value) {
						return $builder->where(DB::raw(1), '!=', DB::raw(1));
					} else if (is_array($scope)) {
						//如果 rule 没有设置过滤字段，那就默认为全部字段
						if (empty($rule_conditions)) {
							foreach ($scope as $field => $value) {
								if (!empty($value)) {
									$builder->whereIn($field, $value);
								}
							}
						} else {
							//如果 rule 没有设置了过滤字段，那就只过滤指定的字段
							foreach ($scope as $field => $value) {
								if (in_array($field, $rule_conditions) && !empty($value)) {
									$builder->whereIn($field, $value);
								}
							}
						}
						return $builder;
					}
				} else {
					//默认的部门层级管理
					//如果定义了多对多关系，那就使用多对多关系来进行数据范围的限定
					$many_2_many = method_exists($model, 'departments');

					$resource = $resources->where('type', 'model')->where('model', get_class($model))->first();
					$department_id = BootPermission::dataScopes()['department_key'] ?? 'department_id';
					if ($resource && $resource['name'] === 'department') {
						$department_id = 'id';
					}
					$creator_id = BootPermission::dataScopes()['creator_key'] ?? 'creator_id';

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
					} else if (is_array($scope)) {
						foreach ($scope as $field => $value) {
							$builder->whereIn($field, $value);
						}
						return $builder;
					}
				}

				throw new \Exception('Scope type is not defined!');
			});
	}
}
