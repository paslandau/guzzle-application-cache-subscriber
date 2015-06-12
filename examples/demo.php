<?php

use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Client;
use paslandau\GuzzleApplicationCacheSubscriber\ApplicationCacheSubscriber;
use paslandau\GuzzleApplicationCacheSubscriber\CacheStorage;

require_once __DIR__ . '/demo-bootstrap.php';

$cache = new CacheStorage(new ArrayCache());
$sub = new ApplicationCacheSubscriber($cache);
$client = new Client();
$client->getEmitter()->attach($sub);

$num = 5;
$url = "http://www.example.com/";
for ($i = 1; $i <= $num; $i++) {
    echo "Making $i. request:\n";
    $resp = $client->get($url, ["debug" => true]);
    echo "Status code of $i. request: " . $resp->getStatusCode() . "\n";
}