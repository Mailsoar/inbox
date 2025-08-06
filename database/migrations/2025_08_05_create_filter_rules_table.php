<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('filter_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['ip', 'domain', 'mx', 'email_pattern']);
            $table->string('value');
            $table->enum('action', ['block', 'allow'])->default('block');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // Pour stocker des options comme normalize_gmail_dots, normalize_plus_alias
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
            $table->index('value');
        });
    }

    public function down()
    {
        Schema::dropIfExists('filter_rules');
    }
};