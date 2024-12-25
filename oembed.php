<?php
/**
 * Copyright 2024 Alan Langford
 * https://github.com/instancezero
 * https://ambitonline.com
 *
 * This code is licensed under the GPL, version 3 or later.
 * You are free to distribute and modify it under the terms of the GPLv3.
 * You may add a notice to claim copyright over your modifications,
 * but you may not remove my copyright notice.
 */

class WpOembed
{
    static private array $timeFactors = [
        's' => 1,
        'm' => 60,
        'h' => 3600,
        'd' => 24*3600,
    ];

    public function request(): void
    {
        if ($_GET['bypass'] ?? false) {
            echo "Not bypassed, check access rules.";
            http_response_code(500);
            return;
        }
        $config = json_decode(@file_get_contents(__DIR__ . '/.oembed.json'), true);
        if ($config === null) {
            $config = [
                'cachePath' => '/wp-content/cache/oembed',
                'lifetime'=> '4d',
            ];
        }

        // Look for a cache file, get the decoded URL and hash that to get the cache file name.
        $query = $_SERVER['QUERY_STRING'] ?? '';
        $url = $_GET['url'];
        if (!preg_match('!https?:!', $url)) {
            $url = urldecode($url);
        }
        $hash = sha1($url);
        $cacheFile = $_SERVER['DOCUMENT_ROOT'] . $config['cachePath'] . "/$hash.cache";
        $now = time();
        if (file_exists($cacheFile)) {
            $cacheData = file_get_contents($cacheFile);
            $parts = explode("\n", $cacheData, 3);
            if ($now < (int)$parts[0]) {
                // Cache is valid, return the data.
                header('content-type: application/json; charset=UTF-8');
                echo $parts[2];
                return;
            }
        }
        // Nothing in the cache, Pass the request to WP
        if ($query === '') {
            $query = '?bypass=1';
        } else {
            $query = "?$query&bypass=1";
        }
        $protocol = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https://' : 'http://';
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $json = file_get_contents("$protocol$host/wp-json/oembed/1.0/embed$query");
        if ($json === false) {
            http_response_code(500);
            echo "Server error. Request failed.";
            return;
        }
        // Write or rewrite the cache file
        $expires = time() + $this->timeToSeconds($config['lifetime']);
        if (!file_exists(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), recursive: true);
        }
        file_put_contents(
            $cacheFile,
            "$expires\n" . urlencode($_GET['url'] ?? '') . "\n$json"
        );
        header('content-type: application/json; charset=UTF-8');
        echo $json;
    }

    private function timeToSeconds(string $span): int
    {
        $units = substr($span, -1);
        if (is_numeric($units)) {
            return (int) $span;
        }
        $value = (int) substr($span, 0, -1);
        return $value * (self::$timeFactors[$units] ?? 1);
    }

}

$o = new WpOembed();
$o->request();
