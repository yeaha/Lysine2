<?php
use Lysine\HTTP;

$__start__ = microtime(true);
$app = require __DIR__ .'/../config/boot.php';

try {
    $resp = $app->execute();
} catch (\Exception $ex) {
    $resp = resp()->reset();

    if ($ex instanceof HTTP\Error) {
        $resp->setCode($ex->getCode());
    } else {
        $resp->setCode(HTTP::INTERNAL_SERVER_ERROR);
        Lysine\logger()->exception($ex);
    }

    $body = \Controller::view()->render('_error', array('exception' => $ex));
    $resp->setBody($body);

    if (DEBUG) {
        foreach (__exception_header($ex) as $header)
            $resp->setHeader($header);
    }
}

$__runtime__ = round(microtime(true) - $__start__, 6);
$resp->setHeader('X-Runtime: '.$__runtime__.'s')
     ->execute();

if (!DEBUG && PHP_SAPI == 'fpm-fcgi')
    fastcgi_finish_request();

function __exception_header($exception) {
    $header = array();

    $message = $exception->getMessage();
    if ($pos = strpos($message, "\n"))
        $message = substr($message, 0, $pos);

    $header[] = 'X-Exception-Message: '. $message;
    $header[] = 'X-Exception-Code: '. $exception->getCode();

    foreach (explode("\n", $exception->getTraceAsString()) as $index => $line)
        $header[] = sprintf('X-Exception-Trace-%02d: %s', $index, $line);

    return $header;
}
