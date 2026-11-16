<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_records', function (Blueprint $table): void {
            $table->id();
            $table->string('sheet')->nullable();
            $table->string('full_name')->index();
            $table->string('tax_id')->nullable();
            $table->date('submission_date')->nullable();
            $table->string('award')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_records');
    }
};
