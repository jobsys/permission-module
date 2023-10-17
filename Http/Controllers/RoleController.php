<?php


namespace Modules\Permission\Http\Controllers;

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
        $super_role = config('conf.super_role', 'super-admin');
        return Inertia::render('PageRole@Permission', ['superRole' => $super_role]);
    }

    public function items(Request $request)
    {
        $items = Role::all();
        log_access('查看角色列表');
        return $this->json($items);
    }

    public function item(Request $request, $id)
    {
        $item = Role::where('id', $id)->first();
        log_access('查看角色信息', $id);
        return $this->json($item, $item ? State::SUCCESS : State::FAIL);
    }

    public function edit(Request $request)
    {

        list($input, $error) = land_form_validate(
            $request->only(['id', 'name', 'display_name', 'description', 'is_active']),
            ['name' => 'bail|required|string', 'display_name' => 'bail|required|string'],
            ['display_name' => '角色名称', 'name' => '角色标识'],
        );

        if ($error) {
            return $this->message($error);
        }


        $unique = land_is_model_unique($input, Role::class, 'displayName');

        if (!$unique) {
            return $this->message('同名角色已经存在');
        }

        $unique = land_is_model_unique($input, Role::class, 'name');

        if (!$unique) {
            return $this->message('同名角色标识已经存在');
        }


        $role = Role::updateOrCreate(['id' => $input['id'] ?? 0], $input);

        if (!isset($input['id']) || !$input['id']) {
            $role->initDataScope();
        }

        log_access(isset($input['id']) && $input['id'] ? '修改角色信息' : '添加角色信息', $role->id);

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

        log_access('删除角色信息', $id);

        return $this->json();
    }

    public function permissionItems(Request $request)
    {

        $id = $request->input('id');

        $role = Role::find($id);

        if (!$role) {
            return $this->message('角色信息不存在');
        }

        //最大权限集为当前用户的所有权限
        if ($this->login_user->hasRole(config('conf.super_role', 'super-admin'))) {
            $permissions = Permission::orderBy('sort_order', 'DESC')->get()->pluck('name');
        } else {
            $permissions = $this->login_user->getAllPermissions()->pluck('name');
        }


        //先拿出目前的菜单项， 菜单项中的一级即为 Folder， 二级为 Page
        //菜单可能会根据不同的项目进行配置，所以这里得进行动态处理
        //如果 Folder 下面的 Page 都没有权限，那么 Folder 也不需要显示
        //如果 Page 下面的 Action 都没有权限，那么 Page 也不需要显示
        //所以这里需要再根据 module.Permission.permissions 拿出 Page 和 Action 的权限，然后进行判断

        //拿出所有的菜单项
        $menus = land_config('menus');

        //拿出所有的权限项
        $all_permissions = config('module.Permission.permissions');


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


        //角色已有权限集
        $role_permissions = $role->permissions->pluck('name');

        //移动一下 Page 权限，因为前端的树形插件如果选择了 Page， 则 Action 会自动选中，所以如果有 Action 的 Page 则由前端自动选中
        $role_permissions = array_values($role_permissions->filter(function ($item) use ($all_permissions) {
            if (isset($all_permissions[$item]['children']) && !empty($all_permissions[$item]['children'])) {
                return false;
            }
            return true;
        })->toArray());

        log_access('获取角色权限信息', $id);

        return $this->json(compact('menus', 'role_permissions'));
    }

    public function permissionEdit(Request $request, PermissionService $service)
    {
        list($input, $error) = land_form_validate(
            $request->only(['id', 'permissions']),
            [
                'id' => 'bail|required|numeric',
                'permissions' => 'bail|required|array',
            ],
            ['id' => '角色ID', 'permission_ids' => '操作权限列表'],
        );

        if ($error) {
            return $this->message($error);
        }

        $role = Role::where('id', $input['id'])->first();

        if (!$role) {
            return $this->message('角色信息不存在');
        }


        list($result, $error) = $service->syncRolePermissions($role, $input['permissions']);

        log_access('编辑角色权限信息', $role->id);

        return $this->json(null, $result ? State::SUCCESS : State::FAIL);
    }

    public function dataScopeItems(Request $request)
    {

        $id = $request->input('id');

        $role = Role::find($id);

        if (!$role) {
            return $this->message('角色信息不存在');
        }


        //所有的数据
        $total_resources = collect(config('module.Permission.data_scope.resources'));

        //当前角色的数据权限
        $existing_scope = $role->data_scopes()->first()->scope;

        //以当前角色的数据权限为上限准备可选项
        if ($this->login_user->hasRole(config('conf.super_role', 'super-admin'))) {
            $scopes = $total_resources->map(function ($item) {
                $item['options'] = $this->getScopeOptions(Scope::ALL->value);
                return $item;
            })->prepend(['displayName' => '默认', 'name' => 'default', 'options' => $this->getScopeOptions(Scope::ALL->value)]);
        } else {
            $default_scope = $existing_scope['default'] ?? Scope::DEPARTMENT_AND_SUBORDINATE->value;
            $scopes = $total_resources->map(function ($item) use ($existing_scope, $default_scope) {
                if (isset($existing_scope[$item['name']])) {
                    $option_scope = $existing_scope[$item['name']];
                } else {
                    $option_scope = $default_scope;
                }
                $item['options'] = $this->getScopeOptions($option_scope);
                return $item;
            })->prepend(['displayName' => '默认', 'name' => 'default', 'options' => $this->getScopeOptions($default_scope)]);
        }


        $role_scopes = [];

        foreach ($existing_scope as $key => $value) {
            $role_scopes[] = [
                'displayName' => $total_resources->where('name', $key)->first()['displayName'] ?? '默认',
                'name' => $key,
                'value' => $value,
                'options' => $scopes->where('name', $key)->first()['options'],
            ];
        }

        log_access('获取角色数据权限', $id);

        return $this->json(compact('scopes', 'role_scopes'));
    }

    public function dataScopeEdit(Request $request)
    {
        list($input, $error) = land_form_validate(
            $request->only(['id', 'scope']),
            [
                'id' => 'bail|required|numeric',
                'scope' => 'bail|required|array',
            ],
            ['id' => '角色ID', 'scope' => '数据范围'],
        );

        if ($error) {
            return $this->message($error);
        }

        if (!isset($input['scope']['default'])) {
            return $this->message('请添加默认数据范围');
        }


        $role = Role::where('id', $input['id'])->first();

        if (!$role) {
            return $this->message('角色信息不存在');
        }

        if (!$role->data_scopes()->count()) {
            $role->data_scopes()->create(['scope' => $input['scope']]);
        } else {
            $role->data_scopes()->update(['scope' => $input['scope']]);
        }
        log_access('编辑角色数据权限', $role->id);

        return $this->json();

    }


    private function getScopeOptions($scope)
    {
        $scope_options = [
            ["label" => '不可见', "value" => Scope::NONE->value],
            ["label" => '本人数据', "value" => Scope::SELF->value],
            ["label" => '本部门数据', "value" => Scope::DEPARTMENT->value],
            ["label" => '本部门及下属部门数据', "value" => Scope::DEPARTMENT_AND_SUBORDINATE->value],
            ["label" => '全部数据', "value" => Scope::ALL->value],
        ];

        //根据 $scope 返回该选项以上的所有可选项
        return collect($scope_options)->filter(function ($item) use ($scope) {
            return $item['value'] <= $scope;
        })->values()->toArray();
    }
}
