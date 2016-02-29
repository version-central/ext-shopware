<?php

namespace Shopware\Plugins\VersionCentralTracker\Components;

use Zend_Http_Client;

use DomainException;

class HttpClient extends Zend_Http_Client
{
  const API_ENDPOINT = 'https://data.version-central.de';

  public function __construct(Credentials $credentials)
  {
    parent::__construct(self::API_ENDPOINT);
    
    $this->setHeaders('Accept', 'application/vnd.version-central-v1+json');
    $this->setAuth($credentials->getIdentifier(), $credentials->getToken());
  }
}
