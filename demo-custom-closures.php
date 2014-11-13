<?php

use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Client;
use GuzzleHttp\Event\AbstractRequestEvent;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\EventInterface;
use paslandau\GuzzleApplicationCacheSubscriber\ApplicationCacheSubscriber;
use paslandau\GuzzleApplicationCacheSubscriber\CacheStorage;

require_once __DIR__ . '/../../../vendor/autoload.php';

$cache = new CacheStorage(new ArrayCache());

/**
 * Just some functions / closures for convenience
 */

$getConfigKeyValue = function (AbstractRequestEvent $e, $configKey){
    $request = $e->getRequest();
    return $request->getConfig()->get($configKey);
};

$setConfigKeyValue = function (AbstractRequestEvent $e, $configKey){
    $request = $e->getRequest();
    return $request->getConfig()->set($configKey, true);
};

/**
 * Setup the closures
 */

$mustRequestFreshKey = "requestFresh";
$mustRequestFresh = function(BeforeEvent $event) use ($mustRequestFreshKey, $getConfigKeyValue){
    $val = $getConfigKeyValue($event,$mustRequestFreshKey);
    if($val === null){
        return false;
    }
    if($val){
        echo "Making a fresh request.\n";
        return true;
    }else{
        echo "Trying to serve the response from cache.\n";
        return false;
    }
};

$canCacheRequestKey = "canCacheRequest";
$canCacheRequest = function(EndEvent $event) use ($canCacheRequestKey, $getConfigKeyValue){
    $val = $getConfigKeyValue($event,$canCacheRequestKey);
    if($val === null){
        return true;
    }
    if($val){
        echo "Caching the request/response.\n";
        return true;
    }else{
        echo "Not allowed to cache the request/response.\n";
        return false;
    }
};

$sub = new ApplicationCacheSubscriber($cache, $canCacheRequest, $mustRequestFresh);
$client = new Client();
$client->getEmitter()->attach($sub);

$url = "http://www.example.com/";
$requests = [];

//First request, caching is allowed
$r = $client->createRequest("GET",$url);
$r->getConfig()->add($canCacheRequestKey,true);
$requests[] = $r;
//Second request, get from cache
$r = $client->createRequest("GET",$url);
$r->getConfig()->add($mustRequestFreshKey,false);
$requests[] = $r;
//Third request, force fresh and disallow caching
$r = $client->createRequest("GET",$url);
$r->getConfig()->add($mustRequestFreshKey,true);
$r->getConfig()->add($canCacheRequestKey,false);
$requests[] = $r;
//Fourth request, try to serve from cache and allow caching
$r = $client->createRequest("GET",$url);
$r->getConfig()->add($mustRequestFreshKey,false);
$requests[] = $r;
//Fifth request, get it again from cache
$r = $client->createRequest("GET",$url);
$r->getConfig()->add($mustRequestFreshKey,false);
$requests[] = $r;

foreach ($requests as $i => $request) {
    echo "Request $i\n";
    $resp = $client->send($request);
    if($request->getConfig()->get(ApplicationCacheSubscriber::CACHED_RESPONSE_KEY)) {
        echo "The response came from cache\n\n";
    }else{
        echo "The response came not from cache\n\n";
    }
}