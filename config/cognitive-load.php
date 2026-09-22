<?php

/*
|--------------------------------------------------------------------------
| Cognitive Load Config
|--------------------------------------------------------------------------
*/

return [

    // Directories/files scanned by default when no path is given on the CLI.
    'paths' => [app_path()],

    // Regex, matched against each scanned file's path relative to the scan root.
    'exclude' => [
        '#/vendor/#',
        '#/tests/#',
        '#/database/migrations/#',
    ],

    // Regex, matched against the filename only.
    'exclude_files' => [],

    // Regex, matched against the resolved fully-qualified class name.
    'exclude_classes' => [],

    // Per-method metrics. Each contributes 0 while at/below its threshold,
    // then grows logarithmically past it (see Scoring\MetricScorer).
    'metrics' => [
        'line_count' => ['enabled' => true, 'threshold' => 20, 'scale' => 4.0],
        'argument_count' => ['enabled' => true, 'threshold' => 4, 'scale' => 3.0],
        'return_count' => ['enabled' => true, 'threshold' => 3, 'scale' => 2.0],
        'variable_count' => ['enabled' => true, 'threshold' => 8, 'scale' => 3.0],
        'property_access_count' => ['enabled' => true, 'threshold' => 5, 'scale' => 2.0],
        'if_count' => ['enabled' => true, 'threshold' => 3, 'scale' => 3.0],
        'if_nesting_depth' => ['enabled' => true, 'threshold' => 2, 'scale' => 5.0],
        'else_count' => ['enabled' => true, 'threshold' => 2, 'scale' => 2.0],
        'cyclomatic_complexity' => ['enabled' => true, 'threshold' => 5, 'scale' => 3.0],
    ],

    // Class-level structural metrics feeding the Architecture category.
    'architecture_metrics' => [
        'class_loc' => ['threshold' => 200, 'scale' => 4.0],
        'method_count' => ['threshold' => 10, 'scale' => 3.0],
        'coupling_count' => ['threshold' => 6, 'scale' => 3.0],
        'inheritance_depth' => ['threshold' => 2, 'scale' => 4.0],
    ],

    // k in `100 * exp(-raw / k)` — how quickly each category decays from 100
    // as its raw penalty accumulates.
    'category_scaling' => [
        'complexity_decay' => 40.0,
        'architecture_decay' => 30.0,
    ],

    // How the two category scores combine into one overall 0-100 score.
    'category_weights' => [
        'complexity' => 0.6,
        'architecture' => 0.4,
    ],

    // Shared tier thresholds (ascending) for both label vocabularies below.
    'bands' => [
        'thresholds' => [40, 60, 80],
        'risk_labels' => ['VeryHigh', 'High', 'Medium', 'Low'],
        'grade_labels' => ['Critical', 'Poor', 'Fair', 'Good'],
    ],

    'baseline' => [
        'path' => base_path('cognitive-load-baseline.json'),
    ],

    // CI/CD gate margins: how much an overall or per-class score is allowed
    // to drop from the baseline before `--fail-on-regression` fails the build.
    'gate' => [
        'overall_margin' => 5.0,
        'class_margin' => 8.0,
        'fail_if_baseline_missing' => false,
    ],

];
