<?php

namespace Tests\Unit\Sloppy;

use Grafite\CognitiveLoad\Sloppy\Baseline\SloppyBaselineComparator;
use Grafite\CognitiveLoad\Sloppy\Finding;
use Grafite\CognitiveLoad\Sloppy\Severity;
use Grafite\CognitiveLoad\Sloppy\SloppyReport;
use Tests\TestCase;

class SloppyBaselineComparatorTest extends TestCase
{
    private function finding(string $fingerprint, Severity $severity): Finding
    {
        return new Finding('SL101', 'Swallowed exception', $severity, 'high', 'app/A.php', 1, 'A::run', 'm', 'a', 'hash', $fingerprint);
    }

    private function report(Finding ...$findings): SloppyReport
    {
        return new SloppyReport('now', ['app'], 1, $findings, []);
    }

    public function test_known_findings_pass_and_missing_ones_are_resolved(): void
    {
        $baseline = ['findings' => ['aaa' => [], 'bbb' => []]];

        $result = (new SloppyBaselineComparator)->compare($this->report($this->finding('aaa', Severity::High)), $baseline, Severity::Medium);

        $this->assertTrue($result->passed);
        $this->assertSame([], $result->newFindings);
        $this->assertSame(['bbb'], $result->resolvedFingerprints);
    }

    public function test_new_findings_fail_only_at_or_above_the_gate_severity(): void
    {
        $comparator = new SloppyBaselineComparator;

        $low = $comparator->compare($this->report($this->finding('new', Severity::Low)), [], Severity::Medium);
        $medium = $comparator->compare($this->report($this->finding('new', Severity::Medium)), [], Severity::Medium);

        $this->assertTrue($low->passed);
        $this->assertCount(1, $low->newFindings);
        $this->assertFalse($medium->passed);
        $this->assertSame('medium', $medium->toArray()['fail_on']);
    }
}
