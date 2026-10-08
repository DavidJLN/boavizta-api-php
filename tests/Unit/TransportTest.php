<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Unit;

use Boavizta\Api\Dto\Cpu;
use Boavizta\Api\Enum\Criterion;
use Boavizta\Api\Exception\BoaviztaException;
use Boavizta\Api\Exception\NotFoundException;
use Boavizta\Api\Exception\RateLimitException;
use Boavizta\Api\Exception\ServerException;
use Boavizta\Api\Exception\TransportException;
use Boavizta\Api\Exception\UnexpectedResponseException;
use Boavizta\Api\Exception\ValidationException;
use Boavizta\Api\Http\Transport;
use Boavizta\Api\Request\ImpactOptions;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Http\Client\Exception\NetworkException;
use Psr\Http\Message\StreamInterface;

final class TransportTest extends MockClientTestCase
{
    public function testCriteriaAreSentAsRepeatedParameters(): void
    {
        $query = (new ImpactOptions(criteria: [Criterion::Gwp, 'pe'], duration: 8760.0, verbose: true))->toQuery('dellR740');

        self::assertSame(
            'verbose=true&duration=8760&archetype=dellR740&criteria=gwp&criteria=pe',
            Transport::buildQuery($query),
        );
    }

    public function testNullParametersAreOmitted(): void
    {
        self::assertSame('verbose=false', Transport::buildQuery((new ImpactOptions())->toQuery()));
    }

    public function testNotFound(): void
    {
        $this->queueJson(['detail' => 'unknown not found'], 404);

        try {
            $this->client->server()->archetypeConfig('unknown');
            self::fail('NotFoundException expected');
        } catch (NotFoundException $e) {
            self::assertSame('unknown not found', $e->getMessage());
            self::assertSame(404, $e->getCode());
        }
    }

    public function testValidationErrorExposesFastApiDetails(): void
    {
        $detail = [['loc' => ['body', 'units'], 'msg' => 'Input should be a valid integer', 'type' => 'int_parsing']];
        $this->queueJson(['detail' => $detail], 422);

        try {
            $this->client->utils()->cpuNames();
            self::fail('ValidationException expected');
        } catch (ValidationException $e) {
            self::assertSame($detail, $e->errors());
        }
    }

    public function testServerErrorWithoutJson(): void
    {
        $this->http->addResponse(new Response(500, [], 'Internal Server Error'));

        try {
            $this->client->utils()->version();
            self::fail('ServerException expected');
        } catch (ServerException $e) {
            self::assertSame(500, $e->getCode());
            self::assertNull($e->body);
            self::assertFalse($e->isRetryable(), 'A 500 is an answer: the same request fails the same way');
        }
    }

    public function testRateLimitExposesRetryAfterAndIsNotRetried(): void
    {
        $this->http->addResponse(new Response(429, ['Content-Type' => 'application/json', 'Retry-After' => '17'], '{"detail":"Too many requests"}'));

        try {
            $this->client->utils()->version();
            self::fail('RateLimitException expected');
        } catch (RateLimitException $e) {
            self::assertSame('Too many requests', $e->getMessage());
            self::assertSame(429, $e->getCode());
            self::assertTrue($e->isRetryable());
            self::assertSame(17, $e->retryAfter());
        }
        self::assertCount(1, $this->http->getRequests(), 'The client never retries on its own');
    }

    public function testRetryAfterAsHttpDate(): void
    {
        $in = new \DateTimeImmutable('+120 seconds', new \DateTimeZone('UTC'));
        $this->http->addResponse(new Response(503, ['Retry-After' => $in->format(DATE_RFC7231)], 'Service Unavailable'));

        try {
            $this->client->utils()->version();
            self::fail('ServerException expected');
        } catch (ServerException $e) {
            self::assertTrue($e->isRetryable());
            self::assertEqualsWithDelta(120, $e->retryAfter(), 2);
        }
        self::assertCount(1, $this->http->getRequests(), 'The client never retries on its own');
    }

    public function testGatewayErrorsAreRetryableWithoutDelay(): void
    {
        $this->http->addResponse(new Response(502, [], 'Bad Gateway'));

        try {
            $this->client->utils()->version();
            self::fail('ServerException expected');
        } catch (ServerException $e) {
            self::assertTrue($e->isRetryable());
            self::assertNull($e->retryAfter());
        }
    }

    public function testOtherClientErrors(): void
    {
        $this->queueJson(['detail' => 'Bad request'], 400);

        try {
            $this->client->utils()->version();
            self::fail('BoaviztaException expected');
        } catch (BoaviztaException $e) {
            self::assertSame(BoaviztaException::class, $e::class);
            self::assertSame('Bad request', $e->getMessage());
            self::assertFalse($e->isRetryable());
        }
    }

    public function testNetworkFailureIsTranslated(): void
    {
        $cause = new NetworkException('connection refused', (new HttpFactory())->createRequest('GET', '/'));
        $this->http->addException($cause);

        try {
            $this->client->utils()->version();
            self::fail('TransportException expected');
        } catch (TransportException $e) {
            self::assertTrue($e->isRetryable());
            self::assertSame($cause, $e->getPrevious(), 'Kept for debugging, never thrown');
        }
    }

    public function testBodyReadFailureIsTranslated(): void
    {
        $body = $this->createStub(StreamInterface::class);
        $body->method('getContents')->willThrowException(new \RuntimeException('connection reset while reading'));
        $this->http->addResponse((new Response(200))->withBody($body));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('connection reset while reading');
        $this->client->utils()->version();
    }

    public function testInvalidJsonOnSuccessIsUnexpected(): void
    {
        $this->http->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{"impacts":'));

        try {
            $this->client->utils()->version();
            self::fail('UnexpectedResponseException expected');
        } catch (UnexpectedResponseException $e) {
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
            self::assertFalse($e->isRetryable());
        }
    }

    public function testUnencodableBodyIsRejectedBeforeSending(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->client->component()->impact(new Cpu(dieSize: NAN));
        } finally {
            self::assertCount(0, $this->http->getRequests());
        }
    }

    public function testBaseUriTrailingSlashIsNormalized(): void
    {
        $this->queueJson('2.4.1');

        self::assertSame('2.4.1', $this->client->utils()->version());
        self::assertSame('http://boavizta.test/v1/utils/version', (string) $this->lastRequest()->getUri());
    }
}
