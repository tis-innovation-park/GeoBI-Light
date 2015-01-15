<?php

namespace R3gis\AppBundle\EventListener;

use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use R3gis\AppBundle\Exception\ApiException;

class ApiExceptionListener {

    private function getExceptionOptions(ApiException $exception) {
        // Add options
        $options = array();

        $i = 0;
        foreach ($exception->getOptions() as $val) {
            $options["p{$i}"] = $val;
            $i++;
        }

        return $options;
    }

    // @TODO: on dev mode add a trace node
    private function getExceptionTrace(\Exception $exception) {

        $result = array();
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $trace) {
            $data = array();
            if (!empty($trace['file'])) {
                $data['file'] = $trace['file'];
                $data['line'] = $trace['line'];
            }
            if (!empty($trace['class'])) {
                $data['method'] = "{$trace['class']}{$trace['type']}{$trace['function']}";
            } else {
                $data['function'] = "{$trace['function']}";
            }
            $result[] = $data;
        }
        
        // reverse array to make steps line up chronologically
        $result = array_reverse($result);
        array_shift($result); // remove {main}
        array_pop($result); // remove call to this method

        return $result;
    }

    public function onKernelException(GetResponseForExceptionEvent $event) {

        // You get the exception object from the received event.
        if ($event->getRequestType() != HttpKernel::MASTER_REQUEST) {
            // Nothing to do
            return;
        }

        // @TODO: get prefix from configuration
        $routePrefix = 'r3gis.api';

        $request = $event->getRequest();
        $routeName = $request->get('_route');

        if (preg_match("/^{$routePrefix}(.*)$/", $routeName) > 0) {

            $exception = $event->getException();
            $code = $exception->getCode();
            $message = $exception->getMessage();

            if ($exception instanceof ApiException) {
                $options = $this->getExceptionOptions($exception);
                $code = $exception->getStatusCode();
            }
            if ($code <= 0) {
                $code = 500;  // Internal server error
            }

            $trace = array();
            // @TODO: Get environmen
            //if ('dev' == $event->getKernel()->getEnvironment()) {
            $trace = $this->getExceptionTrace($exception);
            //}

            $errorNode = array(
                'code' => $code,
                'text' => $message
            );
            if (!empty($options)) {
                $errorNode['params'] = $options;
            }
            if (!empty($trace)) {
                $errorNode['trace'] = $trace;
            }

            $error = array(
                'success' => false,
                'result' => array(
                    'error' => $errorNode));

            // Customize your response object to display the exception details
            $response = new Response();
            $response->setContent(json_encode($error));

            // Send the modified response object to the event
            $event->setResponse($response);
        }
    }

}
