<?php

namespace R3gis\AppBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiException extends HttpException implements R3gisAppBundleApiExceptionInterface {

    const NOT_FOUND = 404;
    const SERVER_ERROR = 500;

    private $options;

    public function __construct($statusCode, $message = null, array $options = array(), \Exception $previous = null, array $headers = array(), $code = 0) {
        $this->options = $options;

        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function getOptions() {
        return $this->options;
    }

}
