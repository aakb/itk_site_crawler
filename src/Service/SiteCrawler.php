<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SiteCrawler
{
    private $uriList = array();
    private $errors = array(
        'global' => array(),
        'pages' => array(),
    );
    private $domain;
    // Max visits pr. site. Use command option to limit further.
    private $count = 50000;
    private $gdprCompliant;
    private $spreadsheet;
    private $timeStart;
    private $timeEnd;

    public function __construct(GdprCompliant $gdprCompliant)
    {
        $this->gdprCompliant = $gdprCompliant;
    }

    /**
     * Set a max number of pagevisits.
     *
     * @param $maxVisits
     */
    public function setMaxVisits($maxVisits) {
        $this->count = $maxVisits;
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
        // Initialize list.
        $this->uriList[] = $domain;
        $this->timeStart = microtime(true);
        $i = 0;
        while ($i < $this->count && $i < count($this->uriList))
        {
            // Crawl page and find links.
            $this->crawlPage($this->uriList[$i], $i);
            $i++;
        }

        $this->timeEnd = microtime(true);
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
            // Initialize list.
            $this->uriList[] = $domain;
            $this->timeStart = microtime(true);
            $i = 0;
            while ($i < $this->count && $i < count($this->uriList))
            {
                // Crawl page and find links.
                $this->crawlPage($this->uriList[$i], $i);
                $i++;
            }

            $this->timeEnd = microtime(true);
            $this->outputResult();
        }
    }

    /**
     * Crawl a specific page within a domain.
     *
     * @param $domain
     * @param $current_count
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
        $errors_global_custom_cookie_banner = $this->gdprCompliant->searchCustomCookieBanner($html);
        $errors_global_siteimprove = $this->gdprCompliant->searchSiteImprove($html);
        $errors_global_facebook = $this->gdprCompliant->searchFacebook($html);
        $errors_global_google_analytics = $this->gdprCompliant->searchGoogleAnalytics($html);
        if ($errors) {
            $this->errors = array_merge_recursive($this->errors, $errors);
            print 'X';
        }
        else {
            print '.';
        }

        // @todo Make an event based approach
        if ($errors_global_custom_cookie_banner) {
            $this->errors = array_merge_recursive($this->errors, $errors_global_custom_cookie_banner);
        }

        if ($errors_global_siteimprove) {
            $this->errors = array_merge_recursive($this->errors, $errors_global_siteimprove);
        }

        if ($errors_global_facebook) {
            $this->errors = array_merge_recursive($this->errors, $errors_global_facebook);
        }

        if ($errors_global_google_analytics) {
            $this->errors = array_merge_recursive($this->errors, $errors_global_google_analytics);
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
        $this->spreadsheet = new Spreadsheet();
        $sheet = $this->spreadsheet->getActiveSheet();
        $sheet->setTitle('Global output');


        $this->outputGlobalSheet();

        // Create a new sheet for page output
        $pageSheet = $this->spreadsheet->createSheet();
        $pageSheet->setTitle('Page output');

        $this->outputPageSheet();

        $filename = 'output/' . preg_replace('#^https?://#', '', $this->domain) . '-result.xlsx';
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($filename);
        $this->printToConsole($filename);
    }

    /**
     * Output global sheet.
     */
    private function outputGlobalSheet() {
        $foundCount = count($this->uriList);
        $visitedCount = $this->count > $foundCount ? $foundCount : $this->count;
        $timeSpent = $this->timeEnd - $this->timeStart;
        $sheet = $this->spreadsheet->getSheetByName('Global output');
        $sheet->setCellValueByColumnAndRow(1, 1, 'Domain');
        $sheet->setCellValueByColumnAndRow(2, 1, $this->uriList[0]);
        $sheet->setCellValueByColumnAndRow(1, 2, 'Pages visited');
        $sheet->setCellValueByColumnAndRow(2, 2, $visitedCount);
        $sheet->setCellValueByColumnAndRow(1, 3, 'Pages Found');
        $sheet->setCellValueByColumnAndRow(2, 3, $foundCount);
        $sheet->setCellValueByColumnAndRow(1, 4, 'Time spent');
        $sheet->setCellValueByColumnAndRow(2, 4, (int)$timeSpent . 'sec');
        $sheet->setCellValueByColumnAndRow(1, 5, '---');
        $sheet->setCellValueByColumnAndRow(1, 6, 'Found indications of:');
        $currentRow = 7;
        foreach ($this->errors['global'] as $key => $error) {
            $sheet->setCellValueByColumnAndRow(1, $currentRow, $key);
        }
    }

    /**
     * Output page sheet.
     */
    private function outputPageSheet() {
        $sheet = $this->spreadsheet->getSheetByName('Page output');

        // Set header for first col.
        $sheet->setCellValueByColumnAndRow(1, 1, 'URL');
        $currentCol = 1;
        $currentRow = 2;

        foreach ($this->errors['pages'] as $path => $page) {
            $sheet->setCellValueByColumnAndRow($currentCol, $currentRow, $path);
            foreach ($page as $type => $result) {
                foreach ($result as $output) {
                    $currentCol ++;
                    // Set header for new col.
                    $sheet->setCellValueByColumnAndRow($currentCol, 1, $type);
                    $sheet->setCellValueByColumnAndRow($currentCol, $currentRow, $output);
                }
                $currentRow ++;
                $currentCol = 1;
            }
        }
    }

    /**
     * Print end result to console.
     *
     * @param $filename
     */
    private function printToConsole($filename) {
        print PHP_EOL;
        print "---";
        print PHP_EOL;
        print "Wrote file: " . $filename . " to disc.";
        print PHP_EOL;
    }
}
