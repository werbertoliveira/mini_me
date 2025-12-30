<?php
header('Content-Type: text/plain; charset=utf-8');
echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "PDO drivers: " . implode(", ", PDO::getAvailableDrivers()) . PHP_EOL;
