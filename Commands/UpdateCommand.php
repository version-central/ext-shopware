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

class UpdateCommand extends ShopwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('VersionCentral:Update')
            ->setDescription('Update version information for plugins and application.')
            ->setHelp('The <info>%command.name%</info> updates version information of plugins and the application.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $builder = $this->container->get('models')
            ->getRepository('Shopware\Models\Plugin\Plugin')
            ->createQueryBuilder('plugin')
            ->andWhere('plugin.capabilityEnable = true');

        $plugins = array_map(
            function(Plugin $plugin) {
                return [
                    'identifier' => $plugin->getName(),
                    'version' => $plugin->getVersion(),
                    'active' => $plugin->getActive()
                ];
            },
            $builder->getQuery()->execute()
        );

        $data = [
            'application' => [
                'identifier' => 'shopware',
                'version' => Shopware::VERSION
            ],
            'packages' => $plugins
        ];

        $config = $this->getContainer()->get('config');

        $httpClient = new Zend_Http_Client($config->getByNamespace('VersionCentralTracker', 'versionCentralApiEndpoint'));
        $httpClient->setHeaders('Accept', 'application/vnd.version-central-v1+json');
        $httpClient->setAuth(
            $config->getByNamespace('VersionCentralTracker', 'versionCentralApiIdentifier'),
            $config->getByNamespace('VersionCentralTracker', 'versionCentralApiToken')
        );
        $httpClient->setRawData(json_encode($data), 'application/json');
        $response = $httpClient->request($httpClient::PUT);
        
        if (intval($response->getStatus()/100) !== 2) {
            $errors = array_map(
                function(array $error) {
                    unset($error['schema']);
                    return $error;
                },
                json_decode($response->getBody(), true)
            );
            throw new DomainException(print_r($errors, true));
        }

        $output->writeln(sprintf('<info>Versions successfully updated.</info>'));
    }
}
