<?php

namespace R3gis\AppBundle\Security\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class WsseUserToken extends AbstractToken
{
    public $created;
    public $digest;
    public $nonce;

    public function __construct(array $roles = array())
    {
        parent::__construct($roles);

        // Se l'utente ha dei ruoli, considerarlo autenticato
        $this->setAuthenticated(count($roles) > 0);
        //$this->setAuthenticated( true ); // TODO: SS: !!!!!!
    }

    public function getCredentials()
    {
        return '';
    }
}