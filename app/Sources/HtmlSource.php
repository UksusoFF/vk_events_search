<?php

namespace App\Sources;

use App\Helpers\DateTimeHelper;
use App\Models\Source;
use Exception;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class HtmlSource implements SourceInterface
{
    protected $config;

    protected $client;

    protected $crawler;

    protected $dateTimeHelper;

    protected const ID_PREFIX = 'html';

    public function __construct(Source $source)
    {
        $this->config = $source;
        $this->client = new Client();
        $this->crawler = new Crawler(null, null, $source->source);
        $this->dateTimeHelper = new DateTimeHelper();
    }

    /**
     * @return \Illuminate\Support\Collection
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getEvents()
    {
        $this->getContent();

        $items = $this->getNode($this->crawler, $this->config->map_items);

        return collect($items->each(function($node) {
            return [
                'uuid' => $this->getNodeUuid($node),
                'title' => $this->config->map_title ? $this->getNodeValue($node, $this->config->map_title) : null,
                'url' => $this->getNodeUrl($node),
                'description' => $this->config->map_description ? $this->getNodeValue($node, $this->config->map_description) : null,
                'image' => $this->config->map_image ? $this->getNodeValue($node, $this->config->map_image) : null,
                'date' => $this->getNodeDate($node),
            ];
        }));
    }

    /**
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getContent()
    {
        $html = (string)($this->client->request('GET', $this->config->source, [
            'timeout' => 10,
            'verify' => false,
        ]))->getBody();

        preg_match('/\<meta[^\>]+charset *= *["\']?([a-zA-Z\-0-9_:.]+)/i', $html, $charset);

        $this->crawler->addHtmlContent($html, (array_last($charset) ?: 'UTF-8'));
    }

    /**
     * @param \Symfony\Component\DomCrawler\Crawler $node
     * @param string $rule
     *
     * @return \Symfony\Component\DomCrawler\Crawler
     * @throws \Exception
     */
    protected function getNode(Crawler $node, string $rule)
    {
        [$type, $selector] = explode('|', $rule);

        switch ($type) {
            case 'css':
                return $node->filter($selector);
            case 'xpath':
                return $node->filterXPath($selector);
            default:
                throw new Exception('Invalid filter type');
        }
    }

    /**
     * @param \Symfony\Component\DomCrawler\Crawler $parent
     * @param string $rule
     *
     * @return null|string
     * @throws \Exception
     */
    protected function getNodeValue(Crawler $parent, string $rule)
    {
        $node = $this->getNode($parent, $rule);

        if ($node->count()) {
            if ($node->nodeName() == 'img') {
                $value = $node->image()->getUri();
            } elseif ($node->nodeName() == 'a') {
                $value = $node->link()->getUri();
            } else {
                $value = implode(PHP_EOL, $node->each(function(Crawler $child) {
                    return $child->text();
                }));
            }
            return trim(str_replace("\xc2\xa0", ' ', $value));
        } else {
            return null;
        }
    }

    /**
     * @param \Symfony\Component\DomCrawler\Crawler $node
     *
     * @return null|string
     * @throws \Exception
     */
    protected function getNodeUuid(Crawler $node)
    {
        if (empty($this->config->map_id)) {
            return null;
        }

        if (empty($nodeValue = $this->getNodeValue($node, $this->config->map_id))) {
            return null;
        }

        return implode('_', [
            self::ID_PREFIX,
            $this->config->id,
            md5($nodeValue),
        ]);
    }

    /**
     * @param \Symfony\Component\DomCrawler\Crawler $node
     *
     * @return \Illuminate\Support\Carbon|null
     * @throws \Exception
     */
    protected function getNodeDate(Crawler $node)
    {
        if (empty($this->config->map_date)) {
            return null;
        }

        if (empty($nodeValue = $this->getNodeValue($node, $this->config->map_date))) {
            return null;
        }

        return $this->dateTimeHelper->getDateFromFormat(
            $nodeValue,
            $this->config->map_date_format,
            $this->config->map_date_regex
        );
    }

    /**
     * @param $node
     *
     * @return null|string
     * @throws \Exception
     */
    protected function getNodeUrl($node)
    {
        if (empty($this->config->map_url)) {
            return $this->config->source;
        }

        return $this->getNodeValue($node, $this->config->map_url);
    }
}
