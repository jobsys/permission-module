<?php

namespace Modules\Permission\Interfaces;

interface CustomAuthorisation
{
	/**
	 * @return array 数组第一项为 module.php 中定义的规则名称，第二项为规则的条件
	 */
	public function onCustomAuthorisationRule(): array;
}
