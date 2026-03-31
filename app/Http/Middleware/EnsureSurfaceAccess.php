<?php

namespace App\Http\Middleware;

use App\Services\PortalAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSurfaceAccess
{
    public function __construct(
        private readonly PortalAccessService $portalAccessService,
    ) {}

    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('portal.entry', [
                'redirect' => $request->getRequestUri(),
            ]);
        }

        if ($this->portalAccessService->canAccessSurface($user, $surface)) {
            return $next($request);
        }

        $home = $this->portalAccessService->resolveHome($user);

        if ($home !== $request->getPathInfo()) {
            return redirect()->to($home);
        }

        abort(403);
    }
}
