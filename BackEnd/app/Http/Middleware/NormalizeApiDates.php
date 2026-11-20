<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeApiDates
{
    private const OPAQUE = ['content', 'snapshot', 'details', 'profile', 'grants', 'checks', 'evidence_ids', 'errors'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof JsonResponse) {
            $response->setData($this->normalize($response->getData(true)));
        }

        return $response;
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, self::OPAQUE, true)) {
                continue;
            }if (is_string($key) && str_ends_with($key, '_at') && is_string($item) && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}(?::?\d{2})?)?$/', $item)) {
                $value[$key] = CarbonImmutable::parse($item, 'UTC')->utc()->format('Y-m-d\TH:i:s.u\Z');
            } elseif (is_array($item)) {
                $value[$key] = $this->normalize($item);
            }
        }

        return $value;
    }
}
