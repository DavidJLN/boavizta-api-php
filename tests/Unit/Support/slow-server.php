<?php

// Router for `php -S`: answers, but too late for any reasonable timeout.
sleep(5);
header('Content-Type: application/json');
echo '"2.4.1"';
