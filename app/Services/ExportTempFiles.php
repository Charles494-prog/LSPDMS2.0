<?php

namespace App\Services;

use RuntimeException;

/**
 * One place for the temporary DOCX files the monthly exporters build before streaming them.
 * Normal runs keep using storage/app/monthly-forms; unit tests write to the system temp directory
 * so they never leave files behind in the real storage folder. The test folder is per process, so
 * test runs started at the same time never see (or delete) each other's files.
 */
final class ExportTempFiles
{
    public static function directory(): string
    {
        $directory = app()->runningUnitTests()
            ? rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'lspdms-monthly-forms-tests-'.getmypid()
            : storage_path('app/monthly-forms');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the monthly form output directory.');
        }

        return $directory;
    }

    /**
     * A fresh, unpredictable path such as ".../R4-01-<random>.docx" inside directory().
     */
    public static function path(string $prefix, string $extension = 'docx'): string
    {
        return self::directory().'/'.$prefix.'-'.bin2hex(random_bytes(12)).'.'.$extension;
    }
}
