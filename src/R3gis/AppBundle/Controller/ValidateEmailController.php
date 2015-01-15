<?php

namespace R3gis\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use R3gis\AppBundle\ApiException;

class ValidateEmailController extends Controller {

    /**
     * 
     * @Route("/validate/{hash}", methods={"GET"}, name="validateemail")
     */
    public function validateEmailAction(Request $request, $hash=null){
        $response = new Response();
        
        try
        {
            $em = $this->getDoctrine()->getManager();
            if($hash==null) {
                throw new ApiException(400, 'Hash invalid.');
            }
            
            $userRepo = $em->getRepository('R3gisAppBundle:User');
            $user = $userRepo->findOneByValidationHash($hash);
            
            if($user==null || $user->getValidationHashCreatedTime()==null) {
                throw new ApiException(404, 'Hash invalid or expired.');
            }
            
            if(time() - $user->getValidationHashCreatedTime()->getTimestamp() > 3600*24) {
                throw new ApiException(404, 'Hash invalid or expired.');
            }
            
            if($user->getStatus() !=='W'){
                throw new ApiException(400, 'User is not waiting for Validation.');
            }
            
            $user->setStatus('E');
            $user->setValidationHash(null);
            $user->setValidationHashCreatedTime(null);
            $em->flush();
            
            $params = array("success"=>true);
            return $this->render('R3gisAppBundle:Default:validated.html.twig', $params);
            
        } catch (\Exception $e) {
            $params = array("success"=>false, "error"=>$e->getMessage());
            return $this->render('R3gisAppBundle:Default:validated.html.twig', $params);
        }
    }
}
