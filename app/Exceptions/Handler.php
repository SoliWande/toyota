<?php

namespace App\Exceptions;

use App\Http\Controllers\RegistrationLookupController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
            if (request()->routeIs('dealers.registrations.*', 'dealers.lookup.*')) {
                Log::error('Dealer registration failed.', ['exception_type' => $error::class]);

                return false;
            }
        });
        $this->renderable(function (Throwable $error, $request) {
            if ($request->routeIs('dealers.lookup.*') && ! $error instanceof HttpResponseException) {
                $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;
                $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
                $headers['Cache-Control'] = 'no-store, private';
                $headers['X-Robots-Tag'] = 'noindex, nofollow';
                $headers['Referrer-Policy'] = 'no-referrer';
                $message = $status === 429 ? 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng đợi ít phút rồi thử lại.'
                    : 'Chưa thể tra cứu. Vui lòng kiểm tra thông tin hoặc thử lại sau.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], $status, $headers);
                }
                try {
                    return app(RegistrationLookupController::class)->page($request,
                        errors: ['lookup' => [$message]], status: $status)->withHeaders($headers);
                } catch (ModelNotFoundException) {
                    // Unknown Dealer: use the normal HTTP error renderer, never a registration page.
                    return null;
                }
            }
            if (! $request->routeIs('dealers.registrations.*')
                || $error instanceof HttpResponseException) {
                return null;
            }
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;
            if ($error instanceof ValidationException) {
                $errors = $error->errors();

                return $request->expectsJson()
                    ? response()->json(['errors' => $errors], 422, ['Cache-Control' => 'no-store, private'])
                    : redirect()->to(route('dealers.profile', $request->route('slug')).'#registration')->withErrors($errors)->header('Cache-Control', 'no-store, private');
            }
            $message = $status === 429 ? 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng đợi ít phút rồi thử lại.' : 'Chưa thể nhận đăng ký. Vui lòng kiểm tra thông tin hoặc thử lại sau.';
            $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
            $headers['Cache-Control'] = 'no-store, private';

            return $request->expectsJson()
                ? response()->json(['message' => $message], $status, $headers)
                : response()->view('errors.dealer-registration', compact('message'), $status, $headers);
        });
    }
}
