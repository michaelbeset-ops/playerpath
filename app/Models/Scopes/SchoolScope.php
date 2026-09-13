<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtert elke query op de actieve school.
 *
 * Bewust fail-closed: is er geen actieve school, dan levert de query niets op.
 * Dat is veiliger dan "alles teruggeven" - een vergeten middleware mag nooit
 * per ongeluk de data van alle scholen openzetten.
 *
 * De enige uitzondering die over scholen heen kijkt is de beheeromgeving van
 * het platform, en die wordt hieronder expliciet genoemd. Zo staat op één plek
 * wanneer er níét gefilterd wordt.
 */
class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(Tenancy::class);

        // Twee redenen om niet te filteren, allebei bewust en allebei hier:
        // een tijdelijke uitzondering rond een blok code, of de beheeromgeving
        // van het platform. Er is geen derde, en er staan nergens losse
        // uitzonderingen in controllers.
        if ($tenancy->isDisabled() || $tenancy->isPlatform()) {
            return;
        }

        $column = $model->qualifyColumn('school_id');

        if (! $tenancy->hasSchool()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($column, $tenancy->id());
    }
}
