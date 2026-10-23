<?php

namespace App\Console\Commands;

use App\Services\LspBotMatcher;
use Illuminate\Console\Command;

class LspBotEval extends Command
{
    protected $signature = 'lspbot:eval {file=storage/app/lspbot_tests.csv : CSV with columns: question,expected_key}';

    protected $description = 'Test LSPBot against a CSV of questions and the entry key each should match (use "none" for no match).';

    public function handle(LspBotMatcher $matcher): int
    {
        $path = base_path($this->argument('file'));
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $total = 0;
        $passed = 0;
        $failures = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2 || trim((string) $row[0]) === '' || strtolower(trim($row[0])) === 'question') {
                continue;
            }
            [$question, $expected] = [trim($row[0]), trim($row[1])];
            $result = $matcher->match($question);
            $got = $result['entry']['key'] ?? 'none';
            $total++;

            if ($got === $expected) {
                $passed++;
            } else {
                $failures[] = [$question, $expected, $got, round($result['score'], 2)];
            }
        }
        fclose($handle);

        if ($failures !== []) {
            $this->table(['Question', 'Expected', 'Got', 'Score'], $failures);
        }
        $percent = $total > 0 ? round($passed / $total * 100, 1) : 0;
        $this->info("Passed {$passed}/{$total} ({$percent}%)");

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}