<?php

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The Vite dev server serves files straight from the project folder, so its config must keep it
 * on this PC and limited to the front-end sources (no backups, sessions, logs or PHP code).
 */
class ViteDevServerConfigTest extends TestCase
{
    public function test_dev_server_listens_on_loopback_by_default(): void
    {
        $server = $this->resolvedServerConfig();

        $this->assertSame('127.0.0.1', $server['host']);
        $this->assertSame(4000, $server['port']);
    }

    public function test_bind_address_can_be_widened_on_purpose(): void
    {
        $server = $this->resolvedServerConfig(['VITE_DEV_SERVER_BIND' => '0.0.0.0']);

        $this->assertSame('0.0.0.0', $server['host']);
    }

    public function test_only_front_end_folders_are_served_and_sensitive_files_are_denied(): void
    {
        $fs = $this->resolvedServerConfig()['fs'];

        $this->assertSame(['resources', 'node_modules'], $fs['allow']);

        // Setting deny replaces Vite's own defaults, so they must still be listed.
        foreach (['.env', '.env.*', '*.{crt,pem}', '**/.git/**'] as $default) {
            $this->assertContains($default, $fs['deny']);
        }
        foreach (['**/storage/**', '**/bootstrap/cache/**', '**/database/**', '**/vendor/**', '*.sql', '*.log'] as $pattern) {
            $this->assertContains($pattern, $fs['deny']);
        }
    }

    /** Evaluates vite.config.js with Node and returns its `server` block. */
    private function resolvedServerConfig(array $env = []): array
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null || ! is_file(base_path('node_modules/vite/package.json'))) {
            $this->markTestSkipped('Node and the JS packages are needed to evaluate vite.config.js.');
        }

        $script = "import config from './vite.config.js';"
            ."const { server } = await config({ mode: 'development', command: 'serve' });"
            .'process.stdout.write(JSON.stringify(server));';

        $process = new Process([$node, '--input-type=module', '-e', $script], base_path(), $env + ['VITE_DEV_SERVER_BIND' => false]);
        $process->setTimeout(60)->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
