<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use R3gis\AppBundle\Exception\ApiException;

class DefaultController extends Controller
{
    
    
    /**
     * #@Route("/{all}", requirements={"all" = ".*"}, defaults={"all" = null}, name="r3gis.api.invalid_route_full" )
     */
    public function indexAllAction($all)
    {
        throw new ApiException(ApiException::NOT_FOUND, 'Route not found');
    }
    
}
