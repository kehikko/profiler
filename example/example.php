<?php

require_once __DIR__ . '/../vendor/autoload.php';

function fetch()
{
    $data = file_get_contents('https://google.com');
    echo 'https://google.com main html content length is ' . strlen($data) . "\n";
}

function yaml()
{
    $content = file_get_contents(__DIR__.'/yaml.yml');
    return Symfony\Component\Yaml\Yaml::parse($content);
}

function loop()
{
    $a = [];
    for ($i = 0; $i < 10000; $i++) {
        $a[] = rand() + $i;
    }
    return $a;
}

/* start profiling here */
profiler_start();

/* do stuff */
fetch();
yaml();
loop();

/* stop is automatically called when script execution stops or could be called earlier manually */
//profiler_stop();
