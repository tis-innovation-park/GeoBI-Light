<?php

namespace R3gis\AppBundle\Security\Firewall;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use R3gis\AppBundle\Security\Authentication\Token\WsseUserToken;

class WsseListener implements ListenerInterface
{
    protected $securityContext;
    protected $authenticationManager;

    public function __construct(SecurityContextInterface $securityContext, AuthenticationManagerInterface $authenticationManager)
    {
        $this->securityContext = $securityContext;
        $this->authenticationManager = $authenticationManager;
    }
    
    /**
     * 
     * @param string $wsse
     * @return WsseUserToken
     * @throws AuthenticationException
     */
    public function validateWsseToken($wsse) {
        $wsseRegex = '/UsernameToken Username="([^"]+)", PasswordDigest="([^"]+)", Nonce="([^"]+)", Created="([^"]+)"/';
        if (!preg_match($wsseRegex, $wsse, $matches)) {
            throw new AuthenticationException("X-WSSE header is malformed");
        }
        
        // create token
        $token = new WsseUserToken();
        $token->setUser($matches[1]);
        $token->digest   = $matches[2];
        $token->nonce    = $matches[3];
        $token->created  = $matches[4];
        
        $authToken =  $this->authenticationManager->authenticate($token);
        
        return $authToken;
    }

    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        try {
            if (!$request->headers->has('x-wsse')) {
                throw new AuthenticationException("X-WSSE header is missing");
            }
            
            $authToken = $this->validateWsseToken($request->headers->get('x-wsse'));
            
            $this->securityContext->setToken($authToken);

            return;
        } catch (AuthenticationException $failed) {
            // ... you might log something here
            
            // To deny the authentication clear the token. This will redirect to the login page.
            // $this->securityContext->setToken(null);
            // return;
            
            
            return;
            // Deny authentication with a '403 Forbidden' HTTP response
            $response = new Response();
            $response->setStatusCode(403);
            $event->setResponse($response);

        }
    }
}