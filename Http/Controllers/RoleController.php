<?php


namespace Modules\Permission\Http\Controllers;

use App\Boot\BootPermission;
use App\Http\Controllers\BaseManagerController;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Permission\Entities\Role;
use Modules\Permission\Enums\Scope;
use Modules\Permission\Services\PermissionService;
use Modules\Starter\Emnus\State;
use Spatie\Permission\Models\Permission;

class RoleController extends BaseManagerController
{
	public function pageRole()
	{
		$super_role = config('conf.role_super');
		return Inertia::render('PageRole@Permission', ['superRole' => $super_role]);
	}

	public function items(Request $request)
	{
		$items = Role::get()->sortBy(function (Role $role) {
			return $role->name === config('conf.role_super') ? 0 : 1;
		})->values();
		return $this->json($items);
	}

	public function item(Request $request, $id)
	{
		$item = Role::where('id', $id)->first();
		log_access('查看角色信息', $item);
		return $this->json($item, $item ? State::SUCCESS : State::FAIL);
	}

	public function edit(Request $request)
	{

		list($input, $error) = land_form_validate(
			$request->only(['id', 'name', 'description', 'is_active']),
			['name' => 'bail|required|string'],
			['name' => '角色名称'],
		);

		if ($error) {
			return $this->message($error);
		}


		$unique = land_is_model_unique($input, Role::class, 'name');

		if (!$unique) {
			return $this->message('同名角色已经存在');
		}


		$role = Role::updateOrCreate(['id' => $input['id'] ?? 0], $input);

		if (!isset($input['id']) || !$input['id']) {
			$role->initDataScope();
		}


		return $this->json($role);
	}

	public function delete(Request $request)
	{
		$id = $request->input('id');

		$role = Role::find($id);


		if ($role) {
			User::all()->each(function ($user) use ($id) {
				$user->roles()->detach($id);
			});
			$role->delete();
		}

		return $this->json();
	}

