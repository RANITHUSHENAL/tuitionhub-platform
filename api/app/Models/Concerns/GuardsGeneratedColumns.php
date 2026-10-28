<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * STORED generated columns are computed by MySQL and must never be written (MySQL error 3105).
 * Saving fails before any query if one is dirty. After an Eloquent insert, call refresh() to read them.
 */
trait GuardsGeneratedColumns
{
    /**
     * @return list<string>
     */
    abstract protected function generatedColumns(): array;

    public static function bootGuardsGeneratedColumns(): void
    {
        static::saving(function (self $model) {
            foreach ($model->generatedColumns() as $column) {
                if ($model->isDirty($column)) {
                    throw new LogicException("Generated column [{$column}] cannot be written.");
                }
            }
        });
    }
}
