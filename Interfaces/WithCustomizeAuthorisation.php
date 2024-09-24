<?php

namespace Modules\Permission\Interfaces;

use Illuminate\Database\Eloquent\Builder;

interface WithCustomizeAuthorisation
{

	/**
	 * 完全自定义数据权限
	 */
	public function getCustomAuthorisationRule(Builder $query): Builder;
}
