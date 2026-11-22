<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $fuel
 * @property string $direction
 * @property string $mpxn
 */
final class MeterPointRecord extends Model
{
    protected $table = 'meter_points';

    protected $fillable = ['fuel', 'direction', 'mpxn'];

    /** @return HasMany<MeterRecord, $this> */
    public function meters(): HasMany
    {
        return $this->hasMany(MeterRecord::class, 'meter_point_id');
    }

    /** @return HasMany<AgreementRecord, $this> */
    public function agreements(): HasMany
    {
        return $this->hasMany(AgreementRecord::class, 'meter_point_id')->orderBy('valid_from');
    }
}
