<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
            if (request()->routeIs('dealers.registrations.store')) {
                Log::error('Dealer registration failed.', ['exception_type' => $error::class]);

                return false;
            }
        });
        $this->renderable(function (Throwable $error, $request) {
            if (! $request->routeIs('dealers.registrations.store')
                || $error instanceof HttpResponseException) {
                return null;
            }
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;
            $message = $status === 429 ? 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng đợi ít phút rồi thử lại.' : 'Chưa thể nhận đăng ký. Vui lòng kiểm tra thông tin hoặc thử lại sau.';
            $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
            $headers['Cache-Control'] = 'no-store, private';

            return $request->expectsJson()
                ? response()->json(['message' => $message], $status, $headers)
                : response()->view('errors.dealer-registration', compact('message'), $status, $headers);
        });
    }
}
