<?php

namespace App\Http\Middleware;

use Closure;
use phpDocumentor\Reflection\DocBlock\Tags\Author;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $this->checkPermission() ? $next($request) : response()->json(['code' => 0, 'message' => '对不起，你暂无操作权限，请联系超级管理员']);
    }

    private function checkPermission()
    {
        $route_name = \request()->route()->getName();
        return $this->hasPermission($route_name);
    }

    private function hasPermission($permission)
    {
        $check = false;
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->id == 1 || $user->hasRole(1) || $user->can($permission)) {
                $check = true;
            }
        }
        return $check;
    }
}
