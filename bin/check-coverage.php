<?php

/**
 * Fails (exit 1) when line coverage in a Clover report is below a minimum.
 *
 * Usage: php bin/check-coverage.php <clover.xml> <min-percent>
 */

if ($argc < 3 || !is_readable($argv[1])) {
    fwrite(STDERR, "Usage: php bin/check-coverage.php <clover.xml> <min-percent>\n");
    exit(2);
}

$metrics = simplexml_load_file($argv[1])->project->metrics;
$total   = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $total > 0 ? $covered / $total * 100 : 0;
$minimum = (float) $argv[2];

printf("PHP line coverage: %.2f%% (%d/%d lines). Minimum: %s%%.\n", $percent, $covered, $total, $minimum);

exit($percent >= $minimum ? 0 : 1);
