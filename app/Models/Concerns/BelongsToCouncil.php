<?php

namespace App\Models\Concerns;

use App\Support\Council;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes a RAMM-derived model to the current council: queries only see that
 * council's rows, new rows are stamped with it, and saves/deletes by key are
 * pinned to it (road_id / code are only unique within a council).
 *
 * Bulk Model::insert() bypasses this — callers must set 'council' themselves.
 */
trait BelongsToCouncil
{
    public static function bootBelongsToCouncil(): void
    {
        static::addGlobalScope('council', function (Builder $q) {
            $q->where($q->getModel()->getTable() . '.council', Council::current());
        });

        static::creating(function ($model) {
            if (empty($model->council)) {
                $model->council = Council::current();
            }
        });
    }

    protected function setKeysForSaveQuery($query)
    {
        return parent::setKeysForSaveQuery($query)
            ->where('council', $this->getOriginal('council') ?? $this->council);
    }

    protected function setKeysForSelectQuery($query)
    {
        return parent::setKeysForSelectQuery($query)
            ->where('council', $this->getOriginal('council') ?? $this->council);
    }
}
