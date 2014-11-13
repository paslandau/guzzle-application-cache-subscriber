<?php
use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Client;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;
use paslandau\GuzzleApplicationCacheSubscriber\ApplicationCacheSubscriber;
use paslandau\GuzzleApplicationCacheSubscriber\CacheStorage;

class ApplicationCacheSubscriberTest extends PHPUnit_Framework_TestCase {

    private function setupResponseMock(Client $client, $url2responses){

        $responses = array_map(function($val){ return $val[1]; },$url2responses);
        $mockSub = new Mock($responses);
        $client->getEmitter()->attach($mockSub);
        $requests = array_map(function($val) use (&$client){ return $client->createRequest("GET", $val[0]); }, $url2responses);

        $arr = [
            "responses" => $responses,
            "requests" => $requests,
        ];

        return $arr;
    }

    public function test_ShouldReturnCachedRequest()
    {
        $cacheDriver = new ArrayCache();
        $cache = new CacheStorage($cacheDriver);
        $sub = new ApplicationCacheSubscriber($cache);
        $client = new Client();
        $client->getEmitter()->attach($sub);

        $url2responses = [
            ["http://www.example.com/", new Response(200)],
            ["http://www.example.com/", new Response(201)]
        ];

        $arr = $this->setupResponseMock($client,$url2responses);
        $responses = $arr["responses"];
        $requests = $arr["requests"];
        $firstResponse = reset($responses);

        $resp = $client->send(array_shift($requests));
        $expectedResponse = array_shift($responses);
        $this->assertEquals($expectedResponse->getStatusCode(),$resp->getStatusCode());
        $resp = $client->send(array_shift($requests));
        $this->assertEquals($expectedResponse->getStatusCode(),$resp->getStatusCode(),"Second request is not fetched from cache");
    }

    public function test_ShouldNotCacheOnNegativeCanCacheRequest()
    {
        $cacheDriver = new ArrayCache();
        $cache = new CacheStorage($cacheDriver);

        $canCacheRequest = function(EndEvent $e){ return false; };
        $sub = new ApplicationCacheSubscriber($cache, $canCacheRequest);
        $client = new Client();
        $client->getEmitter()->attach($sub);

        $url2responses = [
            ["http://www.example.com/", new Response(200)],
            ["http://www.example.com/", new Response(201)]
        ];

        $arr = $this->setupResponseMock($client,$url2responses);
        $responses = $arr["responses"];
        $requests = $arr["requests"];

        $resp = $client->send(array_shift($requests));
        $expectedResponse = array_shift($responses);
        $this->assertEquals($expectedResponse->getStatusCode(),$resp->getStatusCode());
        $resp = $client->send(array_shift($requests));
        $expectedResponse = array_shift($responses);
        $this->assertEquals($expectedResponse->getStatusCode(),$resp->getStatusCode(),"Second request is probably still fetched from cache");
    }

    public function test_ShouldRequestFreshOnPositiveMustRequestFresh()
    {
        $cacheDriver = new ArrayCache();
        $cache = new CacheStorage($cacheDriver);

        $mustRequestFresh = function(BeforeEvent $e){ return true; };
        $sub = new ApplicationCacheSubscriber($cache, null, $mustRequestFresh);
        $client = new Client();
        $client->getEmitter()->attach($sub);

        $url2responses = [
            ["http://www.example.com/", new Response(200)],
            ["http://www.example.com/", new Response(201)]
        ];

        $arr = $this->setupResponseMock($client,$url2responses);
        $responses = $arr["responses"];
        $requests = $arr["requests"];

        $resp = $client->send(array_shift($requests));
        $expectedResponse = array_shift($responses);
        $this->assertEquals($expectedResponse->getStatusCode(),$resp->getStatusCode());
        $resp = $client->send(array_shift($requests));
        $expectedResponse = array_shift($responses);
        $this->assertEquals($expectedResponse->getStatusCode(),$resp->getStatusCode(),"Second request is probably still fetched from cache");
    }
}
 