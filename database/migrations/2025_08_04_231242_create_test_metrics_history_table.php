<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('test_metrics_history', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->integer('total_tests')->default(0);
            $table->integer('completed_tests')->default(0);
            $table->integer('pending_tests')->default(0);
            $table->integer('in_progress_tests')->default(0);
            $table->integer('timeout_tests')->default(0);
            $table->integer('inbox_count')->default(0);
            $table->integer('spam_count')->default(0);
            $table->integer('total_emails')->default(0);
            $table->decimal('inbox_rate', 5, 2)->default(0);
            $table->decimal('spam_rate', 5, 2)->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->decimal('timeout_rate', 5, 2)->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->json('provider_stats')->nullable();
            $table->json('audience_stats')->nullable();
            $table->json('hourly_distribution')->nullable();
            $table->timestamps();
            
            $table->unique('metric_date');
            $table->index('metric_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('test_metrics_history');
    }
};