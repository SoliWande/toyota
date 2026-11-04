<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;

require dirname(__DIR__, 3).'/vendor/autoload.php';

// Use only after competition-db-check.php passes. Existing isolated schema only;
// the UI regression run must not migrate, seed or reset any database.
RefreshDatabaseState::$migrated = true;
