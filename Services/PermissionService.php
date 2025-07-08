<?php

namespace Modules\Permission\Services;

use App\Boot\BootPermission;
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
	 * 找出页面下的所有 Action，由于页面是多套嵌套的，所以需要递归查找
	 * @param array $permissions
	 * @param string $page_key
	 * @return array|null
	 */
	public function findPageActions(array $permissions, string $page_key): ?array
	{
		foreach ($permissions as $key => $value) {
			if ($key === $page_key) {
				return $value['children'] ?? null;
			}

			if (is_array($value) && isset($value['children'])) {
				$found = $this->findPageActions($value['children'], $page_key);
				if (!is_null($found)) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * 找出权限的路径
	 * @param array $permissions
	 * @param string $target_key
	 * @param array $path
	 * @return array|null
	 */
	function findPermissionPath(array $permissions, string $target_key, array $path = []): ?array
	{
		foreach ($permissions as $key => $value) {
			$current_path = [...$path, $key];

			// 直接命中目标
			if ($key === $target_key) {
				return $current_path;
			}

			// 有子权限，递归查找
			if (is_array($value) && isset($value['children'])) {
				$found = $this->findPermissionPath($value['children'], $target_key, $current_path);
				if (!is_null($found)) {
					return $found;
				}
			}
		}

		return null; // 未找到
	}


	/**
	 * 同步角色权限
	 * @param Role $role
	 * @param array $permissions
	 * @return array
	 */
	public function syncRolePermissions(Role $role, array $permissions): array
	{

		// 过滤出以 'page.' 或 'api.' 或 'export.' 或 'print.' 开头的权限
		$permissions = collect($permissions)->filter(function ($permission) {
			return Str::startsWith($permission, 'page.') || Str::startsWith($permission, 'api.') || Str::startsWith($permission, 'export.')
				|| Str::startsWith($permission, 'print.');
		});

		//由于前端在未选中一个页面下所有权限时，该页面的权限不会被标记为选中，所以需要手动补全
		//不需要了，在需要返回用户全部权限的时候再补全，这样前端的树形结构就会自动选中
		$total_permissions = BootPermission::permissions();

		$role_permissions = collect();

		foreach ($permissions as $permission) {
			$permission_path = $this->findPermissionPath($total_permissions, $permission);
			if ($permission_path) {
				$role_permissions = $role_permissions->concat($permission_path);
			}
		}

		$role->syncPermissions($role_permissions->unique()->toArray());


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

		// 过滤出以 'page.' 或 'api.' 或 'export.' 或 'print.' 开头的权限
		$permissions = collect($permissions)->filter(function ($permission) {
			return Str::startsWith($permission, 'page.') || Str::startsWith($permission, 'api.') || Str::startsWith($permission, 'export.')
				|| Str::startsWith($permission, 'print.');
		});


		//由于前端在未选中一个页面下所有权限时，该页面的权限不会被标记为选中，所以需要手动补全
		$total_permissions = BootPermission::permissions();

		$user_permissions = collect();

		foreach ($permissions as $permission) {
			$permission_path = $this->findPermissionPath($total_permissions, $permission);
			if ($permission_path) {
				$user_permissions = $user_permissions->concat($permission_path);
			}
		}
		$user->syncPermissions($user_permissions->unique()->toArray());


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
			return $user->permissions()->pluck('name')->values()->toArray();
		}
		//否则使用角色权限
		return $user->getAllPermissions()->pluck('name')->values()->toArray();
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

				foreach ($scope_config['customOptions'] as $option_config) {
					$option['customOptions'][] = [
						"label" => $option_config['displayName'],
						"type" => $option_config['type'] ?? 'select',
						"field" => $option_config['field'],
						"propOptions" => !empty($option_config['options']) ? $option_config['options']() : [],
						"remoteOptions" => $option_config['remoteOptions'] ?? []
					];
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


	public function tidyPermissionTreeViaPermissions($menus, $user_permissions, $all_permissions): array
	{
		foreach ($menus as $index => $menu) {
			if (isset($menu['children'])) {

				$sub_menus = $this->tidyPermissionTreeViaPermissions($menu['children'], $user_permissions, $all_permissions);

				if (count($sub_menus)) {
					$menus[$index]['children'] = array_values($sub_menus);
					if (!isset($menus[$index]['key'])) {
						$menus[$index]['key'] = $menus[$index]['page'];
					}
				} else {
					unset($menus[$index]);
				}
			} else if (!isset($menu['page'])) {
				//如果没有 children 也没有 page， 那就直接跳过
				unset($menus[$index]);
			} else {
				//如果是 Page，那么就需要判断最大权限集中是否有该页面的权限，没有权限直接从 Menu 中移除
				if (!$user_permissions->contains($menu['page'])) {
					unset($menus[$index]);
				} else {
					$available_actions = $this->findPageActions($all_permissions, $menu['page']);
					if ($available_actions) {
						foreach ($available_actions as $action => $name) {
							if ($user_permissions->contains($action)) {
								$menu['children'][] = [
									'displayName' => $name,
									'key' => $action,
								];
							}
						}
					}
					$menu['key'] = $menu['page'];
					$menus[$index] = $menu;
				}
			}
		}
		return $menus;
	}
}
