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
        ->andWhere('plugin.capabilityEnable = true')
        ->andWhere('plugin.source != \'Default\'');

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
        Shopware()->Plugins()->Core()->VersionCentralTracker()->Config()['versionCentralApiCredentials']
    );

    $httpClient = new HttpClient($credentials);
    $httpClient->setRawData(json_encode($data), 'application/json');
    $response = $httpClient->request($httpClient::PUT);

    if (intval($response->getStatus()/100) !== 2) {
        $body = array_map(
            function(array $error) {
                return $error;
            },
            json_decode($response->getBody(), true)
        );

        if($response->getStatus() == 401) {
            $message = 'Verbindung nicht erfolgreich, bitte prüfen Sie Ihre API-Daten.';
        } else {
            $message = '';
            foreach($body["errors"] as $error) {
                $message .= \Shopware\Plugins\VersionCentralTracker\Components\Error::getErrorMessage($error["code"]) . "\n";
            }
        }
        throw new DomainException($message);
    }

    $this->output->writeln(sprintf('<info>Versions successfully updated.</info>'));
  }

  public function getOutput()
  {
    return $this->output;
  }
}
