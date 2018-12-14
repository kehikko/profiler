<?php

static $profiler_file       = null;
static $profiler_start_time = null;

function profiler_running()
{
    global $profiler_file;
    return is_string($profiler_file);
}

function profiler_start($datapath = '/tmp/kehikko-php-profiler')
{
    /* this file should not - under no circumstances - interfere with any other application */
    if (!extension_loaded('xhprof') && !extension_loaded('uprofiler') && !extension_loaded('tideways')) {
        error_log('php profiler - either extension xhprof, uprofiler or tideways must be loaded');
        return;
    }
    /* do no restart */
    if (profiler_running()) {
        return;
    }
    /* generate some profiling info here already */
    global $profiler_file;
    global $profiler_start_time;
    $profiler_start_time = isset($_SERVER['REQUEST_TIME_FLOAT']) ? floatval($_SERVER['REQUEST_TIME_FLOAT']) : microtime();
    /* create data path if it does not exist */
    if (!is_dir($datapath)) {
        @mkdir($datapath, 0700, true);
    }
    $profiler_file = $datapath . '/' . sprintf('%012.3f', $profiler_start_time) . '_' . uniqid() . '.profile.yml';
    /* first register shutdown function */
    register_shutdown_function('profiler_stop');
    /* start profiling */
    if (extension_loaded('uprofiler')) {
        uprofiler_enable(UPROFILER_FLAGS_CPU | UPROFILER_FLAGS_MEMORY);
    } else if (extension_loaded('tideways')) {
        tideways_enable(TIDEWAYS_FLAGS_CPU | TIDEWAYS_FLAGS_MEMORY | TIDEWAYS_FLAGS_NO_SPANS | TIDEWAYS_FLAGS_NO_BUILTINS);
    } else {
        if (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION > 4) {
            xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY | XHPROF_FLAGS_NO_BUILTINS);
        } else {
            xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
        }
    }
}

function profiler_stop()
{
    /* do no stop if not started */
    if (!profiler_running()) {
        return;
    }
    global $profiler_file;
    global $profiler_start_time;
    /* gather data */
    $data = array();
    if (extension_loaded('uprofiler')) {
        $data['profile'] = uprofiler_disable();
    } else if (extension_loaded('tideways')) {
        $data['profile'] = tideways_disable();
    } else {
        $data['profile'] = xhprof_disable();
    }
    /* ignore_user_abort(true) allows your PHP script to continue executing, even if the user has terminated their request */
    ignore_user_abort(true);
    flush();
    $uri = null;
    if (array_key_exists('REQUEST_URI', $_SERVER) && array_key_exists('SERVER_NAME', $_SERVER)) {
        $uri = $_SERVER['SERVER_NAME'];
        if (isset($_SERVER['HTTPS'])) {
            $uri = 'https://' . $uri;
        } else {
            $uri = 'http://' . $uri;
        }
        if (isset($_SERVER['PORT'])) {
            $uri .= $_SERVER['PORT'] != 80 ? $_SERVER['PORT'] : '';
        }
        $uri .= $_SERVER['REQUEST_URI'];
    }
    if (empty($uri) && isset($_SERVER['argv'])) {
        $cmd = basename($_SERVER['argv'][0]);
        $uri = $cmd . ' ' . implode(' ', array_slice($_SERVER['argv'], 1));
    }
    $data['meta'] = array(
        'uri'    => $uri,
        'server' => $_SERVER,
        'get'    => $_GET,
        'post'   => $_POST,
        'env'    => $_ENV,
        'time'   => $profiler_start_time,
    );
    /* dump data */
    $data = Symfony\Component\Yaml\Yaml::dump($data);
    /* write data */
    if (@file_put_contents($profiler_file, $data) === false) {
        error_log('kehikko profiler - unable to write profiler data to file: ' . $profiler_file);
    }
    $profiler_file       = null;
    $profiler_start_time = null;
}

function profiler_html_index($root_url = '/', $datapath = '/tmp/kehikko-php-profiler', $limit = 20)
{
    $profiles = [];

    if (is_dir($datapath)) {
        $files = scandir($datapath, SCANDIR_SORT_DESCENDING);
        foreach ($files as $file) {
            $filepath = $datapath . '/' . $file;
            if (is_dir($filepath) || $file[0] == '.' || substr($file, -4) != '.yml') {
                continue;
            }
            $content = @file_get_contents($filepath);
            try {
                $profile = Symfony\Component\Yaml\Yaml::parse($content);
                if (!empty($profile) && is_array($profile)) {
                    $profile['id']    = $file;
                    $profile['total'] = array(
                        'ct'  => $profile['profile']['main()']['ct'],
                        'wt'  => $profile['profile']['main()']['wt'],
                        'cpu' => $profile['profile']['main()']['cpu'],
                        'mu'  => $profile['profile']['main()']['mu'],
                        'pmu' => $profile['profile']['main()']['pmu'],
                    );
                    $profiles[] = $profile;
                }
                $limit--;
                if ($limit < 1) {
                    break;
                }
            } catch (Throwable $e) {
                /* unable to parse file */
            }
        }
    }

    $twig_loader = new Twig_Loader_Filesystem(__DIR__ . '/views');
    $twig        = new Twig_Environment($twig_loader);

    echo $twig->render('kehikko-profiler-profiles.html.twig', ['profiles' => $profiles, 'root_url' => $root_url]);
}

