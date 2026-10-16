<?php

return [
    // Private verified.json from scripts/verify-dealer-backup.php; never commit backups.
    'backup_manifest' => env('DEALER_MIGRATION_BACKUP_MANIFEST'),
];
