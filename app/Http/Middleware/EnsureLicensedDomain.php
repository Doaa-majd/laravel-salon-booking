<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soft license/domain guard (analysis doc §settings — license_domain "للحماية").
 *
 * When the salon's settings.license_domain is configured, the admin area is only
 * reachable from that host; requests from any other host are rejected (403). When
 * it is empty/null the guard is a no-op, so an un-provisioned install and the test
 * suite are unaffected. Host comparison is case-insensitive and ignores a port.
 */
class EnsureLicensedDomain
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $licensed = trim((string) Setting::current()->license_domain);

        if ($licensed !== '') {
            abort_unless(
                strcasecmp($request->getHost(), $licensed) === 0,
                Response::HTTP_FORBIDDEN,
                'This installation is not licensed for this domain.',
            );
        }

        return $next($request);
    }
}
