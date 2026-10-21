<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delistment_histories')) {
            if (DB::table('delistment_histories')->exists()) {
                throw new RuntimeException('Cannot repair the incomplete delistment histories table because it already contains data.');
            }

            Schema::drop('delistment_histories');
        }

        Schema::create('delistment_histories', function (Blueprint $table) {
            $table->id();
$table->unsignedInteger('record_id');
            $table->foreignId('delisted_by_user_id')->nullable()->constrained('login_user')->nullOnDelete();
            $table->string('delisted_by', 255);
            $table->text('reason');
            $table->timestamp('delisted_at');
            $table->foreign('record_id')->references('id')->on('records')->cascadeOnDelete();
            $table->index(['record_id', 'delisted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delistment_histories');
    }
};