	public function permissionItems(Request $request)
	{

		// 有两种模式，分别是针对角色和用户
		$mode = $request->input('mode', 'role');
		$id = $request->input('id');

		if ($mode === 'role') {
			$role = Role::find($id);

			if (!$role) {
				return $this->message('角色信息不存在');
			}
		} else {
			$user = User::find($id);

			if (!$user) {
				return $this->message('用户信息不存在');
			}
		}


		//最大权限集为当前登录用户的所有权限
		if ($this->login_user->hasRole(config('conf.role_super'))) {
			$permissions = Permission::orderBy('sort_order', 'DESC')->get()->pluck('name');
		} else {
			//如果有自定义权限，那么就按照自定义权限来
			if ($this->login_user->permissions->count()) {
				$permissions = $this->login_user->permissions()->pluck('name');
			} else {
				//如果没有自定义权限，那么就按照角色的权限来
				$permissions = $this->login_user->getAllPermissions()->pluck('name');
			}
		}


		//先拿出目前的菜单项， 菜单项中的一级即为 Folder， 二级为 Page
		//菜单可能会根据不同的项目进行配置，所以这里得进行动态处理
		//如果 Folder 下面的 Page 都没有权限，那么 Folder 也不需要显示
		//如果 Page 下面的 Action 都没有权限，那么 Page 也不需要显示
		//所以这里需要再根据 module.Permission.permissions 拿出 Page 和 Action 的权限，然后进行判断

		//拿出所有的菜单项
		$menus = land_config('menus');

		//拿出所有的权限项
		$all_permissions = BootPermission::permissions();


		foreach ($menus as $index => &$menu) {
			if (isset($menu['children'])) {
				foreach ($menu['children'] as $sub_index => &$sub_menu) {
					if (!$permissions->contains($sub_menu['page'])) {
						unset($menus[$index]['children'][$sub_index]);
					} else {
						$sub_menu['key'] = $sub_menu['page'];
						$sub_menu['children'] = [];
						//处理 Action
						$available_actions = $all_permissions[$sub_menu['page']]['children'] ?? [];


						foreach ($available_actions as $action => $name) {
							if ($permissions->contains($action)) {
								$sub_menu['children'][] = [
									'displayName' => $name,
									'key' => $action,
								];
							}
						}

						//如果一个 Action 都没有了，那么就直接移除这个 Page
						if (empty($menus[$index]['children'][$sub_index]['children'])) {
							unset($menus[$index]['children'][$sub_index]);
						}
					}
					$menu['children'] = array_values($menu['children']);
				}
				//如果一个 child 都没有了，那么就直接移除这个 Folder
				if (empty($menus[$index]['children'])) {
					unset($menus[$index]);
				}
			} else if (!isset($menu['page'])) {
				//如果没有 children 也没有 page， 那就直接跳过
				unset($menus[$index]);
			} else {
				//如果是 Page，那么就需要判断最大权限集中是否有该页面的权限，没有权限直接从 Menu 中移除
				if (!$permissions->contains($menu['page'])) {
					unset($menus[$index]);
				} else {

					$available_actions = $all_permissions[$menu['page']]['children'] ?? [];

					foreach ($available_actions as $action => $name) {
						if ($permissions->contains($action)) {
							$menu['children'][] = [
								'displayName' => $name,
								'key' => $action,
							];
						}
					}

					$menu['key'] = $menu['page'];
				}
			}
		}

		$menus = array_values($menus);


		$auth_permissions = collect();
		//目标角色/用户已有权限集
		if ($mode === 'user' && isset($user)) {
			//如果该目标用户有自定义权限，那么就按照自定义权限来
			if ($user->permissions->count()) {
				$auth_permissions = $user->permissions->pluck('name');
			} else {
				//如果该目标用户没有自定义权限，那么就按照角色的权限来
				$auth_permissions = $user->getAllPermissions()->pluck('name');
			}
		} else if ($mode === 'role' && isset($role)) {
			$auth_permissions = $role->permissions->pluck('name');
		}

		//移动一下 Page 权限，因为前端的树形插件如果选择了 Page， 则 Action 会自动选中，所以如果有 Action 的 Page 则由前端自动选中
		$auth_permissions = array_values($auth_permissions->filter(function (string $item) use ($all_permissions) {
			if (isset($all_permissions[$item]['children']) && !empty($all_permissions[$item]['children'])) {
				return false;
			}
			return true;
		})->toArray());

		log_access('获取角色/用户权限信息', $role ?? $user);

		return $this->json(compact('menus', 'auth_permissions'));
	}

	public function permissionEdit(Request $request, PermissionService $service)
	{
		$mode = $request->input('mode', 'role');

		list($input, $error) = land_form_validate(
			$request->only(['id', 'permissions']),
			[
				'id' => 'bail|required|numeric',
				'permissions' => 'bail|required|array',
			],
			['id' => $mode === 'role' ? '角色ID' : '用户ID', 'permission_ids' => '操作权限列表'],
		);

		if ($error) {
			return $this->message($error);
		}

		if ($mode === 'role') {
			$role = Role::where('id', $input['id'])->first();

			if (!$role) {
				return $this->message('角色信息不存在');
			}
		} else if ($mode === 'user') {
			$user = User::where('id', $input['id'])->first();

			if (!$user) {
				return $this->message('用户信息不存在');
			}
		}


		if ($mode === 'role' && isset($role)) {
			list($result, $error) = $service->syncRolePermissions($role, $input['permissions']);
			log_access('编辑角色权限信息', $role);
		} else if ($mode === 'user' && isset($user)) {
			list($result, $error) = $service->syncUserPermissions($user, $input['permissions']);
			log_access('编辑权限信息', $user);
		}


		return $this->json(null, isset($result) && $result ? State::SUCCESS : State::FAIL);
	}

