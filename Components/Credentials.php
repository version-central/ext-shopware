<?php

namespace Shopware\Plugins\VersionCentralTracker\Components;

use DomainException;

class Credentials
{
  protected $identifier;
  protected $token;

  public function __construct($credentials)
  {
    if (!$credentials) {
      throw new DomainException(\Shopware\Plugins\VersionCentralTracker\Components\Error::getErrorMessage('api_credentials_invalid'));
    }
    
    list($this->identifier, $this->token) = explode(':', $credentials);
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
