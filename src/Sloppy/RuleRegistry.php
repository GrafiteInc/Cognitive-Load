<?php

namespace Grafite\CognitiveLoad\Sloppy;

use Grafite\CognitiveLoad\Sloppy\Rules\FileRule;
use Grafite\CognitiveLoad\Sloppy\Rules\ProjectRule;
use Grafite\CognitiveLoad\Sloppy\Rules\Rule;
use InvalidArgumentException;

class RuleRegistry
{
    /** @var array<string, class-string<Rule>> */
    public const RULES = [
        'SL101' => Rules\SwallowedException::class,
        'SL102' => Rules\NotImplemented::class,
        'SL103' => Rules\PlaceholderComment::class,
        'SL104' => Rules\EmptyMethod::class,
        'SL105' => Rules\DebugLeftover::class,
        'SL201' => Rules\UnusedConstructorDependency::class,
        'SL202' => Rules\UnusedPrivateMethod::class,
        'SL203' => Rules\UnusedPrivateProperty::class,
        'SL204' => Rules\UnusedImport::class,
        'SL205' => Rules\RedundantBooleanReturn::class,
        'SL206' => Rules\ConstantCondition::class,
        'SL207' => Rules\CommentedOutCode::class,
        'SL301' => Rules\QueryInLoop::class,
        'SL302' => Rules\FilterAfterFetch::class,
        'SL303' => Rules\PossibleNPlusOne::class,
        'SL304' => Rules\EnvOutsideConfig::class,
        'SL305' => Rules\FatController::class,
        'SL306' => Rules\UnvalidatedMassAssignment::class,
        'SL401' => Rules\DuplicateMethodBody::class,
    ];

    /**
     * Built-in rules are on unless `enabled => false`. Custom rules are either
     * a list of class names or a map of class name => rule config.
     *
     * @param  array<string, array<string, mixed>>  $rulesConfig
     * @param  array<int|string, mixed>  $customRules
     * @return array<int, FileRule|ProjectRule>
     */
    public function enabled(array $rulesConfig, array $customRules = []): array
    {
        $rules = [];

        foreach (self::RULES as $id => $class) {
            $config = (array) ($rulesConfig[$id] ?? []);

            if (($config['enabled'] ?? true) !== false) {
                $rules[] = new $class($config);
            }
        }

        foreach ($customRules as $key => $value) {
            [$class, $config] = is_int($key) ? [$value, []] : [$key, (array) $value];

            if (($config['enabled'] ?? true) === false) {
                continue;
            }

            $rule = new $class($config);

            if (! $rule instanceof FileRule && ! $rule instanceof ProjectRule) {
                throw new InvalidArgumentException("Sloppy rule [{$class}] must implement FileRule or ProjectRule.");
            }

            $rules[] = $rule;
        }

        return $rules;
    }
}
