<?php

require_once __DIR__ . 'vendor/autoload.php';

/* show errors in browser, easier this way, this is not production stuff anyways */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* this should be given to later functions so that they are able to generate links correctly */
$root_url = '/_profiler';
/* parse profiler "route" url */
$request_url = trim(substr($_SERVER['REQUEST_URI'], strlen($root_url)), '/');

/* path where profiling data is saved (same as given to profiler_start()), following is default */
$datapath = '/tmp/kehikko-php-profiler';
/* limit shown profiling entries to this number, default is 20 */
$limit = 20;

/* "route" */
if ($request_url == '') {
    /* to index */
    echo profiler_html_index($root_url, $datapath, $limit);
} else {
    /* to single call profile */
    $parts = explode('/', $request_url);
    $id    = array_pop($parts);
    if (strpos($request_url, 'graph/') === 0) {
        /* generate svg call graph */
        profiler_svg_graph_generate($id, $datapath);
    } else if (strpos($request_url, 'callgraph/') === 0) {
        /* view call graph */
        echo profiler_html_profile_call_graph($id, $root_url, $datapath);
    } else {
        /* view call profile */
        echo profiler_html_profile($id, $root_url, $datapath);
    }
}
