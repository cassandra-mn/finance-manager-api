<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia uma rota (404) quando a feature flag correspondente está
 * desligada em config('features'), mesmo pra quem tentar acessar direto
 * pela API — não é só uma questão de esconder o link no frontend. 404 em
 * vez de 403 porque, do ponto de vista de quem está fora do rollout, a
 * rota simplesmente não existe ainda (não revela que a feature existe mas
 * está bloqueada).
 */
final class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! config("features.{$feature}")) {
            abort(404);
        }

        return $next($request);
    }
}
