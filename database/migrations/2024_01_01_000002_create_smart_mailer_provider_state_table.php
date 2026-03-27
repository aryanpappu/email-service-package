<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_mailer_provider_state', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 100)->unique();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedBigInteger('cooling_until')->nullable()->comment('Unix timestamp when cooling ends');
            $table->unsignedBigInteger('last_used_at')->nullable()->comment('Unix timestamp of last successful send');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_mailer_provider_state');
    }
};
