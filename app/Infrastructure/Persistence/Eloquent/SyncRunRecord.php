<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property bool|null $succeeded
 * @property string|null $summary
 */
final class SyncRunRecord extends Model
{
    public $timestamps = false;

    protected $table = 'sync_runs';

    protected $fillable = ['started_at', 'finished_at', 'succeeded', 'summary'];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'succeeded' => 'boolean',
        ];
    }
}
