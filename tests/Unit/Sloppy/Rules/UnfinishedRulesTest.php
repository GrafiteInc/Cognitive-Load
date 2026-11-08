<?php

namespace Tests\Unit\Sloppy\Rules;

use Grafite\CognitiveLoad\Sloppy\Rules\DebugLeftover;
use Grafite\CognitiveLoad\Sloppy\Rules\EmptyMethod;
use Grafite\CognitiveLoad\Sloppy\Rules\NotImplemented;
use Grafite\CognitiveLoad\Sloppy\Rules\PlaceholderComment;
use Grafite\CognitiveLoad\Sloppy\Rules\SwallowedException;
use Grafite\CognitiveLoad\Sloppy\Severity;
use Tests\TestCase;

class UnfinishedRulesTest extends TestCase
{
    use ChecksRules;

    public function test_swallowed_exception_flags_empty_catches_only(): void
    {
        $findings = $this->check(new SwallowedException, <<<'PHP'
            <?php
            namespace App;
            class Job {
                public function empty() { try { run(); } catch (\RuntimeException $e) {} }
                public function commented() { try { run(); } catch (\Throwable $e) { // ignore
                } }
                public function handled() { try { run(); } catch (\Throwable $e) { report($e); } }
            }
            PHP);

        $this->assertSame(['App\Job::empty', 'App\Job::commented'], $this->symbols($findings));
        $this->assertSame(Severity::High, $findings[0]->severity);
        $this->assertSame(Severity::Low, $findings[1]->severity);
    }

    public function test_not_implemented_flags_placeholder_throws(): void
    {
        $findings = $this->check(new NotImplemented, <<<'PHP'
            <?php
            class Service {
                public function a() { throw new \LogicException('Not implemented'); }
                public function b() { throw new \Exception('TODO'); }
                public function c() { throw new \InvalidArgumentException('Invalid id'); }
            }
            PHP);

        $this->assertSame(['Service::a', 'Service::b'], $this->symbols($findings));
    }

    public function test_placeholder_comment_escalates_when_it_is_the_whole_body(): void
    {
        $findings = $this->check(new PlaceholderComment, <<<'PHP'
            <?php
            class Service {
                public function stub() {
                    // TODO: implement
                    return null;
                }
                public function real() {
                    // FIXME: handle negatives
                    return abs(-1);
                }
                public function prose() {
                    // Mastodon is not a placeholder
                    return 1;
                }
            }
            PHP);

        $this->assertSame(['Service::stub', 'Service::real'], $this->symbols($findings));
        $this->assertSame(Severity::Medium, $findings[0]->severity);
        $this->assertSame(Severity::Low, $findings[1]->severity);
        $this->assertSame(4, $findings[0]->line);
    }

    public function test_empty_method_skips_abstract_promoted_constructors_and_commented_bodies(): void
    {
        $findings = $this->check(new EmptyMethod, <<<'PHP'
            <?php
            abstract class Base {
                public function __construct(private int $id) {}
                abstract public function handle(): void;
                public function boot(): void {}
                public function intentional(): void {
                    // Subclasses may override.
                }
            }
            interface Contract { public function run(): void; }
            PHP);

        $this->assertSame(['Base::boot'], $this->symbols($findings));
    }

    public function test_debug_leftover_flags_dump_helpers_but_not_returned_output(): void
    {
        $findings = $this->check(new DebugLeftover, <<<'PHP'
            <?php
            class Service {
                public function a($x) { dd($x); }
                public function b($x) { \var_dump($x); }
                public function c($x) { return print_r($x, true); }
                public function d($q) { return $q->where('a', 1)->dd(); }
                public function e($dumper) { $dumper->dump('x'); }
            }
            PHP);

        $this->assertSame(['Service::a', 'Service::b', 'Service::d'], $this->symbols($findings));
    }

    public function test_debug_leftover_function_list_is_configurable(): void
    {
        $findings = $this->check(new DebugLeftover(['functions' => ['logger']]), '<?php dd(1); logger("x");');

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('logger()', $findings[0]->message);
    }

    public function test_configured_severity_overrides_the_default(): void
    {
        $findings = $this->check(new SwallowedException(['severity' => 'low']), '<?php try { a(); } catch (\Exception $e) {}');

        $this->assertSame(Severity::Low, $findings[0]->severity);
    }
}
