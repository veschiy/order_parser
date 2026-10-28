<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awardees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('full_name')->index();
            $table->string('surname')->nullable()->index();
            $table->string('given_name')->nullable();
            $table->string('patronymic')->nullable();
            $table->string('rank')->nullable();
            $table->string('award')->nullable();
            $table->text('position_note')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'full_name', 'award']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awardees');
    }
};
