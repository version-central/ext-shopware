<?php

namespace Shopware\Plugins\K10rVersionCentralTracker\Components;

use DomainException;

class Credentials
{
  protected $identifier;
  protected $token;

  public function __construct($credentials)
  {
    if (!$credentials) {
      throw new DomainException(\Shopware\Plugins\K10rVersionCentralTracker\Components\Error::getErrorMessage('api_credentials_invalid'));
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
