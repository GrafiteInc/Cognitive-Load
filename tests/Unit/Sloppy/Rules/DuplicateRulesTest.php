<?php

namespace Tests\Unit\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\Rules\DuplicateMethodBody;
use Tests\TestCase;

class DuplicateRulesTest extends TestCase
{
    use ChecksRules;

    private const BODY = '$a = 1; $b = 2; $c = $a + $b; $d = $c * 2; return $d;';

    public function test_it_flags_each_extra_copy_across_files_ignoring_variable_names_and_comments(): void
    {
        $renamed = '// copied
            $x = 1; $y = 2; $z = $x + $y; $w = $z * 2; return $w;';

        $findings = (new DuplicateMethodBody)->checkProject([
            $this->context('<?php class A { public function one() { '.self::BODY.' } }', 'app/A.php'),
            $this->context("<?php class B { public function two() { {$renamed} } }", 'app/B.php'),
            $this->context('<?php class C { public function three() { '.self::BODY.' } }', 'app/C.php'),
        ]);

        $this->assertSame(['B::two', 'C::three'], $this->symbols($findings));
        $this->assertSame('Body duplicates A::one (app/A.php:1) and 1 other location(s).', $findings[0]->message);
    }

    public function test_it_ignores_short_bodies_and_real_differences(): void
    {
        $findings = (new DuplicateMethodBody)->checkProject([
            $this->context('<?php class A { public function one() { '.self::BODY.' } public function short() { return 1; } }'),
            $this->context('<?php class B { public function two() { $a = 1; $b = 2; $c = $a - $b; $d = $c * 2; return $d; } public function short() { return 1; } }', 'app/B.php'),
        ]);

        $this->assertSame([], $findings);
    }

    public function test_min_statements_is_configurable(): void
    {
        $findings = (new DuplicateMethodBody(['min_statements' => 1]))->checkProject([
            $this->context('<?php class A { public function one() { return $this->a + 1; } }', 'app/A.php'),
            $this->context('<?php class B { public function two() { return $this->a + 1; } }', 'app/B.php'),
        ]);

        $this->assertCount(1, $findings);
    }
}
