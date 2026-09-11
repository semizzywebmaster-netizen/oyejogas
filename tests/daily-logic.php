#!/usr/bin/env php
<?php
/**
 * Pure-function checks for daily reward math (no database).
 */
define('OYEJO_BOOT', true);
require dirname(__DIR__) . '/includes/daily.php';

$fail = 0;
$pass = 0;
function ok($cond, $label) {
    global $fail, $pass;
    if ($cond) {
        $pass++;
        echo "PASS  $label\n";
    } else {
        $fail++;
        echo "FAIL  $label\n";
    }
}

$cfg = daily_defaults();
[$base, $step, $week, $mystery, $total] = daily_compute_reward(1, $cfg, 101);
ok($base === 5000 && $step === 0 && $week === 0 && $mystery === 0 && $total === 5000, 'day 1: base only, no mystery');

[$base, $step, $week, $mystery, $total] = daily_compute_reward(2, $cfg, 101);
ok($step === 1500 && $total === 6500, 'day 2: +one streak step');

[$base, $step, $week, $mystery, $total] = daily_compute_reward(7, $cfg, 101);
ok($week === 25000 && $step === 1500 * 6 && $total === 5000 + 9000 + 25000, 'day 7: week bonus + capped steps');

[$base, $step, $week, $mystery, $total] = daily_compute_reward(8, $cfg, 101);
ok($week === 0 && $step === 1500 * 6, 'day 8: week bonus only on multiples of 7');

[$base, $step, $week, $mystery, $total] = daily_compute_reward(1, $cfg, 1);
ok($mystery >= 5000 && $mystery <= 20000 && $total === 5000 + $mystery, 'mystery roll 1 hits bonus');

echo "\n==== $pass passed, $fail failed ====\n";
exit($fail > 0 ? 1 : 0);
