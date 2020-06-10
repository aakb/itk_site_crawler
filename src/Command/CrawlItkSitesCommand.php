<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\SiteCrawler;

class CrawlItkSitesCommand extends Command
{
    protected static $defaultName = 'crawlItkSites';

    private $siteCrawler;

    /**
     * CrawlItkSitesCommand constructor.
     *
     * @param \App\Service\SiteCrawler $siteCrawler
     */
    public function __construct(SiteCrawler $siteCrawler)
    {
        parent::__construct();

        $this->siteCrawler = $siteCrawler;
    }

    /**
     * Configure crawl command.
     */
    protected function configure()
    {
        $this
            ->setDescription('Crawls one or all ITK sites')
            ->addArgument('service_name', InputArgument::OPTIONAL, 'The service to use with the crawler (i.e "gdpr_compliant")')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'A specific domain to crawl')
        ;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $serviceName = $input->getArgument('service_name');

        if (empty($serviceName)) {
            $io->error('Missing service argument.');
            return Command::FAILURE;
        }

        if (!in_array($serviceName, $this->siteCrawler->getCrawlerServices())) {
            $io->error('Unknown service argument.');
            return Command::FAILURE;
        }

        $domain = $input->getOption('domain');
        if ($domain) {
          $this->siteCrawler->crawlSingle($domain, $serviceName);
        }
        else {
          // @todo Crawl all sites from a list.
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