function profiler_html_profile($id, $root_url = '/', $datapath = '/tmp/kehikko-php-profiler')
{
    $twig_loader = new Twig_Loader_Filesystem(__DIR__ . '/views');
    $twig        = new Twig_Environment($twig_loader);
    echo $twig->render('kehikko-profiler-profile.html.twig', ['profile' => profiler_profile_load($id, $datapath), 'root_url' => $root_url]);
}

function profiler_html_profile_call_graph($id, $root_url = '/', $datapath = '/tmp/kehikko-php-profiler')
{
    $twig_loader = new Twig_Loader_Filesystem(__DIR__ . '/views');
    $twig        = new Twig_Environment($twig_loader);
    echo $twig->render('kehikko-profiler-profile-call-graph.html.twig', ['profile' => profiler_profile_load($id, $datapath), 'root_url' => $root_url]);
}

function profiler_profile_load($id, $datapath = '/tmp/kehikko-php-profiler')
{
    $file = $datapath . '/' . $id;
    if (!file_exists($file)) {
        throw new Exception('Profile data not found from: ' . $file);
    }

    $content = @file_get_contents($file);
    $profile = Symfony\Component\Yaml\Yaml::parse($content);
    if (empty($profile) || !is_array($profile)) {
        throw new Exception('Profile data could not be loaded from: ' . $file);
    }

    $data = array();
    /* parse profile functions */
    foreach ($profile['profile'] as $call => $info) {
        $caller    = null;
        $called    = null;
        $functions = explode('==>', $call, 2);
        if (count($functions) == 2) {
            $caller = $functions[0];
            $called = $functions[1];
        } else {
            $called = $functions[0];
        }
        if (isset($data[$called])) {
            $data[$called]['ct'] += intval($info['ct']);
            $data[$called]['wt'] += intval($info['wt']);
            $data[$called]['cpu'] += intval($info['cpu']);
            $data[$called]['mu'] += intval($info['mu']);
            $data[$called]['pmu'] += intval($info['pmu']);
            $data[$called]['ewt']  = $data[$called]['wt'];
            $data[$called]['ecpu'] = $data[$called]['cpu'];
            $data[$called]['emu']  = $data[$called]['mu'];
            $data[$called]['epmu'] = $data[$called]['pmu'];
        } else {
            $data[$called]            = array();
            $data[$called]['ct']      = intval($info['ct']);
            $data[$called]['wt']      = intval($info['wt']);
            $data[$called]['cpu']     = intval($info['cpu']);
            $data[$called]['mu']      = intval($info['mu']);
            $data[$called]['pmu']     = intval($info['pmu']);
            $data[$called]['ewt']     = intval($info['wt']);
            $data[$called]['ecpu']    = intval($info['cpu']);
            $data[$called]['emu']     = intval($info['mu']);
            $data[$called]['epmu']    = intval($info['pmu']);
            $data[$called]['callers'] = array($caller);
            $data[$called]['calls']   = array();
        }
    }
    /* parse exclusives */
    foreach ($profile['profile'] as $call => $info) {
        $functions = explode('==>', $call, 2);
        if (count($functions) != 2) {
            continue;
        }
        $caller = $functions[0];
        $called = $functions[1];
        if (!isset($data[$caller])) {
            continue;
        }
        $data[$caller]['ewt'] -= intval($info['wt']);
        $data[$caller]['ecpu'] -= intval($info['cpu']);
        $data[$caller]['emu'] -= intval($info['mu']);
        $data[$caller]['epmu'] -= intval($info['pmu']);
    }
    /* parse calls */
    foreach ($profile['profile'] as $call => $info) {
        $functions = explode('==>', $call, 2);
        if (count($functions) != 2) {
            continue;
        }
        $caller = $functions[0];
        $called = $functions[1];
        if (!isset($data[$caller]) || !isset($data[$called])) {
            continue;
        }

        $data[$caller]['calls'][$called] = $info;
    }
    /* memory/wall time hogs */
    $by_wt  = array();
    $by_ewt = array();
    $by_mu  = array();
    $by_emu = array();
    foreach ($data as $function => $info) {
        $by_wt[$info['wt']]   = $function;
        $by_ewt[$info['ewt']] = $function;
        $by_mu[$info['mu']]   = $function;
        $by_emu[$info['emu']] = $function;
    }
    krsort($by_wt, SORT_NUMERIC);
    krsort($by_ewt, SORT_NUMERIC);
    krsort($by_mu, SORT_NUMERIC);
    krsort($by_emu, SORT_NUMERIC);
    ksort($data);
    $profile['id']      = $id;
    $profile['profile'] = $data;
    $profile['by']      = array(
        'wt'  => $by_wt,
        'ewt' => $by_ewt,
        'mu'  => $by_mu,
        'emu' => $by_emu,
    );
    $profile['total'] = array(
        'ct'  => $profile['profile']['main()']['ct'],
        'wt'  => $profile['profile']['main()']['wt'],
        'cpu' => $profile['profile']['main()']['cpu'],
        'mu'  => $profile['profile']['main()']['mu'],
        'pmu' => $profile['profile']['main()']['pmu'],
    );

    return $profile;
}

