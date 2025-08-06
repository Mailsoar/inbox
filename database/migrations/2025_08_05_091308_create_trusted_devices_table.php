<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('device_name')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('ip_address', 45);
            $table->timestamp('last_used_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index('email');
            $table->index('token');
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('trusted_devices');
    }
};