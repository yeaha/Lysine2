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
             : isset($this->more[$key]) ? $this->more[$key] : false;
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
    public function getStatusLine() {
        return sprintf('HTTP\1.1 %d %s', $this->getCode(), $this->getMessage());
    }

    static public function factory($code) {
        return new static(\Lysine\HTTP::getStatusMessage($code), $code);
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

class NullNotAllowedError extends \Lysine\UnexpectedValueError {
}
