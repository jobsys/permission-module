<?php

namespace Modules\Permission\Entities;

use Modules\Permission\Traits\HasDataScopes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Role extends \Spatie\Permission\Models\Role
{
	use HasDataScopes, LogsActivity;

	protected string $model_name = "角色";

	protected static function booted(): void
	{
		static::created(function (Role $role) {
			$role->initDataScope();
			$dashboard_permission = Permission::where('name', 'page.manager.dashboard')->first();
			if ($dashboard_permission) {
				$role->givePermissionTo($dashboard_permission);
			}
		});
	}

	protected $guarded = [];

	protected $casts = [
		'is_active' => 'boolean',
		'is_inherent' => 'boolean'
	];

	public $appends = [
		'model_type'
	];

	public function getModelTypeAttribute(): string
	{
		return 'role';
	}

	public static function getModelName(): string
	{
		return (new static)->model_name;
	}

	public function getActivitylogOptions(): LogOptions
	{
		return LogOptions::defaults()->setDescriptionForEvent(function (string $event_name) {
			return match ($event_name) {
				'created' => '创建角色',
				'updated' => '更新角色',
				'deleted' => '删除角色',
				default => ''
			};
		});
	}
}
