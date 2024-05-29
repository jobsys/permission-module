<?php

use App\Boot\BootPermission;
use Illuminate\Support\Collection;

if (!function_exists('permission_get_page_permissions')) {

    /**
     * 获取页面的权限
     * @param string $page 页面名称
     * @param Collection $permissions 用户的权限集合
     * @return array
     */
    function permission_get_page_permissions(string $page, Collection $permissions): array
    {

        $route_prefix = config('permission.route_prefix', '');

        $page_pieces = explode('.', $page);
        if ($page_pieces[1] !== $route_prefix) {
            //add the route prefix to the page and implode it back
            array_splice($page_pieces, 1, 0, $route_prefix);
        }

        $page = implode('.', $page_pieces);

        $predefined_permissions = BootPermission::permissions()[$page]['children'] ?? [];

        foreach ($predefined_permissions as $permission => $name) {
            if ($permissions->where('name', $permission)->count() === 0) {
                unset($predefined_permissions[$permission]);
            }
        }

        return array_keys($predefined_permissions);
    }
}
