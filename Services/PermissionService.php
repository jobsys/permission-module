<?php

namespace Modules\Permission\Services;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Permission\Entities\Role;
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
            return Str::startsWith($permission, 'page.') || Str::startsWith($permission, 'api.') || Str::startsWith($permission, 'export.');
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
     * 获取所有权限
     * @return Collection
     * @throws BindingResolutionException
     */
    public function getAllPermissions(): Collection{
        return app()->make(PermissionRegistrar::class)->getPermissions();
    }
}
