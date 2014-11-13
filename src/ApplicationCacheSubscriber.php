<?php
namespace paslandau\GuzzleApplicationCacheSubscriber;

use Doctrine\Common\Cache\Cache;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;

class ApplicationCacheSubscriber implements SubscriberInterface
{
    const CACHED_RESPONSE_KEY = "has_cached_response";

    /**
     * @var CacheStorage
     */
    private $cache;

    /**
     * @var callable
     */
    private $canCacheRequest;

    /**
     * @var callable
     */
    private $mustRequestFresh;

    /**
     * @param CacheStorage $cache
     * @param callable $canCacheRequest [optional]. Default: null. (A response will be cached if it doesn't have an exception set)
     * @param callable $mustRequestFresh [optional]. Default: null. (No request must be fresh - Each one may be answered from cache)
     */
    function __construct(CacheStorage $cache, callable $canCacheRequest = null, callable $mustRequestFresh = null)
    {
        if ($canCacheRequest === null) {
            $canCacheRequest = function (EndEvent $event) {
                return true;
            };
        }
        $this->canCacheRequest = $canCacheRequest;

        if ($mustRequestFresh === null) {
            $mustRequestFresh = function (BeforeEvent $event) {
                return false;
            };
        }
        $this->mustRequestFresh = $mustRequestFresh;

        $this->cache = $cache;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array
     */
    public function getEvents()
    {
        return array(
            'before' => ['setup', RequestEvents::EARLY],
            'end' => ['evaluate', RequestEvents::LATE],
        );
    }

    public function setup(BeforeEvent $event)
    {
        $request = $event->getRequest();
        $fn = $this->mustRequestFresh;
        if (!$fn($event)) {
            $response = $this->cache->fetch($request);
            if ($response !== null) {
                $request->getConfig()->set(self::CACHED_RESPONSE_KEY,true);
                $event->intercept($response);
            }
        } else {
            $this->cache->delete($request);
        }
    }

    public function evaluate(EndEvent $event)
    {
        $fnReq = $this->canCacheRequest;
        $request = $event->getRequest();
        if ($fnReq($event)) {
            $response = $this->cache->fetch($request);
            if ($response === null) {
                $this->cache->cache($request, $event->getResponse());
            }
        }
    }


}