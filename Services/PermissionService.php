<?php

namespace Modules\Permission\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Permission\Entities\Permission;
use Modules\Permission\Entities\Role;
use Modules\Permission\Enums\Scope;
use Modules\Starter\Services\BaseService;
use Spatie\Permission\PermissionRegistrar;

class PermissionService extends BaseService
{
	/**
	 * 同步角色权限
	 * @param Role $role
	 * @param array $permissions
	 * @return array
	 */
	public function syncRolePermissions(Role $role, array $permissions): array
	{

		//由于前端在未选中一个页面下所有权限时，该页面的权限不会被标记为选中，所以需要手动补全
		$total_permissions = config('module.Permission.permissions');

		$page_permission_map = [];

		foreach ($total_permissions as $permission => $child) {
			if (!is_array($child)) {
				continue;
			}

			if (isset($child['children']) && is_array($child['children'])) {
				foreach ($child['children'] as $action => $name) {
					$page_permission_map[$action] = $permission;
				}
			}
		}

		$permissions = collect($permissions)->filter(function ($permission) {
			return Str::startsWith($permission, 'page.') || Str::startsWith($permission, 'api.') || Str::startsWith($permission, 'export.')
				|| Str::startsWith($permission, 'print.');
		});

		foreach ($permissions as $permission) {
			if (isset($page_permission_map[$permission])) {
				if (!$permissions->contains($page_permission_map[$permission])) {
					$permissions->push($page_permission_map[$permission]);
				}
			}
		}

		$role->syncPermissions($permissions->toArray());

		return [true, null];
	}


	/**
	 * 同步用户权限
	 * @param User $user
	 * @param array $permissions
	 * @return array
	 */
	public function syncUserPermissions(User $user, array $permissions): array
	{

		//由于前端在未选中一个页面下所有权限时，该页面的权限不会被标记为选中，所以需要手动补全
		$total_permissions = config('module.Permission.permissions');

		$page_permission_map = [];

		foreach ($total_permissions as $permission => $child) {
			if (!is_array($child)) {
				continue;
			}

			if (isset($child['children']) && is_array($child['children'])) {
				foreach ($child['children'] as $action => $name) {
					$page_permission_map[$action] = $permission;
				}
			}
		}

		$permissions = collect($permissions)->filter(function ($permission) {
			return Str::startsWith($permission, 'page.') || Str::startsWith($permission, 'api.') || Str::startsWith($permission, 'export.')
				|| Str::startsWith($permission, 'print.');
		});

		foreach ($permissions as $permission) {
			if (isset($page_permission_map[$permission])) {
				if (!$permissions->contains($page_permission_map[$permission])) {
					$permissions->push($page_permission_map[$permission]);
				}
			}
		}

		$user->syncPermissions($permissions->toArray());

		return [true, null];
	}


	/**
	 * 获取所有权限
	 * @return Collection
	 */
	public function getAllPermissions(): Collection
	{
		return app()->make(PermissionRegistrar::class)->getPermissions();
	}


	/**
	 * 获取当前用户权限
	 * @return array
	 */
	public function getCurrentUserPermissions(): array
	{
		$user = auth()->user();

		if (!$user) {
			return [];
		}

		//超管返回全部权限
		if ($user->isSuperAdmin()) {
			return Permission::get(['name'])->pluck('name')->toArray();
		}

		//如果有自定义权限，优先使用自定义权限
		if ($user->permissions()->count()) {
			return $user->permissions()->pluck('name')->toArray();
		}
		//否则使用角色权限
		return $user->getAllPermissions()->pluck('name')->toArray();
	}

	/**
	 * 检测用户是否有权限
	 * @param $permission
	 * @return bool
	 */
	public function can($permission): bool
	{
		//这个 Key 用于记录用户的操作权限
		$user_permission_key = 'user_permission_cache';

		//这个 Key 用于记录全部的操作权限
		$total_permission_key = 'total_permission_cache';

		$user = auth()->user();

		if (!$user) {
			return false;
		}

		if ($user->isSuperAdmin()) {
			return true;
		}

		if (!$total_permissions = session($total_permission_key, false)) {
			$total_permissions = $this->getAllPermissions()->pluck('name')->toArray();
			session([$total_permission_key => $total_permissions]);
		}

		//如果不在权限列表中，表示该 permission 无需校验，直接返回 true
		if (!in_array($permission, $total_permissions)) {
			return true;
		}

		if (!$permissions = session($user_permission_key, false)) {
			//如果有自定义权限，优先使用自定义权限
			if ($user->permissions()->count()) {
				$permissions = $user->permissions()->pluck('name')->toArray();
			} else {
				//否则使用角色权限
				$permissions = $user->getAllPermissions()->pluck('name')->toArray();
			}
			session([$user_permission_key => $permissions]);
		}

		$permissions = collect($permissions);

		return $permissions->contains($permission);

	}


	/**
	 * 根据数据范围配置生成前端选项
	 * @param array $scope_config 数据范围配置
	 * @param int $scope_limit 当前用户的数据范围
	 * @return array
	 */
	public function getScopeOptionsViaConfig(array $scope_config, int $scope_limit): array
	{

		// Model 就按典型的部门进行处理
		$is_model = (isset($scope_config['type']) && $scope_config['type'] == 'model') || empty($scope_config); //empty 是默认
		$with_custom = $scope_config['withCustom'] ?? false;

		$scope_options = [
			["label" => '不可见', "value" => Scope::NONE->value],
		];

		if ($is_model) {
			$scope_options = array_merge($scope_options, [
				["label" => '本人数据', "value" => Scope::SELF->value],
				["label" => '本部门数据', "value" => Scope::DEPARTMENT->value],
				["label" => '本部门及下属部门数据', "value" => Scope::DEPARTMENT_AND_SUBORDINATE->value],
			]);
		}

		$scope_options = array_merge($scope_options, [
			["label" => '全部数据', "value" => Scope::ALL->value],
		]);

		if ($with_custom) {

			$option = ["label" => '自定义', "value" => Scope::CUSTOM->value, 'customOptions' => []];


			//自定义选项
			if (isset($scope_config['customOptions'])) {

				$conditions = [];

				foreach ($scope_config['customOptions'] as $option_config) {
					$prop_options = isset($option_config['options']) ? $option_config['options']($conditions) : [];
					//再将这次的条件加入到下一个查询的条件中

					$option['customOptions'][] = [
						"label" => $option_config['displayName'],
						"field" => $option_config['field'],
						"propOptions" => $prop_options
					];

					$conditions[$option_config['field']] = array_column($prop_options, 'value');
				}
			}

			$scope_options = array_merge($scope_options, [
				$option
			]);
		}

		//根据 $scope 返回该选项以上的所有可选项
		return collect($scope_options)->filter(function ($item) use ($scope_limit) {
			return $item['value'] <= $scope_limit;
		})->values()->toArray();
	}
}
