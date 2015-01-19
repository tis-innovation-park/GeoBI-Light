<?php

namespace R3gis\AppBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiException extends HttpException implements R3gisAppBundleApiExceptionInterface {

    //Main status codes
    const BAD_REQUEST   = 400;
    const ACCESS_DENIED = 403;
    const NOT_FOUND     = 404;
    const SERVER_ERROR  = 500;
    
    //substatus codes
    const GEOBI_400_NAME_MISSING            = '0001';
    const GEOBI_400_NAME_TOOSHORT           = '0002';
    const GEOBI_400_PASSWORD_MISSING        = '0003';
    const GEOBI_400_PASSWORD_TOOSHORT       = '0004';
    const GEOBI_400_EMAIL_MISSING           = '0005';
    const GEOBI_400_EMAIL_INVALID           = '0006';
    const GEOBI_400_CAPTCHA_MISSING         = '0007';
    const GEOBI_400_CAPTCHA_INVALID         = '0008';
    const GEOBI_400_VALIDATION_AWAITING     = '0009';
    const GEOBI_400_EMAIL_DUPLICATE         = '0010';
    const GEOBI_400_EMAIL_ALREADYVALIDATED  = '0011';
    const GEOBI_400_HASH_MISSING            = '0012';
    const GEOBI_400_HASH_INVALID            = '0013';
    const GEOBI_400_ID_MISSING              = '0014';
    const GEOBI_400_USERCHANGES_MISSING     = '0015';
    const GEOBI_400_USERCHANGES_INVALID     = '0015';
    
    const GEOBI_403_ACCESS_DENIED           = '0001';
    const GEOBI_403_LOGIN_FAILED            = '0002';
    
    const GEOBI_404_EMAIL_NOTFOUND          = '0001';
    const GEOBI_404_ID_NOTFOUND             = '0002';

    private $options;

    public function __construct($statusCode, $message = null, array $options = array(), \Exception $previous = null, array $headers = array(), $code = 0) {
        $this->options = $options;

        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function getOptions() {
        return $this->options;
    }
    
    public function getStatusSubCode() {
        if (!empty($this->options['subCode'])) {
            return $this->options['subCode'];
        }
        return null;
    }

}
