<?php

namespace Shopware\Plugins\VersionCentralTracker\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Shopware\Commands\ShopwareCommand;
use Shopware\Models\Plugin\Plugin;
use Shopware;

use Zend_Http_Client;

use DomainException;

use Shopware\Plugins\VersionCentralTracker\Service\TrackerUpdate;

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

        $trackerUpdate = new TrackerUpdate($output, $container->get('models'), $container->get('config'));
        $trackerUpdate->execute();

        return false;
    }
}
