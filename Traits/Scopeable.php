<?php


namespace Modules\Permission\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Permission\Entities\DataScope;
use Modules\Permission\Enums\Scope;

trait Scopeable
{

    public function data_scopes(): MorphMany
    {
        return $this->morphMany(DataScope::class, 'scopeable');
    }

    public function initDataScope(): void
    {
        $this->data_scopes()->create([
            'scope' => ['default' => Scope::DEPARTMENT_AND_SUBORDINATE]
        ]);
    }
}
