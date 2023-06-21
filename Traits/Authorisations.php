<?php

namespace Modules\Permission\Traits;

use Modules\Permission\Entities\Scope\AuthorisationScope;

/**
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder authorise(array $departments = [])
 */
trait Authorisations
{
    public static function bootAuthorisations()
    {
        static::addGlobalScope(new AuthorisationScope());
    }
}
