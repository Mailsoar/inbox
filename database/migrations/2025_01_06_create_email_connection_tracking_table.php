<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_connection_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');
            $table->integer('connections_count')->default(0);
            $table->timestamp('last_connection_at')->nullable();
            $table->timestamp('backoff_until')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('hour_started_at');
            $table->timestamps();
            
            $table->index(['email_account_id', 'hour_started_at']);
        });

        // Table pour stocker les emails en attente de vérification
        Schema::create('pending_email_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->onDelete('cascade');
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');
            $table->string('message_id');
            $table->integer('check_count')->default(0);
            $table->timestamp('next_check_at');
            $table->timestamp('created_at');
            
            $table->index(['email_account_id', 'next_check_at']);
            $table->index(['test_id', 'email_account_id']);
            $table->unique(['test_id', 'email_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_email_checks');
        Schema::dropIfExists('email_connection_tracking');
    }
};