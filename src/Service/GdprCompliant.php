<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;

class GdprCompliant
{
    private $customCookieBannerFound = FALSE;
    private $siteImproveFound = FALSE;
    private $facebookFound = FALSE;
    private $googleAnalyticsFound = FALSE;

    /**
     * Find iframes.
     *
     * @param $html
     * @param $domain
     *
     * @return array|null
     */
    public function searchIframe($html, $domain)
    {
        $errors = [];
        $crawler = new Crawler($html, $domain);
        $errors['pages'][$domain]['iframes'] = $crawler->filter('iframe')->each(function($node) {
            return $node->attr('src');
        });
        if (empty($errors['pages'][$domain]['iframes'])) {
            return NULL;
        }

        return $errors;
    }

    /**
     * Find indications of facebook. (Scripts containing "facebook")
     *
     * @param $html
     *
     * @return array|null
     */
    public function searchFacebook($html)
    {
        $errors = [];

        // Don't run this if we already found an indication of facebook.
        if(!$this->facebookFound) {
            $crawler = new Crawler($html);
            $crawler->filter('script')->each(function($node) {
                $content = $node->text();
                $src = $node->attr('src');
                if(strpos($src, 'facebook') !== false) {
                    $this->facebookFound = TRUE;
                }

                if(strpos($content, 'facebook') !== false) {
                    $this->facebookFound = TRUE;
                }
            });

            if ($this->facebookFound) {
                $errors['global']['facebook'] = TRUE;
                return $errors;
            }
        }

        return NULL;
    }

    /**
     * Find indication of Google analytics. (Scripts containing "UA-")
     *
     * @param $html
     *
     * @return array|null
     */
    public function searchGoogleAnalytics($html)
    {
        $errors = [];

        // Don't run this if we already found an indication of google analytics.
        if(!$this->googleAnalyticsFound) {
            $crawler = new Crawler($html);
            $crawler->filter('script')->each(function($node) {
                $content = $node->text();
                $src = $node->attr('src');
                if(strpos($src, 'UA-') !== false) {
                    $this->googleAnalyticsFound = TRUE;
                }

                if(strpos($content, 'UA-') !== false) {
                    $this->googleAnalyticsFound = TRUE;
                }
            });

            if ($this->googleAnalyticsFound) {
                $errors['global']['facebook'] = TRUE;
                return $errors;
            }
        }

        return NULL;
    }

    /**
     * Find indication of siteimprove. (Scripts containing "siteimprove")
     *
     * @param $html
     *
     * @return array|null
     */
    public function searchSiteImprove($html)
    {
        $errors = [];

        // Don't run this if we already found an indication of siteimprove.
        if(!$this->siteImproveFound) {
            $crawler = new Crawler($html);
            $crawler->filter('script')->each(function ($node) {
                $content = $node->text();
                $src = $node->attr('src');
                if(strpos($src, 'siteimprove') !== false) {
                    $this->siteImproveFound = TRUE;
                }

                if(strpos($content, 'siteimprove') !== false) {
                    $this->siteImproveFound = TRUE;
                }
            });

            if ($this->siteImproveFound) {
                $errors['global']['site_improve'] = TRUE;
                return $errors;
            }
        }

        return NULL;
    }

    /**
     * Find divs with class containing '%cookie%'.
     *
     * @param $html
     *
     * @return array
     */
    public function searchCustomCookieBanner($html)
    {
        $errors = [];
        // Don't run this if we already found an indication of custom banner.
        if(!$this->customCookieBannerFound) {
            $crawler = new Crawler($html);

            $crawler->filter('div')->each(function ($node) {
                $class = $node->attr('class');
                if(strpos($class, 'cookie') !== false) {
                    $this->customCookieBannerFound = TRUE;
                }
            });

            if ($this->customCookieBannerFound) {
                $errors['global']['custom_cookie_banner'] = TRUE;
                return $errors;
            }
        }

        return NULL;
    }
}
