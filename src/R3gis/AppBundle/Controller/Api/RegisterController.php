<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use R3gis\AppBundle\Entity\User;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use R3gis\AppBundle\Exception\ApiException;

/**
 * @Route("/user")
 */
class RegisterController extends Controller {

    /**
     * @Route("/register", methods = {"POST"})
     */
    public function registerAction(Request $request) {

        // @see http://silex.sensiolabs.org/doc/cookbook/json_request_body.html for post request in application
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {  
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data) ? $data : array());
        }

        if ( $request->request->get('name') == '')  {
            throw new ApiException(400, "Missing name.", array('subCode'=>  ApiException::GEOBI_400_NAME_MISSING));
        }
        if ( strlen($request->request->get('name')) < 2)  {
            throw new ApiException(400, "Name too short.", array('subCode'=>ApiException::GEOBI_400_NAME_TOOSHORT));
        }
        if( $request->request->get('password')==''){
            throw new ApiException(400, 'Missing password.', array('subCode'=>ApiException::GEOBI_400_PASSWORD_MISSING));
        }
        if( strlen($request->request->get('password'))<6){
            throw new ApiException(400, 'Password too short.', array('subCode'=>ApiException::GEOBI_400_PASSWORD_TOOSHORT));
        }
        if ( $request->request->get('email') == '')  {
            throw new ApiException(400, "Missing email.", array('subCode'=>ApiException::GEOBI_400_EMAIL_MISSING));
        }
        if ( filter_var( $request->request->get('email'), FILTER_VALIDATE_EMAIL) === false)  {
            throw new ApiException(400, "Invalid email.", array('subCode'=>ApiException::GEOBI_400_EMAIL_INVALID));
        }
        if ( $request->request->get('captcha') == '' && !$this->get('security.context')->isGranted('ROLE_ADMIN'))  {
            throw new ApiException(400, "Missing captcha.", array('subCode'=>ApiException::GEOBI_400_CAPTCHA_MISSING));
        }
        $captcha = $request->request->get('captcha');
        //validate Captcha
        $session = $request->getSession();
        if(     !$this->get('security.context')->isGranted('ROLE_ADMIN') && 
                (!$captcha || !$session->get("captcha") || $captcha !== $session->get("captcha"))
        ) {
            throw new ApiException(400, 'Invalid Captcha.', array('subCode'=>ApiException::GEOBI_400_CAPTCHA_INVALID));
        }
        //make sure it's usable only once
        $session->remove("captcha");

        // Check user email and login
        $userRepo  = $this->getDoctrine()->getRepository('R3gisAppBundle:User');
        $user = $userRepo->findOneByEmail($request->request->get('email'));
        if ($user) {
            if ($user->getStatus() == 'W') { // SS: Where to put status?
                throw new ApiException(400, "Email validation needed.", array('subCode'=>ApiException::GEOBI_400_VALIDATION_AWAITING));
            } else {
                throw new ApiException(400, "Email already exists.", array('subCode'=>ApiException::GEOBI_400_EMAIL_DUPLICATE));
            }    
        }

        // Persist
        $group = $this->getDoctrine()->getRepository('R3gisAppBundle:Group')->findOneByName('MAP_PRODUCER');

        $user = new User();

        $encoder = $this->get('security.encoder_factory')->getEncoder($user);
        $password = $encoder->encodePassword($request->request->get('password'), $user->getSalt());

        $user->setPassword( $password );
        $user->setEmail( $request->request->get('email') );
        $user->setName( $request->request->get('name') );
        $user->setStatus( 'W' );
        $user->setGroup( $group );

        $user->setValidationHashCreatedTime(new \DateTime(date('Y-m-d H:i:s')));
        $user->setValidationHash(md5(uniqid(mt_rand(), true)));

        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        $id = $user->getId();
        // Send email

        $this->sendRegistrationMailToUser( $user );

//            $language = $this->getDoctrine()
//                    ->getRepository('R3gisAppBundle:Language')
//                    ->find($request->getLocale()||'en');
//
//            $user->setLanguage($language);

        $response = new JsonResponse();
        $response->setData(array(
            'success' => true,
            'result' => array('id' => $id)
        ));

        return $response;
    }
    
    
    /**
     * @Route("/requestmail", methods = {"POST"})
     */
    public function registerRequestNewMailAction(Request $request) {

        $response = new JsonResponse();
        $json = json_decode($request->getContent());
        $email = $json->email;
        $captcha = $json->captcha;

        // Validate: SS: See Symfony validation
        if ( $email == '')  {
            throw new ApiException(400, "Missing email", array('subCode'=>ApiException::GEOBI_400_EMAIL_MISSING));
        }
        if ( filter_var( $email, FILTER_VALIDATE_EMAIL) === false)  {
            throw new ApiException(400, "Invalid email", array('subCode'=>ApiException::GEOBI_400_EMAIL_INVALID));
        }
        if ( $captcha == '' && !$this->get('security.context')->isGranted('ROLE_ADMIN'))  {
            throw new ApiException(400, "Missing captcha", array('subCode'=>ApiException::GEOBI_400_CAPTCHA_MISSING));
        }
        //validate Captcha
        $session = $request->getSession();
        if(     !$this->get('security.context')->isGranted('ROLE_ADMIN') && 
                (!$captcha || !$session->get("captcha") || $captcha !== $session->get("captcha"))
        ) {
            throw new ApiException(400, 'Invalid Captcha.', array('subCode'=>ApiException::GEOBI_400_CAPTCHA_INVALID));
        }
        //make sure it's usable only once
        $session->remove("captcha");

        // Check user email and login
        $userRepo  = $this->getDoctrine()->getRepository('R3gisAppBundle:User');
        $user = $userRepo->findOneByEmail($email);
        if (!$user) {
            throw new ApiException(404, "Email not found", array('subCode'=>ApiException::GEOBI_404_EMAIL_NOTFOUND));
        }

        if ($user->getStatus() != 'W') { // SS: Where to put status?
                throw new ApiException(400, "Email already confirmed", array('subCode'=>ApiException::GEOBI_400_EMAIL_ALREADYVALIDATED));
            }    

        $this->sendRegistrationMailToUser( $user );

        $response->setData(array(
            'success' => true,
            'result' => array()
        ));
        return $response;
    }
    
    // SS: Move elsewhere!
    private function sendRegistrationMailToUser(User $user) {
        $message = \Swift_Message::newInstance()
        ->setSubject('Registration GeoBI')                    		// SS: Mettere in configurazione
        ->setFrom('registration@'.$this->getRequest()->getHost())       // SS: Mettere in configurazione
        ->setTo($user->getEmail())
        ->setBody(
            $this->renderView(
                'R3gisAppBundle:Default:validation.mail.html.twig',
                array(
                    'name' => $user->getName(),
                    'hash' => $user->getValidationHash(),
                )
            ), 'text/html'
        );
        
        $this->get('mailer')->send($message);
        
        // TODO: Log di spedizione!
    }

}
