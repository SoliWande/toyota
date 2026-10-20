<?php

use App\Actions\ApproveCustomerSubmission;
use App\Enums\SubmissionStatus;
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
if ($sameRecord) {
    touch($barrier.'/'.$adminId.'.ready');
    $deadline = microtime(true) + 10;
    while (count(glob($barrier.'/*.ready')) < 2) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Race barrier timed out.');
        }
        usleep(20000);
    }
}
CustomerSubmission::saving(function (CustomerSubmission $submission) use ($barrier, $sameRecord) {
    if ($sameRecord) {
        return;
    }
    if ($submission->status !== SubmissionStatus::Approved) {
        return;
    }

    // Both transactions have passed the application duplicate check before either writes.
    touch($barrier.'/'.$submission->id.'.ready');
    $deadline = microtime(true) + 10;
    while (count(glob($barrier.'/*.ready')) < 2) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Race barrier timed out.');
        }
        usleep(20000);
    }
});

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
