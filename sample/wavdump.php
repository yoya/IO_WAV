<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/WAV.php';
}

$options = getopt("f:h");

function usage() {
    echo "Usage: php wavdump.php [-h] [-D] [-S] -f <wavfile>".PHP_EOL;
}

if ((isset($options['f']) === false) ||
    (is_readable($options['f']) === false)) {
    usage();
    exit(1);
}
$opts = array();

$opts['hexdump'] = isset($options['h']);

$wavfile = $options['f'];
$wavdata = file_get_contents($wavfile);

$wav = new IO_WAV();

try {
    $wav->parse($wavdata);
} catch (Exception $e) {
    echo "Exception".$e->getMessage().PHP_EOL;
}

$wav->dump($opts);

exit(0);
