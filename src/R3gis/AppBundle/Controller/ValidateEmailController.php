<?php

namespace R3gis\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
                throw new \Exception('Hash invalid.', 400);
            }
            
            $userRepo = $em->getRepository('R3gisAppBundle:User');
            $user = $userRepo->findOneByValidationHash($hash);
            
            if($user==null || $user->getValidationHashCreatedTime()==null) {
                throw new \Exception('Hash invalid or expired.', 404);
            }
            
            if(time() - $user->getValidationHashCreatedTime()->getTimestamp() > 3600*24) {
                throw new \Exception('Hash invalid or expired.', 404);
            }
            
            if($user->getStatus() !=='W'){
                throw new \Exception('User is not waiting for Validation.', 400);
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
