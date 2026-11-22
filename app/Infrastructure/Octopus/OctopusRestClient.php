<?php

declare(strict_types=1);

namespace App\Infrastructure\Octopus;

use Generator;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Thin, read-only wrapper around the Octopus REST API (GET only).
 */
final readonly class OctopusRestClient
{
    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private string $apiKey,
        private int $timeout,
    ) {}

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        /** @var array<string, mixed> */
        return $this->request()->get($path, $query)->throw()->json();
    }

    /**
     * Walks a paginated endpoint, yielding each result. A 404 yields nothing
     * (e.g. a retired meter or a tariff without that rate kind).
     *
     * @param  array<string, scalar>  $query
     * @return Generator<int, array<string, mixed>>
     */
    public function paginate(string $path, array $query = []): Generator
    {
        $response = $this->request()->get($path, $query);

        while (true) {
            if ($response->status() === 404) {
                return;
            }

            $response->throw();

            foreach ($response->json('results', []) as $result) {
                yield $result;
            }

            $next = $response->json('next');

            if (! is_string($next) || $next === '') {
                return;
            }

            $response = $this->request()->get($next);
        }
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl)
            ->withBasicAuth($this->apiKey, '')
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(3, 1000, static fn (Throwable $e): bool => ! $e instanceof RequestException || $e->response->serverError() || $e->response->status() === 429, throw: false);
    }
}
