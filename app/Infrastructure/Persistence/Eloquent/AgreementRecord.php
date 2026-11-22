<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $meter_point_id
 * @property string $tariff_code
 * @property string $product_code
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_to
 */
final class AgreementRecord extends Model
{
    public $timestamps = false;

    protected $table = 'agreements';

    protected $fillable = ['meter_point_id', 'tariff_code', 'product_code', 'valid_from', 'valid_to'];

    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_datetime',
            'valid_to' => 'immutable_datetime',
        ];
    }
}
