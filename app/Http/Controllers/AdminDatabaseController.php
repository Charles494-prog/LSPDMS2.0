<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDatabaseController extends Controller
{
    public function exportSql(Request $request, DatabaseBackupService $backups): StreamedResponse
    {
        $validated = $request->validate([
            'exclude_tables' => ['sometimes', 'array'],
            'exclude_tables.*' => ['required', 'string', 'distinct', Rule::in(['login_user', 'messages'])],
        ]);
        $excludedTables = $validated['exclude_tables'] ?? [];

        return response()->streamDownload(function () use ($backups, $excludedTables): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new RuntimeException('Unable to open the SQL export stream.');
            }

            try {
                $backups->writeSql($stream, $excludedTables);
            } finally {
                fclose($stream);
            }
        }, 'lspdms.sql', [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }

    public function previewSqlImport(Request $request, DatabaseBackupService $backups): JsonResponse
    {
        $validated = $request->validate([
            'sql_file' => ['required', 'file', 'max:102400', 'extensions:sql'],
        ]);
        $inspection = $backups->inspectImport($validated['sql_file']);

        return response()->json([
            'tables' => $inspection['tables'],
            'omitted_tables' => $inspection['omitted_tables'],
            'message' => 'The import replaces data only in the listed tables. Omitted tables will remain unchanged.',
        ]);
    }

    public function importSql(Request $request, DatabaseBackupService $backups): JsonResponse
    {
        $validated = $request->validate([
            'sql_file' => ['required', 'file', 'max:102400', 'extensions:sql'],
            'confirmation_text' => ['required', 'string', Rule::in(['IMPORT DATABASE'])],
            'current_password' => ['required', 'string'],
        ]);
        $userId = (int) $request->session()->get('login_user.id');
        $user = DB::table('login_user')->where('id', $userId)->first();
        abort_unless($user, 403);

        $passwordMatches = str_starts_with((string) $user->password, '$')
            ? Hash::check($validated['current_password'], $user->password)
            : hash_equals((string) $user->password, $validated['current_password']);
        if (! $passwordMatches) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $backupPath = $backups->import($validated['sql_file']);

        return response()->json([
            'message' => 'Database import completed. A safety backup was saved before the import.',
            'backup_file' => basename($backupPath),
        ]);
    }

    public function exportXlsx(Request $request, DatabaseBackupService $backups): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $tableNames = $backups->tableNames();
        $validated = $request->validate([
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['required', 'string', 'distinct', Rule::in($tableNames)],
        ]);

        $directory = storage_path('app/private');
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the private export directory.');
        }

        $path = tempnam($directory, 'xlsx-export-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary XLSX export file.');
        }

        $writer = new Writer;
        $writerOpened = false;
        $exportCompleted = false;

        try {
            $writer->openToFile($path);
            $writerOpened = true;

            foreach ($validated['tables'] as $index => $table) {
                $sheet = $index === 0
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($this->sheetName($table, $validated['tables'], $index));

                $columns = $backups->exportColumns($table);
                $writer->addRow(Row::fromValues($columns));

                foreach (DB::table($table)->cursor() as $databaseRow) {
                    $row = (array) $databaseRow;
                    $cells = [];
                    foreach ($columns as $column) {
                        $value = $row[$column] ?? null;
                        $cells[] = $value === null || $value === ''
                            ? new EmptyCell($value, null)
                            : new StringCell((string) $value, null);
                    }
                    $writer->addRow(new Row($cells));
                }
            }

            $writer->close();
            $writerOpened = false;
            $exportCompleted = true;
        } finally {
            if ($writerOpened) {
                $writer->close();
            }
            if (! $exportCompleted && is_file($path)) {
                unlink($path);
            }
        }

        return response()->download($path, 'lspdms-tables-'.now()->format('Ymd-His').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function sheetName(string $table, array $tables, int $index): string
    {
        $base = preg_replace('/[\\\\\/?*\[\]:]/', '_', $table) ?: 'Table';
        $base = mb_substr($base, 0, 31);
        $name = $base;
        $suffix = 1;

        while (in_array(mb_strtolower($name), array_map(
            fn (string $previous): string => mb_strtolower(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '_', $previous) ?: 'Table', 0, 31)),
            array_slice($tables, 0, $index)
        ), true)) {
            $ending = '-'.$suffix++;
            $name = mb_substr($base, 0, 31 - mb_strlen($ending)).$ending;
        }

        return $name;
    }
}
