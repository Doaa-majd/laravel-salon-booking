<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Redirect users to their role-appropriate landing page after login.
     *
     * Extends Fortify's login response only (per ADR-0003); the core auth
     * flow is untouched.
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();

        $target = $user instanceof User && $user->isAdmin()
            ? route('admin.dashboard')
            : (string) config('fortify.home', '/dashboard');

        return redirect()->intended($target);
    }
}
