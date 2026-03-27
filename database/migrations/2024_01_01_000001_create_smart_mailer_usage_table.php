<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_mailer_usage', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 100)->index();
            $table->string('period_type', 20);  // daily | hourly | total
            $table->date('period_date')->nullable();
            $table->tinyInteger('period_hour')->unsigned()->nullable();
            $table->unsignedInteger('sent_today')->default(0);
            $table->unsignedInteger('sent_this_hour')->default(0);
            $table->unsignedBigInteger('sent_total')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_key', 'period_type', 'period_date', 'period_hour'], 'smart_mailer_usage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_mailer_usage');
    }
};
