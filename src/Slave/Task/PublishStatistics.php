<?php

declare(strict_types=1);

namespace App\Slave\Task;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use stdClass;

class PublishStatistics implements TaskInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    private ClientInterface $client;
    private $method;
    private $endpoint;
    private $body;

    public function __construct(LoggerInterface $logger, ClientInterface $client)
    {
        $this->logger = $logger;
        $this->client = $client;
    }

    public function execute(): array
    {
        $startedAt = microtime(true);
        $this->logger->info(sprintf('worker: publishing stats to master (%d bytes)', strlen(serialize($this->body))));
        try {
            $response = $this->client->request($this->method, $this->endpoint, [
                RequestOptions::JSON => $this->body,
                RequestOptions::COOKIES => new FileCookieJar(sys_get_temp_dir().'/.slave.cookiejar.json', true)
            ]);

            $this->logger->info(sprintf('worker: published stats (took %.2f seconds)', microtime(true) - $startedAt));
            return ['code' => $response->getStatusCode(), 'contents' => (string)$response->getBody()];
        } catch (RequestException $exception) {
            $this->logger->error(sprintf('worker: failed to publish stats: %s (took %.2f seconds)', $exception->getMessage(), microtime(true) - $startedAt));

            $body = $exception->getResponse() === null ? 'empty body' : (string)$exception->getResponse()->getBody();
            $this->logger->debug(sprintf('worker: stats response body: %s (took %.2f seconds)', $body, microtime(true) - $startedAt));

            return ['code' => $exception->getCode(), 'contents' => $exception->getMessage()];
        } catch (GuzzleException $exception) {
            $this->logger->error(sprintf('worker: failed to publish stats: %s (took %.2f seconds)', $exception->getMessage(), microtime(true) - $startedAt));

            return ['code' => $exception->getCode(), 'contents' => $exception->getMessage()];
        }
    }

    public function setArgs(array $args): void
    {
        $this->method = $args['method'] ?? 'POST';
        $this->endpoint = sprintf('/api/slaves/%s/stats', $_ENV['SLAVE_NAME']);
        $this->body = $args['body'] ?? new stdClass();
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getType(): string
    {
        return self::class;
    }
}
