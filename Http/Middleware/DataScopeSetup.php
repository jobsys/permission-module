<?php

namespace Modules\Permission\Http\Middleware;


use App\Models\Department;
use Modules\Permission\Entities\Role;
use Modules\Permission\Enums\Scope;

class DataScopeSetup
{
    public function handle($request, \Closure $next, $role_name)
    {
        //这个 Key 用于记录用户的角色的数据范围
        $scope_key = 'data_scopes_cache';

        // 如果使用 department 来作为资源的限定条件，那就在一开始把用户的 department 信息放到 session 中
        // 如果使用其他的资源来作为限定条件，那就在这里把其他的资源信息放到 session 中
        // $scope_content = ['department_nested' => [] //包括子部门 , 'departments' => [] //仅当前部门, 'user_id' => [] //用户id]
        $scope_content_key = 'data_scope_content_cache';

        $existing_scopes = session($scope_key);

        $is_super_admin = $request->user()->isSuperAdmin();

        if (!$existing_scopes) {
            if ($is_super_admin) {
                session([$scope_key => ['default' => Scope::ALL->value]]);
            } else {
                $role = Role::where('name', $role_name)->first();
                $scope = $role->data_scopes()->first();
                session([$scope_key => $scope ? $scope->scope : []]);
            }
        }

        if ($is_super_admin) {
            return $next($request);
        }

        $existing_scopes_content = session($scope_content_key);

        if (!$existing_scopes_content) {
            $user = $request->user();
            // 这里是获取用户的 department 信息
            $department_ids = $user->departments()->pluck('id')->toArray();
            $department_nested = [];

			foreach ($department_ids as $department_id) {
				$nested_department_ids = Department::find($department_id)->descendantsWithSelf()->pluck('id')->toArray();
				$department_nested = array_merge($department_nested, $nested_department_ids);
			}
			session([
				$scope_content_key => [
					'department_nested' => $department_nested,
					'departments' => $department_ids,
					'user_id' => $user->id
				]
			]);
		}

        return $next($request);
    }
}
