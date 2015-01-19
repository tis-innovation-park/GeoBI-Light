<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use R3gis\AppBundle\Entity\User;
use R3gis\AppBundle\Exception\ApiException;

/**
 * 
 * @Route("/resetpassword")
 */
class ResetPasswordController extends Controller {
    
    /**
     * @api {POST} /resetpassword/request  request Email
     * @apiName requestAction
     * @apiGroup ResetPassword
     *
     * @apiDescription Request email for Password reset. 
     *                 Returns json with success=true, if user with that email exists and email was sent.
     * 
     * @apiParam {String} email          emailaddress of user
     * @apiParam {String} captcha        captcha
     * 
     */
    /**
     * @Route("/request", methods = {"POST"})
     */
    public function requestAction(Request $request) {
        $json = json_decode($request->getContent());
        $email = $json->email;
        $captcha = $json->captcha;
        
        if($email==null || trim($email)==='') {
            throw new ApiException(ApiException::BAD_REQUEST,'No email specified.', array('subCode'=>  ApiException::GEOBI_400_EMAIL_MISSING));
        }
        if ( $captcha == '' && !$this->get('security.context')->isGranted('ROLE_ADMIN'))  {
            throw new ApiException(ApiException::BAD_REQUEST, "Missing captcha", array('subCode'=>  ApiException::GEOBI_400_CAPTCHA_MISSING));
        }
        //validate Captcha
        $session = $request->getSession();
        if(     !$this->get('security.context')->isGranted('ROLE_ADMIN') && 
                (!$captcha || !$session->get("captcha") || $captcha !== $session->get("captcha"))
        ) {
            throw new ApiException(ApiException::BAD_REQUEST, "Missing captcha", array('subCode'=>  ApiException::GEOBI_400_CAPTCHA_INVALID));
        }
        //make sure it's usable only once
        $session->remove("captcha");

        $em = $this->getDoctrine()->getManager();
        $user = $em
                ->getRepository('R3gisAppBundle:User')
                ->findOneByEmail($email)
        ;

        if($user==null) {
            throw new ApiException(ApiException::NOT_FOUND, "User with this email not found.", array('subCode'=>  ApiException::GEOBI_404_EMAIL_NOTFOUND));
        }

        $hash = md5(uniqid(mt_rand(), true));

        $user->setResetPasswordHash($hash);
        $user->setResetPasswordHashCreatedTime(new \DateTime(date('Y-m-d H:i:s')));
        $em->flush();

        $this->sendResetPwMailToUser($user);

        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setData(
            array(
                'success' => true,
            )
        );
        return $response;
    }
    
    private function sendResetPwMailToUser(User $user) {
        $message = \Swift_Message::newInstance()
        ->setSubject('Reset Password')                    // SS: Mettere in configurazione
        ->setFrom('mittente@example.com')       // SS: Mettere in configurazione
        ->setTo($user->getEmail())
        ->setBody(
            $this->renderView(
                'R3gisAppBundle:Default:resetpw.mail.html.twig',
                array(
                    'name' => $user->getName(),
                    'hash' => $user->getResetPasswordHash(),
                )
            ), 'text/html'
        );
        
        $this->get('mailer')->send($message);
        
        // TODO: Log di spedizione!
    }

    /**
     * @api {get} /resetpassword/reset/{hash}  reset link
     *                                         
     * @apiName resetLinkAction
     * @apiGroup ResetPassword
     *
     * @apiDescription Link the client gets in his email. Will redirect to angular resetpw/error page, depending if hash is valid or not.
     * 
     * @apiParam {String} hash          reset password-hash
     * 
     */
    /**
     * 
     * @Route("/reset/{hash}", methods={"GET"}, name="resetlink")
     */
    public function resetLinkAction(Request $request, $hash=null){

        if($hash==null || $hash==='') {
            throw new ApiException(ApiException::BAD_REQUEST, "Hash missing.", array('subCode'=>  ApiException::GEOBI_400_HASH_MISSING));
        }

        $em = $this->getDoctrine()->getManager();
        $userRepo = $em->getRepository('R3gisAppBundle:User');
        $user = $userRepo->findOneByResetPasswordHash($hash);

        if($user==null || $user->getResetPasswordHashCreatedTime()==null) {
            throw new ApiException(ApiException::BAD_REQUEST, "Hash invalid or expired.", array('subCode'=>  ApiException::GEOBI_400_HASH_INVALID));
        }
        if(time() - $user->getResetPasswordHashCreatedTime()->getTimestamp() > 3600*24) {
            throw new ApiException(ApiException::BAD_REQUEST, "Hash invalid or expired.", array('subCode'=>  ApiException::GEOBI_400_HASH_INVALID));
        }

        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setData(
            array(
                "success" => true,
                "result" => array(
                    'expireDate'=> $user->getResetPasswordHashCreatedTime()->getTimestamp()+3600*24
                )
            )
        );
        
        return $response;
    }
    
    /**
     * @api {PUT} /resetpassword/reset/{hash}  change Password
     * @apiName changePasswordAction
     * @apiGroup ResetPassword
     *
     * @apiDescription Returns json with success=true, if hash and password are valid and password was changed.
     * 
     * @apiParam  {String} hash          reset password-hash
     * @apiParam  {String} password      the new password
     * 
     */
    /**
     * 
     * @Route("/reset/{hash}", methods={"PUT"}, name="changepassword")
     */
    public function changePasswordAction(Request $request, $hash=''){
        $json = json_decode($request->getContent());
        $password = $json->password;
        if($hash==null || $hash==='' ) {
            throw new ApiException(ApiException::BAD_REQUEST, "Hash missing.", array('subCode'=>  ApiException::GEOBI_400_HASH_MISSING));
        }
        if($password==null || $password==='') {
            throw new ApiException(ApiException::BAD_REQUEST, "Password missing.", array('subCode'=>  ApiException::GEOBI_400_PASSWORD_MISSING));
        }
        if(strlen($password)>6) {
            throw new ApiException(ApiException::BAD_REQUEST, "Password missing.", array('subCode'=>  ApiException::GEOBI_400_PASSWORD_TOOSHORT));
        }

        $em = $this->getDoctrine()->getManager();
        $userRepo = $em->getRepository('R3gisAppBundle:User');
        $user = $userRepo->findOneByResetPasswordHash($hash);

        if($user==null || $user->getResetPasswordHashCreatedTime()==null) {
            throw new ApiException(ApiException::BAD_REQUEST, "Hash invalid or expired.", array('subCode'=>  ApiException::GEOBI_400_HASH_INVALID));
        }

        if(time() - $user->getResetPasswordHashCreatedTime()->getTimestamp() > 3600*24) {
            throw new ApiException(ApiException::BAD_REQUEST, "Hash invalid or expired.", array('subCode'=>  ApiException::GEOBI_400_HASH_INVALID));
        }

        $encoder = $this->get('security.encoder_factory')->getEncoder($user);
        $password = $encoder->encodePassword($password, null); //$user->getSalt());
        $user->setPassword($password);
        $user->setResetPasswordHash(null);
        $user->setResetPasswordHashCreatedTime(null);
        $em->flush();

        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setData(array('success' => true,));
        return $response;
    }
}
