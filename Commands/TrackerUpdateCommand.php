<?php

namespace Shopware\Plugins\K10rVersionCentralTracker\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Shopware\Commands\ShopwareCommand;
use Shopware;
use Shopware\Plugins\K10rVersionCentralTracker\Service\TrackerUpdate;

class TrackerUpdateCommand extends ShopwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('VersionCentral:TrackerUpdate')
            ->setDescription('Update version information for plugins and application.')
            ->setHelp('The <info>%command.name%</info> updates version information of plugins and the application.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $trackerUpdate = new TrackerUpdate($output, $container->get('models'));
        $trackerUpdate->execute();

        return false;
    }
}
