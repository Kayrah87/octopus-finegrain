<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $meter_point_id
 * @property string $serial
 */
final class MeterRecord extends Model
{
    public $timestamps = false;

    protected $table = 'meters';

    protected $fillable = ['meter_point_id', 'serial'];
}
