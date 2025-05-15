<?php
namespace Grav\Plugin\AiRssFetcher;

use Grav\Common\Grav;
use FeedIo\FeedIo;
use FeedIo\Factory;
use Grav\Plugin\AiRssFetcher\ContentGenerator;

/**
 * Class RssFetcher
 * @package Grav\Plugin\AiRssFetcher
 */
class RssFetcher
{
    /**
     * @var array Plugin configuration
     */
    protected $config;

    /**
     * @var FeedIo
     */
    protected $feedIo;

    /**
     * @var ContentGenerator
     */
    protected $contentGenerator;

    /**
     * RssFetcher constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        
        // Initialize FeedIO with PSR-18 compatible client
        $this->feedIo = Factory::create()->getFeedIo();
        
        // Create content generator
        $this->contentGenerator = new ContentGenerator($config);
    }

    /**
     * Fetch all enabled feeds
     * 
     * @param bool $force Force fetch even if cache is still valid
     * @return array Results for each feed
     */
    public function fetchAllFeeds($force = false)
    {
        $results = [];
        
        // Get all enabled feeds
        $feeds = $this->getEnabledFeeds();
        
        foreach ($feeds as $feed) {
            $results[$feed['name']] = $this->fetchFeed($feed, $force);
        }
        
        return $results;
    }

    /**
     * Fetch a single feed by name
     * 
     * @param string $feedName The name of the feed to fetch
     * @param bool $force Force fetch even if cache is still valid
     * @return array Result of the fetch operation
     */
    public function fetchSingleFeed($feedName, $force = false)
    {
        $feeds = $this->config['feed_urls'];
        $feedConfig = null;
        
        // Find the feed by name
        foreach ($feeds as $feed) {
            if ($feed['name'] === $feedName) {
                $feedConfig = $feed;
                break;
            }
        }
        
        if (!$feedConfig) {
            return [
                'status' => 'error',
                'message' => 'Feed not found: ' . $feedName,
                'count' => 0
            ];
        }
        
        // Check if the feed is enabled
        if (!$feedConfig['enabled']) {
            return [
                'status' => 'error',
                'message' => 'Feed is disabled: ' . $feedName,
                'count' => 0
            ];
        }
        
        // Fetch the feed
        return $this->fetchFeed($feedConfig, $force);
    }

    /**
     * Fetch a specific feed
     * 
     * @param array $feed Feed configuration
     * @param bool $force Force fetch even if cache is still valid
     * @return array Result of the fetch operation
     */
    protected function fetchFeed($feed, $force = false)
    {
        try {
            // Check if we need to fetch based on cache time
            if (!$force && !$this->shouldFetchFeed($feed)) {
                return [
                    'status' => 'skipped',
                    'message' => 'Cache is still valid',
                    'count' => 0
                ];
            }
            
            // Fetch the feed
            $result = $this->feedIo->read($feed['url']);
            $feedResult = $result->getFeed();
            
            // Process items
            $count = 0;
            $maxItems = $this->config['max_items_per_feed'];
            
            foreach ($feedResult as $i => $item) {
                // Stop if we've reached the max items
                if ($i >= $maxItems) {
                    break;
                }
                
                // Generate content
                $this->contentGenerator->generateContentFromItem($item, $feed);
                $count++;
            }
            
            // Update last fetch time
            $this->updateLastFetchTime($feed['name']);
            
            return [
                'status' => 'success',
                'message' => 'Successfully fetched feed',
                'count' => $count
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error fetching feed: ' . $e->getMessage(),
                'count' => 0
            ];
        }
    }

    /**
     * Get all enabled feeds
     * 
     * @return array Enabled feeds
     */
    protected function getEnabledFeeds()
    {
        $enabledFeeds = [];
        
        foreach ($this->config['feed_urls'] as $feed) {
            if ($feed['enabled']) {
                $enabledFeeds[] = $feed;
            }
        }
        
        return $enabledFeeds;
    }

    /**
     * Check if we should fetch a feed based on cache time
     * 
     * @param array $feed Feed configuration
     * @return bool True if we should fetch, false otherwise
     */
    protected function shouldFetchFeed($feed)
    {
        $grav = Grav::instance();
        $lastFetchTime = $grav['cache']->fetch('plugin.ai-rss-fetcher.' . ($feed['name']?? ''));
        
        if (!$lastFetchTime) {
            return true;
        }
        
        $cacheTime = $this->config['cache_time'];
        $currentTime = time();
        
        return ($currentTime - $lastFetchTime) > $cacheTime;
    }

    /**
     * Update the last fetch time for a feed
     * 
     * @param string $feedName The name of the feed
     */
    protected function updateLastFetchTime($feedName)
    {
        $grav = Grav::instance();
        $cacheTime = $this->config['cache_time'];
        
        $grav['cache']->save('plugin.ai-rss-fetcher.' . $feedName, time(), $cacheTime);
    }
}