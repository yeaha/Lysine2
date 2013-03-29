<?php
use Lysine\HTTP;

$__start__ = microtime(true);

set_exception_handler('__exception_handler');

try {
    $app = require __DIR__ .'/config/boot.php';
    $response = $app->execute();
} catch (HTTP\Error $exception) {
    Lysine\logger()->debug( HTTP::getStatusHeader($exception->getCode()) );
    $response = __exception_response($exception->getCode(), $exception);
} catch (\Exception $exception) {
    $response = __exception_handler($exception, true);
}

$__runtime__ = round(microtime(true) - $__start__, 6);
$response->setHeader('X-Runtime: '.$__runtime__.'s')
         ->execute();

if (!DEBUG && PHP_SAPI == 'fpm-fcgi')
    fastcgi_finish_request();

////////////////////////////////////////////////////////////////////////////////
function __exception_handler($exception, $call = false) {
    try {
        \Lysine\logger()->exception($exception);

        if (PHP_SAPI == 'cli') {
            echo $exception . PHP_EOL;
            exit(1);
        }

        $response = __exception_response(HTTP::INTERNAL_SERVER_ERROR, $exception);

        if ($call)
            return $response;

        $response->execute();
    } catch (\Exception $e) {
        error_log($exception . PHP_EOL . $e);

        if (ini_get('display_errors'))
            echo $exception . PHP_EOL . $e . PHP_EOL;
    }

    exit(1);
}

function __exception_response($code, $exception) {
    $resp = resp()->reset()->setCode($code);

    if (DEBUG) {
        foreach (__exception_header($exception) as $header)
            $resp->setHeader($header);
    }

    if (req()->isAjax())
        return $resp;

    $body = \Controller::view()->render('_error', array('exception' => $exception));
    $resp->setBody($body);

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
