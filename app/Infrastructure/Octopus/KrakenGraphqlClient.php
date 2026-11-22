<?php

declare(strict_types=1);

namespace App\Infrastructure\Octopus;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Read-only Kraken GraphQL client. The only mutation sent is obtainKrakenToken.
 */
final readonly class KrakenGraphqlClient
{
    private const TOKEN_TTL_SECONDS = 50 * 60;

    private const TOKEN_MUTATION = <<<'GQL'
        mutation ObtainToken($key: String!) {
          obtainKrakenToken(input: { APIKey: $key }) { token }
        }
        GQL;

    public function __construct(
        private HttpFactory $http,
        private Cache $cache,
        private string $url,
        private string $apiKey,
        private int $timeout,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function query(string $query, array $variables = []): array
    {
        if (preg_match('/^\s*mutation\b/', $query) === 1) {
            throw new RuntimeException('Mutations are not permitted: this client is read-only.');
        }

        return $this->send($query, $variables, $this->token());
    }

    private function token(): string
    {
        $cacheKey = 'octopus:kraken-token:'.hash('sha256', $this->apiKey);

        /** @var string */
        return $this->cache->remember($cacheKey, self::TOKEN_TTL_SECONDS, function (): string {
            $data = $this->send(self::TOKEN_MUTATION, ['key' => $this->apiKey], null);
            $token = $data['obtainKrakenToken']['token'] ?? null;

            return is_string($token) ? $token : throw new RuntimeException('Kraken did not return a token.');
        });
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function send(string $query, array $variables, ?string $token): array
    {
        $request = $this->http->acceptJson()->asJson()->timeout($this->timeout);

        if ($token !== null) {
            $request = $request->withHeaders(['Authorization' => $token]);
        }

        $response = $request->post($this->url, ['query' => $query, 'variables' => $variables])->throw();

        if ($response->json('errors')) {
            throw new RuntimeException('Kraken GraphQL error: '.(string) $response->json('errors.0.message'));
        }

        /** @var array<string, mixed> */
        return $response->json('data') ?? [];
    }
}
