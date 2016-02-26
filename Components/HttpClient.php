<?php

namespace Shopware\Plugins\VersionCentralTracker\Components;

use Zend_Http_Client;

use DomainException;

class HttpClient extends Zend_Http_Client
{
  public function __construct(Credentials $credentials)
  {
    parent::__construct($credentials->getEndpoint());
    
    $this->setHeaders('Accept', 'application/vnd.version-central-v1+json');
    $this->setAuth($credentials->getIdentifier(), $credentials->getToken());
  }
}
