<?php

namespace Shopware\Plugins\VersionCentralTracker\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Doctrine\ORM\EntityManager;

use Shopware;
use Shopware_Components_Config;
use Shopware\Models\Plugin\Plugin;

use DomainException;

use Shopware\Plugins\VersionCentralTracker\Components\HttpClient;
use Shopware\Plugins\VersionCentralTracker\Components\Credentials;

class TrackerUpdate
{
  /** @var OutputInterface */
  protected $output;

  /** @var EntityManager */
  protected $em;

  /** @var InventoryFile */
  protected $config;

  public function __construct(OutputInterface $output, EntityManager $em, Shopware_Components_Config $config)
  {
    $this->output = $output;
    $this->em = $em;
    $this->config = $config;
  }

  public function execute()
  {
    $builder = $this->em
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

    $shop = $this->em->getRepository('Shopware\Models\Shop\Shop')->getDefault();
    if (!$shop) {
      throw new DomainException('No default shop found. Check your shop configuration');
    }

    $data = [
        'application' => [
            'identifier' => 'shopware',
            'version' => Shopware::VERSION
        ],
        'packages' => $plugins,
        'meta' => [
          'name' => $shop->getName(),
          'url' => sprintf(
            '%s://%s/%s',
            $shop->getSecure() ? 'https' : 'http',
            $shop->getHost(),
            ltrim($shop->getBasePath(), '/')
          )
        ]
    ];

    $credentials = new Credentials(
      $this->config->getByNamespace('VersionCentralTracker', 'versionCentralApiCredentials')
    );

    $httpClient = new HttpClient($credentials);
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

    $this->output->writeln(sprintf('<info>Versions successfully updated.</info>'));
  }

  public function getOutput()
  {
    return $this->output;
  }
}
