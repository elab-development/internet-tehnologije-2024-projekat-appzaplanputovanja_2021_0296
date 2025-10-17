<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException; 
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConflictException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
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
        $this->renderable(function (Throwable $e, $request) {

            // --- mapiraj DB CHECK i pre API garde ---
            $isDb = $e instanceof QueryException || $e instanceof PDOException;
            if ($isDb) {
                $msg = $e->getMessage() ?? '';

                // MySQL CHECK: "check_total_cost_budget"
                if (Str::contains($msg, 'check_total_cost_budget')) {
                    return response()->json([
                        'message' => 'Budget decrease would make the current plan exceed the budget. Reduce optional activities or increase the budget.',
                        'code'    => 'BUDGET_EXCEEDED',
                    ], 422);
                }

                // (opciono) tvoji ostali constraint-i
                if (Str::contains($msg, 'date_from_before_date_to')) {
                    return response()->json(['message' => 'Start date/time must be before end date/time.', 'code' => 'DATE_RANGE_INVALID'], 422);
                }
                if (Str::contains($msg, 'time_from_before_time_to')) {
                    return response()->json(['message' => 'An activity has its end time before the start time.', 'code' => 'TIME_RANGE_INVALID'], 422);
                }

                // fallback za ostale SQLSTATE slučajeve
                return response()->json([
                    'message' => 'A database constraint was violated. Please review your input.',
                ], 422);
            }


            // 3) Ako nije API, pusti Laravel view
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            // 4) Ostale tipične mape
            if ($e instanceof ValidationException) {
                return response()->json(['message' => 'The given data was invalid.','errors' => $e->errors()], 422);
            }
            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
            if ($e instanceof NotFoundHttpException) {
                return response()->json(['message' => 'Route not found.'], 404);
            }
            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json(['message' => 'Method not allowed.'], 405);
            }
            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            if ($e instanceof HttpExceptionInterface) {
                return response()->json(
                    ['message' => $e->getMessage() ?: 'HTTP error.'],
                    $e->getStatusCode(),
                    $e->getHeaders()
                );
            }

            // 5) Fallback
            return response()->json(['message' => 'Server Error'], 500);
        });

        
        $this->reportable(function (Throwable $e) {});
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        return redirect()->guest(route('login'));
    }
}

