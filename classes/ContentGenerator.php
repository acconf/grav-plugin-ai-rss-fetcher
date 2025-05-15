<?php
namespace Grav\Plugin\AiRssFetcher;

use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Collection;
use Grav\Common\Utils;
use Grav\Common\Filesystem\Folder;
use FeedIo\Feed\Item;
use FeedIo\Feed\Item\MediaInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class ContentGenerator
 * @package Grav\Plugin\AiRssFetcher
 */
class ContentGenerator
{
    /**
     * @var array Plugin configuration
     */
    protected $config;

    /**
     * @var string Base output path
     */
    protected $outputPath;

    /**
     * ContentGenerator constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        
        // Get the base output path
        $grav = Grav::instance();
        $this->outputPath = $grav['locator']->findResource('page://') . '/' . $this->config['output_path'];
        
        // Create output directory if it doesn't exist
        if (!file_exists($this->outputPath)) {
            Folder::create($this->outputPath);
        }
    }

    /**
     * Generate content from feed item
     * 
     * @param Item $item Feed item
     * @param array $feed Feed configuration
     * @return bool Success status
     */
    public function generateContentFromItem(Item $item, array $feed)
    {
        // Get item data
        $title = $item->getTitle();
        $link = $item->getLink();
        $date = $item->getLastModified() ?: new \DateTime();
        $content = $item->getContent();
        $author = $item->getAuthor() ? $item->getAuthor()->getName() : '';
        
        // Generate excerpt
        $excerpt = $this->generateExcerpt($content);
        
        // Generate item folder name (using hash to avoid filesystem issues)
        $itemFolderName = substr(md5($link), 0, 10);
        $itemFolderPath = $this->outputPath . '/' . $itemFolderName;
        
        // Create item folder
        if (!file_exists($itemFolderPath)) {
            Folder::create($itemFolderPath);
        }
        
        // Get featured image if available
        $image = null;
        if ($this->config['fetch_images']) {
            $image = $this->getFeaturedImage($item);
        }
        
        // Create Markdown file
        return $this->createMarkdownFile(
            $itemFolderPath,
            $title,
            $link,
            $date,
            $content,
            $excerpt,
            $author,
            $feed['name'],
            $image
        );
    }

    /**
     * Generate excerpt from content
     * 
     * @param string $content Full content
     * @return string Excerpt
     */
    protected function generateExcerpt($content)
    {
        // Strip HTML
        $text = strip_tags($content);
        
        // Truncate to excerpt length
        $length = $this->config['excerpt_length'];
        if (mb_strlen($text) > $length) {
            $excerpt = mb_substr($text, 0, $length);
            $lastSpace = mb_strrpos($excerpt, ' ');
            if ($lastSpace !== false) {
                $excerpt = mb_substr($excerpt, 0, $lastSpace);
            }
            $excerpt .= '...';
        } else {
            $excerpt = $text;
        }
        
        return $excerpt;
    }

    /**
     * Get featured image from item
     * 
     * @param Item $item Feed item
     * @return string|null Image URL or null if none found
     */
    protected function getFeaturedImage(Item $item)
    {
        // Check for media in the item
        foreach ($item->getMedias() as $media) {
            $type = $media->getType();
            if (strpos($type, 'image/') === 0) {
                return $media->getUrl();
            }
        }
        
        // Try to extract image from content
        try {
            $content = $item->getContent();
            if (!empty($content)) {
                $crawler = new Crawler($content);
                $images = $crawler->filter('img');
                
                if ($images->count() > 0) {
                    // Get the first image
                    $image = $images->eq(0);
                    $src = $image->attr('src');
                    
                    if ($src) {
                        return $src;
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if there's an error parsing content
        }
        
        return null;
    }

    /**
     * Create Markdown file for item
     * 
     * @param string $path Item folder path
     * @param string $title Item title
     * @param string $link Item link
     * @param \DateTime $date Item date
     * @param string $content Item content
     * @param string $excerpt Item excerpt
     * @param string $author Item author
     * @param string $feedName Feed name
     * @param string|null $image Item featured image
     * @return bool Success status
     */
    protected function createMarkdownFile($path, $title, $link, $date, $content, $excerpt, $author, $feedName, $image = null)
    {
        // Format date
        $formattedDate = $date->format($this->config['date_format']);
        
        // Build frontmatter
        $frontmatter = [
            'title' => $title,
            'date' => $date->format('Y-m-d H:i:s'),
            'link' => $link,
            'author' => $author,
            'feed' => $feedName,
            'excerpt' => $excerpt,
        ];
        
        if ($image) {
            $frontmatter['image'] = $image;
        }
        
        // Format frontmatter as YAML
        $yamlFrontmatter = "---\n";
        foreach ($frontmatter as $key => $value) {
            $yamlFrontmatter .= $key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
        $yamlFrontmatter .= "---\n\n";
        
        // Build markdown content
        $markdown = $yamlFrontmatter;
        $markdown .= "# " . $title . "\n\n";
        $markdown .= "*Published on " . $formattedDate . "*\n\n";
        
        if ($author) {
            $markdown .= "*By " . $author . "*\n\n";
        }
        
        if ($image) {
            $markdown .= "![" . $title . "](" . $image . ")\n\n";
        }
        
        $markdown .= $content . "\n\n";
        $markdown .= "[Read the original article](" . $link . ")";
        
        // Write to file
        $filePath = $path . '/item.md';
        return file_put_contents($filePath, $markdown) !== false;
    }

    /**
     * Get all RSS items as a collection
     * 
     * @return Collection Page collection of RSS items
     */
    public function getItemsCollection()
    {
        $grav = Grav::instance();
        $pages = $grav['pages'];
        
        // Get the base path
        $basePath = '/' . $this->config['output_path'];
        
        // Create a collection
        $collection = new Collection();
        
        // Get all modular pages under the output path
        $items = $pages->find($basePath)->children();
        
        if ($items) {
            foreach ($items as $item) {
                $collection->addPage($item);
            }
        }
        
        // Sort by date
        $collection = $collection->order('date', 'desc');
        
        return $collection;
    }
}