<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parse_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('mode');
            $table->string('source_url');
            $table->string('status');
            $table->unsignedInteger('items_found')->default(0);
            $table->unsignedInteger('items_parsed')->default(0);
            $table->unsignedInteger('orders_created')->default(0);
            $table->unsignedInteger('awardees_created')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parse_runs');
    }
};
