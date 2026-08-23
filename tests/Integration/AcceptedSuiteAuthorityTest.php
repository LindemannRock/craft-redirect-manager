<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\redirectmanager\tests\Support\AcceptedSuiteAuthority;
use lindemannrock\redirectmanager\tests\TestCase;
use RuntimeException;

/**
 * Proves that accepted declared and executed coverage cannot drift silently.
 *
 * @since 5.41.0
 */
final class AcceptedSuiteAuthorityTest extends TestCase
{
    public function testMissingIntegrationClassIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Declared suite differs');
        AcceptedSuiteAuthority::assertDeclared(
            ['integrationClasses' => 5, 'testMethods' => 7],
            ['integrationClasses' => 4, 'testMethods' => 7],
        );
    }

    public function testMissingDeclaredBehaviorMethodIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Declared suite differs');
        AcceptedSuiteAuthority::assertDeclared(
            ['integrationClasses' => 5, 'testMethods' => 7],
            ['integrationClasses' => 5, 'testMethods' => 6],
        );
    }

    public function testExecutedCaseLossIsRejectedWhenDeclarationsRemainStable(): void
    {
        $actual = $this->executionSummary();
        $actual['tests']--;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Executed suite differs');
        AcceptedSuiteAuthority::assertExecuted($this->executionSummary(), $actual);
    }

    public function testAssertionLossIsRejected(): void
    {
        $actual = $this->executionSummary();
        $actual['assertions']--;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Executed suite differs');
        AcceptedSuiteAuthority::assertExecuted($this->executionSummary(), $actual);
    }

    public function testSkippedAndIncompleteCasesAreRejected(): void
    {
        foreach (['skipped', 'incomplete'] as $outcome) {
            $actual = $this->executionSummary();
            $actual[$outcome] = 1;
            try {
                AcceptedSuiteAuthority::assertExecuted($this->executionSummary(), $actual);
                self::fail("A new {$outcome} case must fail the accepted suite authority.");
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Executed suite differs', $exception->getMessage());
            }
        }
    }

    public function testMissingAndMalformedAuthoritiesAreRejected(): void
    {
        $temporaryRoot = $this->createTrackedTempDirectory('redirect-manager-suite-authority-');
        $missing = $temporaryRoot . '/missing.json';
        try {
            AcceptedSuiteAuthority::load($missing);
            self::fail('A missing accepted suite authority must fail.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('is missing', $exception->getMessage());
        }
        $malformed = $temporaryRoot . '/malformed.json';
        self::assertNotFalse(file_put_contents($malformed, '{"schemaVersion":1}'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('complete schemaVersion 1 contract');
        AcceptedSuiteAuthority::load($malformed);
    }

    public function testAcceptedBaselineChangeRequiresAnExplicitFileUpdate(): void
    {
        $temporaryRoot = $this->createTrackedTempDirectory('redirect-manager-suite-update-');
        $baselinePath = $temporaryRoot . '/accepted-suite.json';
        $baseline = [
            'schemaVersion' => 1,
            'declared' => ['integrationClasses' => 1, 'testMethods' => 1],
            'executed' => $this->executionSummary(),
        ];
        self::assertNotFalse(file_put_contents($baselinePath, json_encode($baseline, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)));
        $accepted = AcceptedSuiteAuthority::load($baselinePath);
        AcceptedSuiteAuthority::assertExecuted($accepted['executed'], $this->executionSummary());
        self::assertSame(11, $accepted['executed']['tests']);

        $changed = $this->executionSummary();
        $changed['tests']++;
        try {
            AcceptedSuiteAuthority::assertExecuted($accepted['executed'], $changed);
            self::fail('An execution change must fail before the accepted file is explicitly updated.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Executed suite differs', $exception->getMessage());
        }

        $baseline['executed'] = $changed;
        self::assertNotFalse(file_put_contents($baselinePath, json_encode($baseline, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)));
        $updated = AcceptedSuiteAuthority::load($baselinePath);
        AcceptedSuiteAuthority::assertExecuted($updated['executed'], $changed);
        self::assertSame(12, $updated['executed']['tests']);
    }

    /** @return array{tests: int, assertions: int, errors: int, failures: int, skipped: int, incomplete: int} */
    private function executionSummary(): array
    {
        return ['tests' => 11, 'assertions' => 23, 'errors' => 0, 'failures' => 0, 'skipped' => 0, 'incomplete' => 0];
    }
}
