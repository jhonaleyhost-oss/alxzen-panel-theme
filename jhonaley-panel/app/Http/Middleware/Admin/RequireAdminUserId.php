<?php

namespace Pterodactyl\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware yang membatasi akses hanya untuk admin dengan id = 1 (superadmin).
 * Ini menggantikan Closure middleware di routes/admin.php agar rute dapat di-cache
 * dengan aman menggunakan `php artisan route:cache`.
 */
class RequireAdminUserId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->user()->id !== 1) {
            abort(403, 'Access Denied');
        }

        return $next($request);
    }
}
