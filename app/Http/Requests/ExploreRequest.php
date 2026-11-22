<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Shared\Granularity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExploreRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'meter' => ['nullable', 'integer', 'exists:meter_points,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'granularity' => ['nullable', Rule::enum(Granularity::class)],
        ];
    }

    public function meter(): ?int
    {
        return $this->filled('meter') ? $this->integer('meter') : null;
    }

    public function granularity(): ?Granularity
    {
        return $this->enum('granularity', Granularity::class);
    }
}
