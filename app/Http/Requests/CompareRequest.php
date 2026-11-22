<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Dashboard\ComparePeriods;
use App\Domain\Shared\Granularity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompareRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'meter' => ['nullable', 'integer', 'exists:meter_points,id'],
            'granularity' => ['nullable', Rule::in([Granularity::Day->value, Granularity::Week->value, Granularity::Month->value])],
            'periods' => ['nullable', 'array', 'max:'.ComparePeriods::MAX_PERIODS],
            'periods.*' => ['date_format:Y-m-d'],
        ];
    }

    public function meter(): ?int
    {
        return $this->filled('meter') ? $this->integer('meter') : null;
    }

    public function granularity(): Granularity
    {
        return $this->enum('granularity', Granularity::class) ?? Granularity::Month;
    }

    /** @return list<string> */
    public function periods(): array
    {
        return array_values(array_map('strval', (array) $this->input('periods', [])));
    }
}
