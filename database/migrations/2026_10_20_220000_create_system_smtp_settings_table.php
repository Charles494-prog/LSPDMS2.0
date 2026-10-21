<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_smtp_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->text('app_password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_smtp_settings');
    }
};
