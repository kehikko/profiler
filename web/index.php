<?php

require_once __DIR__ . '/../vendor/autoload.php';

/* show errors in browser, easier this way, this is not production stuff anyways */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* parse profiler "route" url */
$request_url = trim($_SERVER['REQUEST_URI'], '/');

/* "route" */
if ($request_url == '') {
    /* to index */
    profiler_html_index();
} else {
    /* to single call profile */
    $parts = explode('/', $request_url);
    $id    = array_pop($parts);
    if (strpos($request_url, 'graph/') === 0) {
        /* generate svg call graph */
        profiler_svg_graph_generate($id);
    } else if (strpos($request_url, 'callgraph/') === 0) {
        /* view call graph */
        profiler_html_profile_call_graph($id);
    } else {
        /* view call profile */
        profiler_html_profile($id);
    }
}
