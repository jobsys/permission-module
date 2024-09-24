<?php

namespace Modules\Permission\Entities;

use Modules\Permission\Traits\HasDataScopes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Role extends \Spatie\Permission\Models\Role
{
	use HasDataScopes, LogsActivity;

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
