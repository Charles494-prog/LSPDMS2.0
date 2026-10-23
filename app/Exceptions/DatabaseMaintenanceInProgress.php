<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Another database import or backup is running, so this one did not start.
 */
class DatabaseMaintenanceInProgress extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Another database import or backup is already running. Wait for it to finish, then try again.');
    }
}
