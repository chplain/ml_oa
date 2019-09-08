<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use phpDocumentor\Reflection\DocBlock\Tags\Author;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
        \League\OAuth2\Server\Exception\OAuthServerException::class,
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
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        // dd(get_class($exception)); // 打印出异常报错类，然后加入到上边的$dontReport数组中
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        $exceptions = parent::render($request, $exception);
        $status_code = $exceptions->getStatusCode();
        switch ($status_code) {
            case 401:
                return response()->json([
                    'code' => 0,
                    'message' => '访问凭据过期，请重新登录',
                ], 401);
                break;
            case 500:
                return response()->json([
                    'code' => 0,
                    'message' => '内部服务器错误，请检查',
                ], 500);
                break;
            case 404:
                return response()->json([
                    'code' => 0,
                    'message' => '接口地址或服务器资源不存在，请检查',
                ], 404);
                break;
            default:
                $exception_message = json_decode($exceptions->getContent(), true);
                return response()->json([
                    'code' => 0,
                    'message' => $exception_message['message'] ?? '',
                ], $status_code);
        }
    }
}
