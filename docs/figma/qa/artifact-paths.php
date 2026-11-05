<?php

// QA-only output helper. No Laravel bootstrap or database connection.
function qa_artifact_output(string $relative): string
{
    $run = getenv('QA_RUN_ID') ?: 'latest';
    if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,79}\z/', $run)
        || ! preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\z~', $relative)) {
        throw new InvalidArgumentException('Invalid QA output path or QA_RUN_ID.');
    }
    $directory = __DIR__.'/artifacts/'.$run.'/screenshots/'.$relative;
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    return $directory;
}
