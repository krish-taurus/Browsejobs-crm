<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hard gate for founder-only surfaces (Taurus Neural Ops).
 *
 * Deliberately NOT permission-based like {@see CheckPermission}: a permission
 * can be handed to any role from the Roles & Permissions screen, and this
 * section shows company-wide finance, per-employee performance signals and
 * lead PII together on one page. Membership of the SUPER_ADMIN role is the
 * only thing that opens it, and that is set on the user record.
 *
 * Usage in routes:
 *   Route::middleware(['auth', 'super-admin'])->group(function () { ... });
 */
class SuperAdminOnly
{
    public const ROLE_CODE = 'SUPER_ADMIN';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $user->role?->role_code !== self::ROLE_CODE) {
            abort(403, 'This section is restricted to the Super Admin.');
        }

        return $next($request);
    }
}
