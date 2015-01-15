<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Gregwar\Captcha\CaptchaBuilder;

/**
 * @Route("/captcha")
 */
class CaptchaController extends Controller {

    /**
     * @api {GET} /captcha/request.json  request captcha
     * @apiName getCaptchaAction
     * @apiGroup captcha
     *
     * @apiDescription Request captcha image
     *                 Returns json with success=true and the image in result as base64 image.
     * 
     * 
     */
    /**
     * Returns Captcha image and saves the phrase to session.
     * 
     * @Route("/request.json", methods = {"GET"}, name="r3gis.app.captcha.get_captcha")
     */
    public function getCaptchaAction(Request $request) {

        // @see http://silex.sensiolabs.org/doc/cookbook/json_request_body.html for post request in application
        $response = new JsonResponse();

        $captcha = new CaptchaBuilder;
        $captcha->build();
        $session = $request->getSession();
        $session->set("captcha", $captcha->getPhrase());
        $captchaImage = $captcha->inline();

        $response->setData(array(
            'success' => true,
            'result' => $captchaImage));

        return $response;
    }

    /**
     * validate captcha / only for testing.
     * 
     * ###testonly###@Route("/validate/{phrase}", methods = {"GET"}, name="r3gis.app.captcha.get_image")
     */
    public function validateAction(Request $request, $phrase="") {

        $session = $request->getSession();
        //validate
        if(!$phrase || !$session->get("captcha") || $phrase !== $session->get("captcha")) {
            $response = new JsonResponse();
            $response->setStatusCode(200);
            $response->setData(
                array(
                    "success"=>false
                )
            );
            return $response;
        }
        
        //make sure it's usable only once
        $session->remove("captcha");
        
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setData(
            array(
                "success"=>true
            )
        );
        return $response;
    }

}
