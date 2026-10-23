<?php

namespace Tests\Feature;

use App\Services\FileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_is_kept_in_database_chunks_and_restored_when_the_disk_copy_is_lost(): void
    {
        Storage::fake('local');
        $store = app(FileStore::class);
        $contents = random_bytes(FileStore::CHUNK_BYTES * 2 + 123);

        $store->put('local', 'messages/1/photo.png', $contents, 'image/png');

        $this->assertSame(3, DB::table('stored_file_chunks')->count());
        $this->assertDatabaseHas('stored_files', [
            'disk' => 'local',
            'path' => 'messages/1/photo.png',
            'mime' => 'image/png',
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'chunk_count' => 3,
        ]);

        Storage::disk('local')->delete('messages/1/photo.png');
        $this->assertSame($contents, $store->get('local', 'messages/1/photo.png'));
        Storage::disk('local')->assertExists('messages/1/photo.png');
    }

    public function test_damaged_disk_copy_is_replaced_and_missing_files_return_null(): void
    {
        Storage::fake('public');
        $store = app(FileStore::class);
        $store->put('public', 'profile-pictures/a.jpg', 'original-bytes', 'image/jpeg');
        Storage::disk('public')->put('profile-pictures/a.jpg', 'tampered');

        $this->assertSame('original-bytes', $store->get('public', 'profile-pictures/a.jpg'));
        $this->assertSame('original-bytes', Storage::disk('public')->get('profile-pictures/a.jpg'));
        $this->assertNull($store->get('public', 'profile-pictures/missing.jpg'));
        $this->assertNull($store->response('public', 'profile-pictures/missing.jpg', 'x.jpg'));
    }

    public function test_every_exported_chunk_fits_under_the_default_mariadb_packet_limit(): void
    {
        // The SQL exporter hex-encodes each value, doubling its length.
        $base64Length = (int) ceil(FileStore::CHUNK_BYTES / 3) * 4;
        $this->assertLessThan(1048576 - 4096, $base64Length * 2);
    }

    public function test_ingest_copies_existing_disk_files_once_and_reports_missing_ones(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-pictures/b.gif', 'gif-bytes');
        $store = app(FileStore::class);

        $this->assertSame('stored', $store->ingest('public', 'profile-pictures/b.gif'));
        $this->assertSame('already_stored', $store->ingest('public', 'profile-pictures/b.gif'));
        $this->assertSame('missing', $store->ingest('public', 'profile-pictures/none.gif'));

        $store->delete('public', 'profile-pictures/b.gif');
        $this->assertFalse($store->exists('public', 'profile-pictures/b.gif'));
    }
}
