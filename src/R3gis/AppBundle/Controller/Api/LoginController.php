<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use R3gis\AppBundle\Entity\User;

/**
 * @Route("/user")
 */
class LoginController extends Controller {



     /**
     * #@Route("/login", methods = {"POST"})
     *
    public function loginAction(Request $request) {
        
        
        
        // @see http://silex.sensiolabs.org/doc/cookbook/json_request_body.html for post request in application
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {  
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data) ? $data : array());
        }
        
        $response = new JsonResponse();
        
        try {
            $login = $request->request->get('login');
            $password = $request->request->get('password');
            
            if ( $login === '')  {
                throw new \Exception("Missing login.");
            }
            if ( $password === '')  {
                throw new \Exception("Missing password.");
            }
            
            $userRepo  = $this->getDoctrine()->getRepository('R3gisAppBundle:User');
            $user = $userRepo->findOneByLogin($login);
            
            if(!$user || $user->getPassword()!== $password) {
                throw new \Exception("Invalid Login or Password.");
            }
            
            if($user->getStatus()!=='E'){
                throw new \Exception("Account not enabled.");
            }
            
            $response->setData(array(
                'success' => true,
            ));
            
        } catch(\Exception $e) {
            $response->setData(array(
                'success' => false,
                'error' => $e->getMessage()
            ));
        }
        
        return $response;
    }
*/
}
