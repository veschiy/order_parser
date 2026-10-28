<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->nullable();
            $table->string('title')->nullable();
            $table->string('document_url')->unique();
            $table->string('rss_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('parsed_at');
            $table->longText('raw_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
