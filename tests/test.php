<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Neskodi\SSHCommander\SSHCommander;

$c = new SSHCommander([
    // 'host' => 'localhost',

    'host' => '192.168.11.2',
    'user' => 'vagrant',
    'keyfile' => 'i:/Dropbox/Personal/Documents and Settings/Keys/SSH/sn',

    'basedir' => '/var/www/vhosts',
]);

$result = $c->run('ls -lA');

echo $result->getStatus() . PHP_EOL;
echo $result->getOutput(true) . PHP_EOL;


