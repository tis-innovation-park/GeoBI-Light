<?php

namespace R3gis\AppBundle\Security\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\NonceExpiredException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use R3gis\AppBundle\Security\Authentication\Token\WsseUserToken;
use Symfony\Component\Security\Core\User\UserChecker;

class WsseProvider implements AuthenticationProviderInterface
{
    private $userProvider;
    private $cacheDir;
    private $userChecker;

    public function __construct(UserProviderInterface $userProvider, $cacheDir)
    {
        $this->userProvider = $userProvider;
        $this->cacheDir     = $cacheDir;
        $this->userChecker  = new UserChecker();
    }

    public function authenticate(TokenInterface $token)
    {
        $user = $this->userProvider->loadUserByUsername($token->getUsername());
        
        $this->userChecker->checkPreAuth($user);
        if ($user && $this->validateDigest($token->digest, $token->nonce, $token->created, $user->getPassword())) {
            $authenticatedToken = new WsseUserToken($user->getRoles());
            $authenticatedToken->setUser($user);

            return $authenticatedToken;
        }

        throw new AuthenticationException('The WSSE authentication failed.');
    }

    /**
     * Questa funzione � specifica dell'autenticazione Wsse ed é usata solo per aiutare in questo esempio
     *
     * Per approfondire questa logica, vedere
     * https://github.com/symfony/symfony-docs/pull/3134#issuecomment-27699129
     */
    protected function validateDigest($digest, $nonce, $created, $secret)
    {

        // Verifica che il tempo di creazione non sia nel futuro
        if (strtotime($created) > time()) {
            return false;
        }

        // Scade dopo 1 giorno
        if (time() - strtotime($created) > 3600*24) {
            return false;
        }

        // Valida che nonce *non* sia stato usato negli ultimi 5 minuti
        // se lo é stato, potrebbe trattarsi di un attacco
        if (file_exists($this->cacheDir.'/'.$nonce) && file_get_contents($this->cacheDir.'/'.$nonce) + 300 > time()) {
            throw new NonceExpiredException('Previously used nonce detected');
        }
        // Se la cartella della cache non esiste, va creata
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        //file_put_contents($this->cacheDir.'/'.$nonce, time());

        // Valida la parola segreta
        $expected = base64_encode(sha1(base64_decode($nonce).$created.$secret, true));

        return $digest === $expected;
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof WsseUserToken;
    }
}

