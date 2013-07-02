<?php
namespace Lysine;

class Error extends \Exception {
    private $more = array();

    public function __construct($message = null, $code = 0, \Exception $previous = null, array $more = array()) {
        $this->more = $more;
        parent::__construct($message, $code, $previous);
    }

    public function getMore($key = null) {
        return $key === null
             ? $this->more
             : (isset($this->more[$key]) ? $this->more[$key] : false);
    }

    public function setMore(array $more) {
        $this->more = array_merge($this->more, $more);
    }
}

class RuntimeError extends Error {
}

class InvalidArgumentError extends Error {
}

class UnexpectedValueError extends Error {
}

////////////////////////////////////////////////////////////////////////////////
namespace Lysine\HTTP;

class Error extends \Lysine\Error {
    static public function factory($code, $body = null, array $header = array()) {
        $error = new static(\Lysine\HTTP::getStatusMessage($code), $code);

        $more = array();
        if ($body) $more['body'] = $body;
        if ($header) $more['header'] = $header;
        $error->setMore($more);

        return $error;
    }
}

////////////////////////////////////////////////////////////////////////////////
namespace Lysine\Service;

class ConnectionError extends \Lysine\RuntimeError {
}

class RuntimeError extends \Lysine\RuntimeError {
}

////////////////////////////////////////////////////////////////////////////////
namespace Lysine\DataMapper;

class RuntimeError extends \Lysine\InvalidArgumentError {
}

class UndefinedPropertyError extends \Lysine\InvalidArgumentError {
}

class UnexpectedValueError extends \Lysine\UnexpectedValueError {
}

class RefuseUpdateError extends \Lysine\UnexpectedValueError {
}

class NullNotAllowedError extends \Lysine\UnexpectedValueError {
}
