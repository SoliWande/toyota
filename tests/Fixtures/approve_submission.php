<?php

use App\Actions\ApproveCustomerSubmission;
use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestDatabase;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

TestDatabase::assertIsolated();

[$script, $adminId, $submissionId, $barrier] = $argv;
$sameRecord = ($argv[4] ?? '') === 'same';
// Synchronize before acquiring the global mutex, never wait for a competitor inside it.
touch($barrier.'/'.$adminId.'.ready');
$deadline = microtime(true) + 10;
while (count(glob($barrier.'/*.ready')) < 2) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Race barrier timed out.');
    }
    usleep(20000);
}

try {
    $app->make(ApproveCustomerSubmission::class)->execute(User::findOrFail($adminId), CustomerSubmission::findOrFail($submissionId));
    echo 'approved';
} catch (ValidationException $exception) {
    if ($sameRecord && isset($exception->errors()['submission'])) {
        echo 'stale';
        exit;
    }
    if (! isset($exception->errors()['duplicate'])) {
        throw $exception;
    }
    echo 'duplicate';
}
