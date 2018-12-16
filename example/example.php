<?php

require_once __DIR__ . '/../vendor/autoload.php';

function fetch()
{
    $data = file_get_contents('https://google.com');
    echo 'https://google.com main html content length is ' . strlen($data) . "\n";
}

function json()
{
    $content = file_get_contents(__DIR__.'/json.json');
    return json_decode($content, true);
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
json();
loop();

/* stop is automatically called when script execution stops or could be called earlier manually */
//profiler_stop();
