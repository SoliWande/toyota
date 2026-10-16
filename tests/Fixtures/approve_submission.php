<?php

use App\Actions\ApproveCustomerSubmission;
use App\Enums\SubmissionStatus;
use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = getenv('DEALER_SUBMISSION_TEST_DATABASE');
if (! $app->environment('testing') || ! preg_match('/^toyota_dealer_integrity_test_[a-f0-9]{16}$/D', $database ?: '')) {
    throw new LogicException('Approval race fixture requires an isolated restored database.');
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
DB::purge('mysql');

[$script, $adminId, $submissionId, $barrier] = $argv;
if (realpath(dirname($barrier)) !== realpath(base_path('.laravel-tools'))
    || ! preg_match('/^submission-approval-race-[a-f0-9]{16}$/D', basename($barrier))) {
    throw new LogicException('Invalid approval race barrier.');
}
CustomerSubmission::saving(function (CustomerSubmission $submission) use ($barrier) {
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
    if (! isset($exception->errors()['duplicate'])) {
        throw $exception;
    }
    echo 'duplicate';
}
