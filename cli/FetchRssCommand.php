<?php
namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Grav\Plugin\AiRssFetcher\RssFetcher;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class FetchRssCommand
 * @package Grav\Plugin\AiRssFetcher\Cli
 */
class FetchRssCommand extends ConsoleCommand
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('rss:fetch')
            ->setDescription('Fetch and cache RSS feeds')
            ->setHelp('This command allows you to fetch RSS feeds and cache them as Markdown files')
            ->addOption(
                'feed',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Specify a specific feed name to fetch (defaults to all enabled feeds)'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force fetch even if cache is still valid'
            );
    }

    /**
     * @return int
     */
    protected function serve()
    {
        // TODO: remove when requiring Grav 1.7+
        if (method_exists($this, 'initializeGrav')) {
            $this->initializeThemes();
        }

        $io = new SymfonyStyle($this->input, $this->output);
        $io->title('RSS Feed Fetcher');

        // Get plugin config
        $config = $this->getPluginConfig();
        if (!$config) {
            $io->error('Plugin configuration not found or plugin not enabled');
            return 1;
        }

        // Create the fetcher
        $fetcher = new RssFetcher($config);

        // Check if specific feed was requested
        $feedName = $this->input->getOption('feed');
        $force = $this->input->getOption('force');

        if ($feedName) {
            $io->section('Fetching specific feed: ' . $feedName);
            $result = $fetcher->fetchSingleFeed($feedName, $force);
            
            if ($result['status'] === 'success') {
                $io->success(sprintf('Successfully fetched %d items from feed "%s"', $result['count'], $feedName));
            } else {
                $io->error($result['message']);
                return 1;
            }
        } else {
            $io->section('Fetching all enabled feeds');
            $results = $fetcher->fetchAllFeeds($force);
            
            $totalItems = 0;
            $errors = [];
            
            foreach ($results as $feedName => $result) {
                if ($result['status'] === 'success') {
                    $totalItems += $result['count'];
                    $io->writeln(sprintf('✓ Feed "%s": %d items', $feedName, $result['count']));
                } else {
                    $errors[] = sprintf('Feed "%s": %s', $feedName, $result['message']);
                    $io->writeln(sprintf('✗ Feed "%s": %s', $feedName, $result['message']));
                }
            }
            
            if (empty($errors)) {
                $io->success(sprintf('Successfully fetched %d items from %d feeds', $totalItems, count($results)));
            } else {
                $io->warning(sprintf('Fetched %d items with %d errors', $totalItems, count($errors)));
            }
        }

        return 0;
    }

    /**
     * Get the plugin configuration
     */
    private function getPluginConfig()
    {
        $grav = Grav::instance();
        $config = $grav['config']->get('plugins.ai-rss-fetcher');
        if ($config && isset($config['enabled']) && $config['enabled']) {
            return $config;
        }
        return null;
    }
}