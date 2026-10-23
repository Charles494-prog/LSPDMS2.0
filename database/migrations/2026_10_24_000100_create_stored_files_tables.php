<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database copies of uploaded files (profile pictures, chat images, monthly
 * session snapshots). The files stay on disk as a cache; these rows make the
 * SQL export/import carry the file contents, so a restore on another PC brings
 * the images back. Contents are base64 in chunks small enough that every
 * exported INSERT stays under MariaDB's default 1MB max_allowed_packet.
 *
 * Both tables are keyed by (disk, path) rather than by row id, so an import
 * can replace one category of files (for example only chat images) without
 * id collisions with the files it keeps.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stored_files')) {
            Schema::create('stored_files', function (Blueprint $table): void {
                $table->id();
                $table->string('disk', 20);
                $table->string('path');
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size');
                $table->char('sha256', 64);
                $table->unsignedInteger('chunk_count');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['disk', 'path']);
            });
        }

        if (! Schema::hasTable('stored_file_chunks')) {
            Schema::create('stored_file_chunks', function (Blueprint $table): void {
                $table->id();
                $table->string('disk', 20);
                $table->string('path');
                $table->unsignedInteger('chunk_index');
                $table->longText('data');
                $table->unique(['disk', 'path', 'chunk_index']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_file_chunks');
        Schema::dropIfExists('stored_files');
    }
};
