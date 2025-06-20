<?php

class RateLimiter {
    private $dataDir;
    private $windowSeconds;
    private $maxRequests;

    public function __construct($windowSeconds = 60, $maxRequests = 10) {
        $this->dataDir = __DIR__ . '/../data/rate_limits/';
        $this->windowSeconds = $windowSeconds;
        $this->maxRequests = $maxRequests;

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }

    public function isAllowed($identifier) {
        $file = $this->dataDir . md5($identifier) . '.json';
        $now = time();
        $window = [];

        if (file_exists($file)) {
            $window = json_decode(file_get_contents($file), true);
            // Remove old timestamps
            $window = array_filter($window, function($timestamp) use ($now) {
                return $timestamp > ($now - $this->windowSeconds);
            });
        }

        if (count($window) >= $this->maxRequests) {
            return false;
        }

        $window[] = $now;
        file_put_contents($file, json_encode($window));
        return true;
    }
}
