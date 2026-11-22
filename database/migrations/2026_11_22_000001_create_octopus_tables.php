<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_points', function (Blueprint $table): void {
            $table->id();
            $table->string('fuel', 16);
            $table->string('direction', 16);
            $table->string('mpxn', 32)->unique();
            $table->timestamps();
        });

        Schema::create('meters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meter_point_id')->constrained()->cascadeOnDelete();
            $table->string('serial', 64);
            $table->unique(['meter_point_id', 'serial']);
        });

        Schema::create('agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meter_point_id')->constrained()->cascadeOnDelete();
            $table->string('tariff_code', 64);
            $table->string('product_code', 64);
            $table->dateTime('valid_from');
            $table->dateTime('valid_to')->nullable();
            $table->unique(['meter_point_id', 'tariff_code', 'valid_from']);
        });

        Schema::create('tariff_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('tariff_code', 64);
            $table->string('kind', 16);
            $table->bigInteger('value_micropence');
            $table->dateTime('valid_from');
            $table->dateTime('valid_to')->nullable();
            $table->unique(['tariff_code', 'kind', 'valid_from']);
        });

        Schema::create('intervals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meter_point_id')->constrained()->cascadeOnDelete();
            $table->dateTime('interval_start');
            $table->dateTime('interval_end');
            $table->integer('energy_wh');
            $table->bigInteger('unit_rate_micropence')->nullable();
            $table->bigInteger('cost_micropence')->nullable();
            $table->date('local_date');
            $table->unsignedTinyInteger('local_slot');
            $table->unique(['meter_point_id', 'interval_start']);
            $table->index(['meter_point_id', 'local_date']);
        });

        Schema::create('daily_standing_charges', function (Blueprint $table): void {
            $table->foreignId('meter_point_id')->constrained()->cascadeOnDelete();
            $table->date('local_date');
            $table->bigInteger('amount_micropence');
            $table->primary(['meter_point_id', 'local_date']);
        });

        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->boolean('succeeded')->nullable();
            $table->text('summary')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['sync_runs', 'daily_standing_charges', 'intervals', 'tariff_rates', 'agreements', 'meters', 'meter_points'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
