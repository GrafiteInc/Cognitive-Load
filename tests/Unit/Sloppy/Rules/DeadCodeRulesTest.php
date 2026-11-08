<?php

namespace Tests\Unit\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\Rules\CommentedOutCode;
use Grafite\CognitiveLoad\Sloppy\Rules\ConstantCondition;
use Grafite\CognitiveLoad\Sloppy\Rules\RedundantBooleanReturn;
use Grafite\CognitiveLoad\Sloppy\Rules\UnusedConstructorDependency;
use Grafite\CognitiveLoad\Sloppy\Rules\UnusedImport;
use Grafite\CognitiveLoad\Sloppy\Rules\UnusedPrivateMethod;
use Grafite\CognitiveLoad\Sloppy\Rules\UnusedPrivateProperty;
use Tests\TestCase;

class DeadCodeRulesTest extends TestCase
{
    use ChecksRules;

    public function test_unused_constructor_dependency(): void
    {
        $findings = $this->check(new UnusedConstructorDependency, <<<'PHP'
            <?php
            class Service {
                public function __construct(
                    private Logger $logger,
                    private Mailer $mailer,
                    protected Cache $cache,
                    string $prefix,
                    string $unused,
                ) {
                    $this->label = $prefix;
                }
                public function send() { $this->mailer->send(); }
            }
            PHP);

        $messages = array_map(fn ($finding) => $finding->message, $findings);

        $this->assertSame(['Promoted dependency $logger is never read.', 'Constructor parameter $unused is never used.'], $messages);
    }

    public function test_unused_constructor_dependency_is_less_confident_when_traits_are_used(): void
    {
        $findings = $this->check(new UnusedConstructorDependency, '<?php class A { use HasLogger; public function __construct(private Logger $logger) {} }');

        $this->assertSame('medium', $findings[0]->confidence);
    }

    public function test_unused_private_method_honours_calls_and_string_callables(): void
    {
        $findings = $this->check(new UnusedPrivateMethod, <<<'PHP'
            <?php
            namespace App;
            class Service {
                public function run() { $this->a(); self::b(); Service::c(); array_map([$this, 'd'], []); }
                private function a() {}
                private static function b() {}
                private static function c() {}
                private function d() {}
                private function orphan() {}
                private function __clone() {}
            }
            PHP);

        $this->assertSame(['App\Service::orphan'], $this->symbols($findings));
    }

    public function test_unused_private_property_ignores_writes(): void
    {
        $findings = $this->check(new UnusedPrivateProperty, <<<'PHP'
            <?php
            class Service {
                private array $written = [];
                private int $read = 0;
                private static $counter = 0;
                protected $shared;
                public function run() { $this->written = [1]; static::$counter++; return $this->read; }
            }
            PHP);

        $this->assertCount(1, $findings);
        $this->assertSame('Private property $written is never read.', $findings[0]->message);
    }

    public function test_unused_import_checks_code_and_docblocks(): void
    {
        $findings = $this->check(new UnusedImport, <<<'PHP'
            <?php
            namespace App;
            use App\Models\User;
            use App\Models\Post;
            use Illuminate\Support\Collection;
            use Illuminate\Support;
            use App\Unused\Thing;
            use function strlen;
            class Service {
                /** @return Collection<int, Post> */
                public function run(User $user) { return Support\Str::of('x'); }
            }
            PHP);

        $this->assertCount(1, $findings);
        $this->assertSame('Imported App\Unused\Thing is never used.', $findings[0]->message);
        $this->assertSame('App\Service', $findings[0]->symbol);
    }

    public function test_redundant_boolean_return(): void
    {
        $findings = $this->check(new RedundantBooleanReturn, <<<'PHP'
            <?php
            class Check {
                public function a($x) { return $x > 1 ? true : false; }
                public function b($x) { if ($x) { return true; } else { return false; } }
                public function c($x) { if ($x) { return false; } return true; }
                public function d($x) { if ($x) { return true; } return true; }
                public function e($x) { return $x ? 'yes' : false; }
            }
            PHP);

        $this->assertSame(['Check::a', 'Check::b', 'Check::c'], $this->symbols($findings));
    }

    public function test_constant_condition_allows_infinite_while_loops(): void
    {
        $findings = $this->check(new ConstantCondition, <<<'PHP'
            <?php
            class Check {
                public function a() { if (false) { debug(); } }
                public function b() { while (true) { break; } }
                public function c() { while (false) { never(); } }
                public function d($x) { return null ? 1 : $x; }
                public function e($x) { if ($x) {} elseif (true) {} }
                public function f($x) { if ($x === true) {} }
            }
            PHP);

        $this->assertSame(['Check::a', 'Check::c', 'Check::d', 'Check::e'], $this->symbols($findings));
    }

    public function test_commented_out_code_ignores_prose_and_annotations(): void
    {
        $findings = $this->check(new CommentedOutCode, <<<'PHP'
            <?php
            class Check {
                public function a() {
                    // $user = User::find(1);
                    // $user->delete();
                    return 1;
                }
                public function b() {
                    // Look up the user and remove them when they are inactive.
                    return 2;
                }
                public function c() {
                    // phpcs:ignore Generic.Something;
                    return 3;
                }
                public function d() {
                    /* if ($legacy) { run(); } */
                    return 4;
                }
            }
            PHP);

        $this->assertSame(['Check::a', 'Check::d'], $this->symbols($findings));
        $this->assertSame(4, $findings[0]->line);
    }
}
