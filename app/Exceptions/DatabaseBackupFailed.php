<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A backup file could not be written or failed its usability check. The
 * message is safe to show to the Head Secretary (no SQL, paths or secrets).
 */
class DatabaseBackupFailed extends RuntimeException {}
