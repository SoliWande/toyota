<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $error) {
            // Rendering can wrap database exceptions; never log SQL bindings or traces with arguments.
            for ($exception = $error; $exception !== null; $exception = $exception->getPrevious()) {
                if ($exception instanceof QueryException) {
                    Log::error('Database operation failed.', [
                        'exception_type' => $error::class,
                        'sql_state' => $exception->errorInfo[0] ?? null,
                        'driver_code' => $exception->errorInfo[1] ?? null,
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]);

                    return false;
                }
            }
        });
    }
}
