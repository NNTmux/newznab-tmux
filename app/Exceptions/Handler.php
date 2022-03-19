<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Catch errors with Sentry.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    }

    /**
     * Report or log an exception.
     *
     *
     * @param  \Exception  $exception
     * @return void
     *
     * @throws \Throwable
     */
    public function report(\Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, \Throwable $exception)
    {
        if ($exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
            abort(401);
        }
        if ($exception instanceof \Illuminate\Session\TokenMismatchException) {
            return redirect()
                ->back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'The form has expired due to inactivity. Please refresh the page and try again');
        }

        return parent::render($request, $exception);
    }
}
