<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\HttpClient\HttpClient;
use App\Service\GdprCompliant;

class SiteCrawler
{
    private $uriList = array();
    private $errors = array(
        'global' => array(),
        'pages' => array(),
    );
    private $domain;
    private $count = 200;
    private $gdprCompliant;

    public function __construct(GdprCompliant $gdprCompliant)
    {
        $this->gdprCompliant = $gdprCompliant;
    }

    /**
     * Register all services.
     * @todo Make an event based approach, or remove this part all together.
     *
     * @return array
     */
    public function getCrawlerServices()
    {
        $crawlerServices = [
          'gdpr_compliant'
        ];

        return $crawlerServices;
    }

    /**
     * Crawl a single domain.
     *
     * @param $domain
     * @param $serviceName
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function crawlSingle($domain)
    {
        $this->domain = $domain;
        $this->uriList[] = $domain;

        $i = 0;
        while ($i < $this->count && $i < count($this->uriList))
        {
            // Crawl page and find links.
            $this->crawlPage($this->uriList[$i], $i);
            $i++;
        }

        $this->outputResult();
    }

    /**
     * Crawl an array of domains.
     *
     * @param $domains
     * @param $serviceName
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function crawlMultiple($domains)
    {
        foreach($domains as $domain) {
            $this->errors = array(
              'global' => array(),
              'pages' => array(),
            );
            $this->domain = $domain;
            $this->count = 500;
            $this->uriList[] = $domain;

            $i = 0;
            while ($i < $this->count && $i < count($this->uriList))
            {
                // Crawl page and find links.
                $this->crawlPage($this->uriList[$i], $i);
                $i++;
            }

            $this->outputResult();
        }
    }

    /**
     * Crawl a specific page within a domain.
     *
     * @param $domain
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function crawlPage($domain, $current_count)
    {
        $html = $this->getContent($domain);
        if ($html) {
            $crawler = new Crawler($html, $domain);

            // Add new links to $urilist.
            $anchor_links = $crawler->filter('a')->links();
            foreach ($anchor_links as $link) {
                $uri = $link->getUri();
                if($this->isValid($uri)) {
                    $this->uriList[] = $uri;
                }
            }
        }
        $errors = $this->gdprCompliant->searchIframe($html, $domain);
        $errors_global = $this->gdprCompliant->searchCustomCookieBanner($html);
        if ($errors) {
            $this->errors = array_merge_recursive($this->errors, $errors);
            print 'X';
        }
        else {
            print '.';
        }

        if($errors_global) {
            $this->errors = array_merge_recursive($this->errors, $errors_global);
        }

        // Print status every 50 result.
        if ($current_count % 50 == 0) {
            print '|'. $current_count . '/' . count($this->uriList) .'|';
        }
    }

    /**
     * Get contents of page.
     *
     * @param $domain
     *
     * @return string|void
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function getContent($domain)
    {
        $no_crawl_status = [
          403,
          404
        ];

        $client = HttpClient::create();
        $response = $client->request('GET', $domain);
        $status = $response->getStatusCode();
        if (in_array($status, $no_crawl_status)) {
            return;
        }
        // @todo Check content type.
        // @todo Other checks?
        $html = $response->getContent();

        return $html;
    }

    /**
     * Ensure it's a valid page.
     *
     * @param $uri
     *
     * @return bool
     */
    private function isValid($uri) {
        // Check domain.
        if (strpos($uri, $this->domain) !== 0) {
            return false;
        }

        // Check existing.
        if(in_array($uri, $this->uriList)) {
            return false;
        }

        return true;
    }

    /**
     * Output the result.
     */
    private function outputResult() {
        print_r($this->uriList);
        print '---';
        print_r($this->errors);
        // @todo Output results as csv.
    }
}
