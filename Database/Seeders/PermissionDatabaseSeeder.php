<?php

namespace Modules\Permission\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Permission\Entities\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionDatabaseSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		Model::unguard();

		app()[PermissionRegistrar::class]->forgetCachedPermissions();

		$role_super_admin = Role::where('name', config('conf.role_super'))->first();

		if (!$role_super_admin) {
			$role_super_admin = Role::create(['name' => config('conf.role_super'), 'guard_name' => 'web', 'is_active' => 1, 'is_inherent' => 1]);
		}

		$user = User::where('name', config('conf.super_admin_name', 'root'))->first();
		if (!$user) {
			$user = User::create(['name' => config('conf.super_admin_name', 'root'), 'is_active' => 1]);
		}

		$user->assignRole($role_super_admin);
	}
}
