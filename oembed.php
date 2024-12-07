<?php

class WpOembed
{
    static private array $timeFactors = [
        's' => 1,
        'm' => 60,
        'h' => 3600,
        'd' => 24*3600,
    ];

    public function request()
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

        // Look for a cache file
        $query = $_SERVER['QUERY_STRING'] ?? '';
        $hash = sha1($query);
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
        // Pass the request to WP
        if ($query === '') {
            $query = '?bypass=1';
        } else {
            $query = "?$query&bypass=1";
        }
        $protocol = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https://' : 'http://';
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $json = file_get_contents("$protocol$host/wp-json/oembed/1.0/embed$query");
        if ($json === false) {
            echo "Call to WP failed";
            http_response_code(500);
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
