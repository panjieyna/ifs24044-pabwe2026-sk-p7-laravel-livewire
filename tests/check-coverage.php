<?php

$report = simplexml_load_file(__DIR__.'/../coverage.xml');
$metrics = $report->project->metrics;
foreach (['statements', 'methods', 'elements'] as $metric) {
    $total = (int) $metrics[$metric];
    $covered = (int) $metrics['covered'.$metric];
    if ($total === 0 || $covered !== $total) {
        fwrite(STDERR, "Coverage app/ harus 100%: {$metric} {$covered}/{$total}.".PHP_EOL);
        exit(1);
    }
    echo "{$metric}: {$covered}/{$total} (100%)".PHP_EOL;
}
