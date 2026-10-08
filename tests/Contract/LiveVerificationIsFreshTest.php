<?php

declare(strict_types=1);

namespace Boavizta\Api\Tests\Contract;

use Boavizta\Api\Tests\Contract\Support\LiveVerification;
use PHPUnit\Framework\TestCase;

/**
 * The recordings are only worth what the last live check says about them. This test runs with the
 * contract tier, on every build, so that a live tier which stopped running (failing job, disabled
 * schedule, deleted workflow) becomes a red build instead of going unnoticed.
 */
final class LiveVerificationIsFreshTest extends TestCase
{
    /**
     * Two weekly runs (.github/workflows/live.yml) plus one day of margin.
     *
     * - One failed run is tolerated: an outage of the remote server, or of GitHub, must not turn
     *   every pull request red. The failed job itself already warns the maintainers that week.
     * - Two failed runs in a row are not: from there on the problem is lasting, and every build
     *   says so, whoever opens it.
     * - The extra day absorbs the delays of GitHub scheduled runs, which start late at times of
     *   high load, and leaves room for a manual rerun.
     */
    public const MAX_AGE_DAYS = 15;

    public function testLiveTestsPassedRecently(): void
    {
        $verification = LiveVerification::load();
        $age = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->getTimestamp() - $verification->verifiedAt->getTimestamp();

        self::assertGreaterThanOrEqual(0, $age, 'Last live verification is dated in the future');
        self::assertLessThanOrEqual(self::MAX_AGE_DAYS * 86400, $age, sprintf(
            'The live tests last passed on %s against %s, more than %d days ago: nothing proves the recordings still match the real API. '
            . 'Look at the latest runs of .github/workflows/live.yml, or run bin/verify-live.',
            $verification->verifiedAt->format('Y-m-d'),
            $verification->baseUri,
            self::MAX_AGE_DAYS,
        ));
    }
}
