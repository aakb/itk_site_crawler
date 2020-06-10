<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector\CssSelectorConverter;

class GdprCompliant
{
    private $customCookieBannerFound = FALSE;

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
        $errors['pages'][$domain]['iframes'][] = $crawler->filter('iframe')->each(function ($node) {
            return $node->attr('src');
        });
        if (empty($errors['pages'][$domain]['iframes'][0])) {
            return NULL;
        }

        return $errors;
    }

    public function searchFacebook($html, $domain)
    {
        $errors = [];

        return $errors;
    }

    public function searchGoogleAnalytics($html, $domain)
    {
        $errors = [];

        return $errors;
    }

    public function searchVimeo($html, $domain)
    {
        $errors = [];

        return $errors;
    }

    public function searchSiteImprove($html, $domain)
    {
        $errors = [];

        return $errors;
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