function profiler_svg_graph_generate($id, $datapath = '/tmp/kehikko-php-profiler')
{
    $svg_file = $datapath . '/' . $id . '.svg';
    if (file_exists($svg_file)) {
        /* svg file has already been created, just output the same stuff from previous run */
        header('Content-type: image/svg+xml');
        readfile($svg_file);
        return;
    }

    $tmp_data_file = $datapath . '/' . $id . '.tmpdata';
    $data          = profiler_profile_load($id, $datapath);

    $f = fopen($tmp_data_file, 'w');
    if (!$f) {
        throw new Exception('profiler unable to open temporary data file for creating svg: ' . $tmp_data_file);
    }

    fwrite($f, 'digraph callgraph { splines=true; node [shape="box" style="rounded"];');
    /* list all nodes we want to include */
    $nodes     = array();
    $nodes_all = array();
    profiler_svg_create_nodes($data['profile'], 'main()', $nodes, $nodes_all);
    /* write basic information for all nodes */
    $wt_max = $data['profile']['main()']['wt'];
    foreach ($nodes_all as $function => $node) {
        $info  = $data['profile'][$function];
        $r     = 0xff - intval(0x10 * $info['wt'] / $wt_max);
        $g     = 0xff - intval(0xff * $info['wt'] / $wt_max);
        $b     = 0xff - intval(0x30 * $info['wt'] / $wt_max);
        $color = sprintf('#%02x%02x%02x', $r, $g, $b);
        fwrite($f, $node . ' [style="filled,rounded" fillcolor="' . $color . '" label="' . $function . "\nwt: " . number_format($info['wt']) . ' uS"];');
    }
    /* create callgraph links */
    foreach ($nodes as $node) {
        fwrite($f, $node['from'] . ' -> ' . $node['to'] . '[color="#00a070" label="' . $node['info']['ct'] . ' call' . ($node['info']['ct'] > 1 ? 's' : '') . '"];');
    }
    fwrite($f, '}');
    fclose($f);

    /* generate svg image */
    $cmd = 'dot -Tsvg -o' . escapeshellarg($svg_file) . ' ' . escapeshellarg($tmp_data_file);
    exec($cmd, $output, $r);
    unlink($tmp_data_file);
    if ($r !== 0) {
        throw new Exception('profiler failed to create svg image: ' . $svg_file);
    }
    header('Content-type: image/svg+xml');
    if (readfile($svg_file) === false) {
        error_log('profiler failed to read svg image: ' . $svg_file);
    }
}

function profiler_svg_create_nodes($profile, $function, &$nodes, &$nodes_all, $recursion_limit = 25)
{
    if ($recursion_limit < 1) {
        error_log('recursion limit reached in profiler_svg_create_nodes(), current function: ' . $function);
        return;
    }
    $wt_max               = $profile['main()']['wt'];
    $search               = array(':', '@', '\\', '(', ')', '{', '}');
    $from                 = str_replace($search, '_', $function);
    $nodes_all[$function] = $from;
    $calls                = $profile[$function]['calls'];
    foreach ($calls as $call => $info) {
        if (($profile[$call]['wt'] / $wt_max) < 0.01) {
            continue;
        }
        $to           = str_replace($search, '_', $call);
        $nodes[$call] = array(
            'from' => $from,
            'to'   => $to,
            'info' => $info,
        );
        $nodes_all[$call] = $to;
        profiler_svg_create_nodes($profile, $call, $nodes, $nodes_all, $recursion_limit - 1);
    }
}
