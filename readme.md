#guzzle-application-cache-subscriber
[![Build Status](https://travis-ci.org/paslandau/guzzle-application-cache-subscriber.svg?branch=master)](https://travis-ci.org/paslandau/guzzle-application-cache-subscriber)

Plugin for [Guzzle 5](https://github.com/scripts/guzzle) to cache requests/responses on an application level scope. 
This is different from the [CacheSubscriber](https://github.com/guzzle/cache-subscriber) because it doesn't adhere to HTTP caching standards
but leaves it to the developer to decide what to cache and when to request a fresh response. Although I shamelessly used the 
[CacheStorage](https://github.com/guzzle/cache-subscriber/blob/0.1.0/src/CacheStorage.php) from said plugin :)

##Description

This plugin works as a transparent proxy for requests made to the same URL using the same HTTP verb. I use it frequently when developing 
API wrappers to avoid making multiple calls to the same API endpoint. This is particularly helpful in cases where the 
API usage is restricted to a certain amount of requests. 

The underlying cache library is [Doctrine/Cache](https://github.com/doctrine/cache) and I like to use the
[FilesystemCache](https://github.com/doctrine/cache/blob/v1.3.1/lib/Doctrine/Common/Cache/FilesystemCache.php) to store responses on disk
and have it available on the next test run.

###Basic Usage
```php

$cache = new CacheStorage(new ArrayCache());
$sub = new ApplicationCacheSubscriber($cache);
$client = new Client();
$client->getEmitter()->attach($sub);

$num = 5;
$url = "http://www.example.com/";
for ($i = 1; $i <= $num; $i++) {
    echo "Making $i. request:\n";
    $resp = $client->get($url, ["debug" => true]);
    echo "Status code of $i. request: ".$resp->getStatusCode()."\n";
}
```

**Output**

    Making 1. request:
    * Hostname was NOT found in DNS cache
    *   Trying 93.184.216.119...
    * Connected to www.example.com (93.184.216.119) port 80 (#0)
    > GET / HTTP/1.1
    Host: www.example.com
    User-Agent: Guzzle/5.0.3 curl/7.36.0 PHP/5.5.11
    
    < HTTP/1.1 200 OK
    < Accept-Ranges: bytes
    < Cache-Control: max-age=604800
    < Content-Type: text/html
    < Date: Thu, 13 Nov 2014 09:54:15 GMT
    < Etag: "359670651"
    < Expires: Thu, 20 Nov 2014 09:54:15 GMT
    < Last-Modified: Fri, 09 Aug 2013 23:54:35 GMT
    * Server ECS (iad/182A) is not blacklisted
    < Server: ECS (iad/182A)
    < X-Cache: HIT
    < x-ec-custom-error: 1
    < Content-Length: 1270
    < 
    * Connection #0 to host www.example.com left intact
    Status code of 1. request: 200
    Making 2. request:
    Status code of 2. request: 200
    Making 3. request:
    Status code of 3. request: 200
    Making 4. request:
    Status code of 4. request: 200
    Making 5. request:
    Status code of 5. request: 200

Notice how only the first request produced a debug outbut. The remaining requests have been fetched from 
the cache and were intercepted in the `before` event.

###Examples

See `examples` folder.

##Requirements

- PHP >= 5.5
- Guzzle >= 5.3.0
- Doctrine/Cache >= 1.3.1

##Installation

The recommended way to install guzzle-application-cache-subscriber is through [Composer](http://getcomposer.org/).

    curl -sS https://getcomposer.org/installer | php

Next, update your project's composer.json file to include GuzzleApplicationCacheSubscriber:

    {
        "repositories": [ { "type": "composer", "url": "http://packages.myseosolution.de/"} ],
        "minimum-stability": "dev",
        "require": {
             "paslandau/guzzle-application-cache-subscriber": "dev-master"
        }
    }

After installing, you need to require Composer's autoloader:
```php

require 'vendor/autoload.php';
```

##General workflow and customization options
The guzzle-application-cache-subscriber uses a closures (`canCacheRequest`) that is evaluated in the `end` event
to decide wether a request/response can be stored in the cache. If it returns `true` and the response is not `null`,
it is stored in the cache.

On a subsequent call to the same URL using the same HTTP verb, another closure (`mustRequestFresh`) is used in the `before` event to determine
if the request can be answered from cache or not. If it returns `true` and the corresponding response has been cached before,
the configuration key `has_cached_response` (`ApplicationCacheSubscriber::CACHED_RESPONSE_KEY`) is set to `true` so that
this info might be evaluated later on. On `false`, the cached response is deleted and Guzzle proceeds to perform the request as usual.

###Setting up the validation closures

```php
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
```

**Output**

    Request 0
    Caching the request/response.
    The response came not from cache
    
    Request 1
    Trying to serve the response from cache.
    The response came from cache
    
    Request 2
    Making a fresh request.
    Not allowed to cache the request/response.
    The response came not from cache
    
    Request 3
    Trying to serve the response from cache.
    The response came not from cache
    
    Request 4
    Trying to serve the response from cache.
    The response came from cache
    
##Similar plugins

- [CacheSubscriber (Guzzle 4 & 5)](https://github.com/guzzle/cache-subscriber)
- [CachePlugin (Guzzle 3)](https://github.com/guzzle/plugin-cache/)

##Frequently searched questions

- How can I cache Guzzle requests/responses?