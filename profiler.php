<?php

function profiler_start()
{
    if (cfg(['profiler', 'enabled']) !== true) {
        return;
    }

    /* this file should not - under no circumstances - interfere with any other application */
    if (!extension_loaded('xhprof') && !extension_loaded('uprofiler') && !extension_loaded('tideways')) {
        error_log('php profiler - either extension xhprof, uprofiler or tideways must be loaded');
        return;
    }
    /* first register shutdown function */
    register_shutdown_function(
        function () {
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
            $time             = array_key_exists('REQUEST_TIME', $_SERVER) ? $_SERVER['REQUEST_TIME'] : time();
            $requestTimeFloat = explode('.', $_SERVER['REQUEST_TIME_FLOAT']);
            if (!isset($requestTimeFloat[1])) {
                $requestTimeFloat[1] = 0;
            }
            $micro        = $requestTimeFloat[1];
            $data['meta'] = array(
                'uri'    => $uri,
                'server' => $_SERVER,
                'get'    => $_GET,
                'post'   => $_POST,
                'env'    => $_ENV,
                'time'   => $time,
                'micro'  => $micro,
            );
            $dir = tr('{path:tmp}/kehikko-profiler');
            if (cfg(['profiler', 'namespace'])) {
                $dir .= '/' . cfg(['profiler', 'namespace']);
            } else {
                $dir .= '/__default__';
            }
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            $file = $dir . '/' . str_pad($time, 16, '0', STR_PAD_LEFT) . '_' . str_pad($micro, 16, '0', STR_PAD_LEFT) . '_' . md5($uri) . '.profile.yml';
            /* dump data */
            $data = Symfony\Component\Yaml\Yaml::dump($data);
            /* write data */
            if (@file_put_contents($file, $data) === false) {
                error_log('kehikko profiler - unable to write profiler data to file: ' . $file);
            }
        }
    );
    /* start profiling */
    if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
    }
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
