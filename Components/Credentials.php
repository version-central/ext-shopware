<?php

namespace Shopware\Plugins\VersionCentralTracker\Components;

use Zend_Http_Client;

use DomainException;

class Credentials
{
  protected $endpoint;
  protected $identifier;
  protected $token;

  public function __construct($endpoint, $identifier, $token)
  {
    $this->endpoint = $endpoint;
    $this->identifier = $identifier;
    $this->token = $token;
  }

  public function getEndpoint()
  {
    return $this->endpoint;
  }

  public function getIdentifier()
  {
    return $this->identifier;
  }

  public function getToken()
  {
    return $this->token;
  }
}
