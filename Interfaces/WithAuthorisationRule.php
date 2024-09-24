<?php

namespace Modules\Permission\Interfaces;



interface WithAuthorisationRule
{
	/**
	 * 根据规则自定义权限
	 * @return array 数组第一项为 dataScope 中定义的规则名称，第二项为规则的条件
	 */
	public function getAuthorisationRule(): array;
}
