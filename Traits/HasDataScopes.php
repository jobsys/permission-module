<?php


namespace Modules\Permission\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Permission\Entities\DataScope;
use Modules\Permission\Enums\Scope;

trait HasDataScopes
{

	public function dataScopes(): MorphMany
	{
		return $this->morphMany(DataScope::class, 'scopeable');
	}


	public function getDataScope()
	{
		$this->loadMissing('dataScopes');

		if ($this->dataScopes()->count()) {
			return $this->dataScopes()->first()->scope;
		}
		return $this->getDataScopeViaRoles();
	}

	public function getDataScopeViaRoles(): array
	{
		$data_scopes = $this->loadMissing('roles', 'roles.dataScopes')
			->roles->pluck('dataScopes')->flatten();

		$scopes = [];

		foreach ($data_scopes as $data_scope) {

			$scope_config = $data_scope->scope;
			if(empty($scope_config)){
				continue;
			}

			foreach ($scope_config as $name => $scope) {
				if (is_array($scope)) { // 自定义
					if (!isset($scopes[$name])) {
						$scopes[$name] = $scope;
					} else {
						$scopes[$name] = array_merge_recursive($scopes[$name], $scope);
					}
				} else {
					//只保留最大的那个值
					if (!isset($scopes[$name]) || $scopes[$name] < $scope) {
						$scopes[$name] = $scope;
					}
				}
			}
		}

		return $scopes;
	}

	public function initDataScope(): void
	{
		$this->dataScopes()->create([
			'scope' => ['default' => Scope::SELF->value]
		]);
	}
}
