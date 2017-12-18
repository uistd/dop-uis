<?php

use UiStd\Uis\Work\CrontabConfig;

require_once '../vendor/autoload.php';

print_r(new CrontabConfig('* * * * *'));
print_r(new CrontabConfig('*/5 * * * *'));
print_r(new CrontabConfig('13-30/5 * * * *'));
print_r(new CrontabConfig('13-30 * * * *'));
print_r(new CrontabConfig('* 1 * * *'));
print_r(new CrontabConfig('* 1 2,4,5 * *'));
print_r(new CrontabConfig('1-3,5-7,6-9/2 1 2,4,5 * *'));