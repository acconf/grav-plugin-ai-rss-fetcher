<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\AiRssFetcher\RssFetcher;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class AiRssFetcherPlugin
 * @package Grav\Plugin
 */
class AiRssFetcherPlugin extends Plugin
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized'      => ['onPluginsInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onGetPageTemplates' => ['onGetPageTemplates', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigExtensions' => ['onTwigExtensions', 0]
        ];
    }

    /**
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }



    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }
    }

    /**
     * Add plugin templates path
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Add page templates
     */
    public function onGetPageTemplates(Event $event)
    {
        $types = $event->types;
        $types->register('rss_display', 'plugins://ai-rss-fetcher/templates');
    }

    /**
     * Add CSS and JS assets
     */
    public function onTwigSiteVariables()
    {
        $this->grav['assets']->addCss('plugin://ai-rss-fetcher/css/ai-rss-fetcher.css');
        $this->grav['assets']->addJs('plugin://ai-rss-fetcher/js/ai-rss-fetcher.js');
    }
    
    /**
     * Add Twig Extensions
     */
    public function onTwigExtensions()
    {
        $this->grav['twig']->twig->addFunction(new \Twig\TwigFunction('ai_rss_fetcher_items', [$this, 'getRssItems']));
    }

    /**
     * Get RSS items collection
     * 
     * @return \Grav\Common\Page\Collection
     */
    public function getRssItems()
    {
        $generator = new \Grav\Plugin\AiRssFetcher\ContentGenerator($this->config());
        return $generator->getItemsCollection();
    }

    /**
     * Get the fetcher instance
     */
    public function getFetcher()
    {
        return new RssFetcher($this->config());
    }
}