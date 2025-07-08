<?php

namespace Modules\Permission\Entities;


class Permission extends \Spatie\Permission\Models\Permission
{
	protected string $model_name = "操作权限";

	protected $casts = [
		'is_active' => 'boolean',
	];

	public static function getModelName(): string
	{
		return (new static)->model_name;
	}
}
