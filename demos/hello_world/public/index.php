<?php
use Lysine\HTTP;

$__start__ = microtime(true);
$app = require __DIR__ .'/../config/boot.php';

try {
    $response = $app->execute();
} catch (HTTP\Error $exception) {
    $response = __exception_response($exception->getCode(), $exception);
} catch (\Exception $exception) {
    Lysine\logger()->exception($exception);

    if ($exception instanceof \Lysine\Service\ConnectionError) {
        $response = __exception_response(HTTP::SERVICE_UNAVAILABLE, $exception);
    } else {
        $response = __exception_response(HTTP::INTERNAL_SERVER_ERROR, $exception);
    }
}

$__runtime__ = round(microtime(true) - $__start__, 6);
$response->setHeader('X-Runtime: '.$__runtime__.'s')
         ->execute();

if (!DEBUG && PHP_SAPI == 'fpm-fcgi')
    fastcgi_finish_request();

////////////////////////////////////////////////////////////////////////////////

function __exception_response($code, $exception) {
    $resp = resp()->reset()->setCode($code);

    if (!req()->isAjax()) {
        $body = \Controller::view()->render('_error', array('exception' => $exception));
        $resp->setBody($body);
    }

    if (DEBUG) {
        foreach (__exception_header($exception) as $header)
            $resp->setHeader($header);
    }

    return $resp;
}

function __exception_header($exception) {
    $header = array();

    $message = $exception->getMessage();
    if ($pos = strpos($message, "\n"))
        $message = substr($message, 0, $pos);

    $header[] = 'X-Exception-Class: '. get_class($exception);
    $header[] = 'X-Exception-Message: '. $message;
    $header[] = 'X-Exception-Code: '. $exception->getCode();

    foreach (explode("\n", $exception->getTraceAsString()) as $index => $line)
        $header[] = sprintf('X-Exception-Trace-%02d: %s', $index, $line);

    return $header;
}