	public function dataScopeItems(Request $request, PermissionService $service)
	{

		// 有两种模式，分别是针对角色和用户
		$mode = $request->input('mode', 'role');
		$id = $request->input('id');

		if ($mode === 'role') {
			$role = Role::find($id);

			if (!$role) {
				return $this->message('角色信息不存在');
			}
		} else if ($mode === 'user') {
			$user = User::find($id);

			if (!$user) {
				return $this->message('用户信息不存在');
			}
		}


		//所有的数据
		$total_resources = collect(BootPermission::dataScopes()['resources']);

		$existing_scope = [];

		if ($mode === 'role' && isset($role)) {
			//当前角色的数据权限
			$existing_scope = $role->dataScopes()->first()->scope;
		} else if ($mode === 'user' && isset($user)) {
			//当前用户的数据权限
			$existing_scope = $user->dataScopes()->first()?->scope ?? [];
		}


		//以当前角色的数据权限为上限准备可选项
		if ($this->login_user->hasRole(config('conf.role_super'))) {
			$scopes = $total_resources->map(function ($item) use ($service) {
				$item['options'] = $service->getScopeOptionsViaConfig($item, Scope::ALL->value);
				return $item;
			})->prepend(['displayName' => '默认', 'name' => 'default', 'options' => $service->getScopeOptionsViaConfig([], Scope::ALL->value)]);
		} else {
			$default_scope = $existing_scope['default'] ?? Scope::SELF->value;
			$scopes = $total_resources->map(function ($item) use ($existing_scope, $default_scope, $service) {
				if (isset($existing_scope[$item['name']])) {
					$option_scope = $existing_scope[$item['name']];
				} else {
					$option_scope = $default_scope;
				}
				$item['options'] = $service->getScopeOptionsViaConfig($item, is_array($option_scope) ? -1 : $option_scope);
				return $item;
			})->prepend(['displayName' => '默认', 'name' => 'default', 'options' => $service->getScopeOptionsViaConfig([], $default_scope)]);
		}


		$role_scopes = [];

		foreach ($existing_scope as $key => $value) {
			$role_scopes[] = [
				'displayName' => $total_resources->where('name', $key)->first()['displayName'] ?? '默认',
				'name' => $key,
				'value' => is_array($value) ? -1 : $value,
				'custom' => is_array($value) ? $value : [],
				'options' => $scopes->where('name', $key)->first()['options'],
			];
		}

		log_access('获取角色/用户数据权限', $role ?? $user);

		return $this->json(compact('scopes', 'role_scopes'));
	}

	public function dataScopeEdit(Request $request)
	{
		$mode = $request->input('mode', 'role');

		list($input, $error) = land_form_validate(
			$request->only(['id', 'scope']),
			[
				'id' => 'bail|required|numeric',
				'scope' => 'bail|required|array',
			],
			['id' => $mode === 'role' ? '角色ID' : '用户ID', 'scope' => '数据范围'],
		);

		if ($error) {
			return $this->message($error);
		}
		if (!isset($input['scope']['default'])) {
			return $this->message('请添加默认数据范围');
		}

		foreach ($input['scope'] as $data => $scope) {
			if (is_array($scope)) {
				foreach ($scope as $key => $value) {
					if (empty($value)) {
						unset($input['scope'][$data][$key]);
					}
				}
			}
		}

		if ($mode === 'role') {
			$role = Role::find($input['id']);
			if (!$role) {
				return $this->message('角色信息不存在');
			}
			if (!$role->dataScopes()->count()) {
				$role->dataScopes()->create(['scope' => $input['scope']]);
			} else {
				$role->dataScopes()->update(['scope' => $input['scope']]);
			}
			log_access('编辑角色数据权限', $role);
		} else if ($mode === 'user') {
			$user = User::find($input['id']);

			if (!$user) {
				return $this->message('用户信息不存在');
			}
			if (!$user->dataScopes()->count()) {
				$user->dataScopes()->create(['scope' => $input['scope']]);
			} else {
				$user->dataScopes()->update(['scope' => $input['scope']]);
			}
			log_access('编辑用户数据权限', $user);
		}

		return $this->json();
	}

	public function userPermissionClear(Request $request)
	{
		$id = $request->input('id');

		$user = User::with(['permissions'])->find($id);

		if (!$user) {
			return $this->message('用户信息不存在');
		}

		$user->permissions()->detach();

		log_access('清空用户操作权限', $user);

		return $this->json();
	}

	public function userDataScopeClear(Request $request)
	{
		$id = $request->input('id');

		$user = User::with(['dataScopes'])->find($id);

		if (!$user) {
			return $this->message('用户信息不存在');
		}

		$user->dataScopes()->delete();

		log_access('清空用户数据权限', $user);

		return $this->json();
	}
}
