<?php

namespace Shopware\Plugins\K10rVersionCentralTracker\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Doctrine\ORM\EntityManager;

use Shopware;
use Shopware\Models\Plugin\Plugin;

use DomainException;

use Shopware\Plugins\K10rVersionCentralTracker\Components\HttpClient;
use Shopware\Plugins\K10rVersionCentralTracker\Components\Credentials;

class TrackerUpdate
{
  /** @var OutputInterface */
  protected $output;

  /** @var EntityManager */
  protected $em;

  public function __construct(OutputInterface $output, EntityManager $em)
  {
    $this->output = $output;
    $this->em = $em;
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
            return array(
                'identifier' => $plugin->getName(),
                'version' => $plugin->getVersion(),
                'active' => $plugin->getActive()
            );
        },
        $builder->getQuery()->execute()
    );

    $shop = $this->em->getRepository('Shopware\Models\Shop\Shop')->getDefault();
    if (!$shop) {
      throw new DomainException('No default shop found. Check your shop configuration');
    }

    $data = array(
        'application' => array(
            'identifier' => 'shopware',
            'version' => Shopware::VERSION
        ),
        'packages' => $plugins,
        'meta' => array(
          'name' => $shop->getName(),
          'url' => sprintf(
            '%s://%s/%s',
            $shop->getSecure() ? 'https' : 'http',
            $shop->getHost(),
            ltrim($shop->getBasePath(), '/')
          )
        )
    );

    $config = Shopware()->Plugins()->Core()->K10rVersionCentralTracker()->Config();
    $credentials = new Credentials(
        $config['versionCentralApiCredentials']
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
            $message = \Shopware\Plugins\K10rVersionCentralTracker\Components\Error::getErrorMessage('api_credentials_invalid');
        } else {
            $message = '';
            foreach($body["errors"] as $error) {
                $message .= \Shopware\Plugins\K10rVersionCentralTracker\Components\Error::getErrorMessage($error["code"]) . "\n";
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
