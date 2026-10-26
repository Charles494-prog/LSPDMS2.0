<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_up_reports_ok_when_the_database_answers(): void
    {
        $this->get('/up')->assertOk()->assertSee('Application up');
    }

    public function test_up_reports_a_failure_when_the_database_is_down(): void
    {
        config(['app.debug' => false]);
        Exceptions::fake();
        DB::shouldReceive('select')->once()->with('select 1')
            ->andThrow(new RuntimeException('SQLSTATE[HY000] [2002] No connection could be made'));

        $this->get('/up')
            ->assertStatus(500)
            ->assertSee('Application experiencing problems')
            ->assertDontSee('SQLSTATE');

        Exceptions::assertReported(fn (RuntimeException $e): bool => str_contains($e->getMessage(), '2002'));
    }
}
