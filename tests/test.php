<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Neskodi\SSHCommander\SSHCommander;

try {
    $c = new SSHCommander([
        // 'host' => 'localhost',

        'host' => '192.168.11.2',
        // 'host' => '172.16.254.1',
        'user' => 'vagrant',
        'keyfile' => 'i:/Dropbox/Personal/Documents and Settings/Keys/SSH/sn',

        'basedir' => '/var/www/vhosts',
        'timeout_connect' => 3,

        'log_file' => 'E:/temp/sshcommander.log.txt',
        'log_level' => 'debug',
    ]);

    $result = $c->run('ls -lA');
    $result = $c->run('whoami');
    echo $result->getStatus() . PHP_EOL;
    echo $result->getOutput(true) . PHP_EOL;
} catch (Throwable $e) {
    echo $e->getMessage();
}
