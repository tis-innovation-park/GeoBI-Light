<?php

namespace R3gis\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use R3gis\AppBundle\Utils\MapCreatorUtils;

class PurgeCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('geobi:purge-old')
            ->setDescription('Remove old temporary map')
            ->addArgument('ttl', InputArgument::OPTIONAL, 'TTL (in seconds)')
            //->addOption('yell', null, InputOption::VALUE_NONE, 'Se impostato, urlerà in lettere maiuscole')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ttl = $input->getArgument('ttl');
        if (empty($ttl)) {
            throw new \Exception("Missing parameter \"ttl\"");
        }
        if (!is_numeric($ttl)) {
            throw new \Exception("Invalid parameter value for \"ttl\": \"{$ttl}\" is not an integer");
        }
        
        $logger = $this->getContainer()->get('logger');
        $logger->info("Purging old map. TTL: {$ttl}");
        
        $mapUtils = new MapCreatorUtils($this->getContainer()->get('doctrine'));
        $tot = $mapUtils->purgeOldMaps((int)$ttl);

        $output->writeln("{$tot} temporary maps removed");
    }
}