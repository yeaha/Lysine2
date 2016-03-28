<?php

namespace Lysine {
    class Exception extends \Exception
    {
        private $more = array();

        public function __construct($message = null, $code = 0, \Exception $previous = null, array $more = array())
        {
            $this->more = $more;
            parent::__construct($message, $code, $previous);
        }

        public function getMore($key = null)
        {
            return $key === null
                 ? $this->more
                 : (isset($this->more[$key]) ? $this->more[$key] : false);
        }

        public function setMore(array $more)
        {
            $this->more = array_merge($this->more, $more);
        }
    }
}

namespace Lysine\HTTP {
    class Exception extends \Lysine\Exception
    {
        public static function factory($code, $body = null, array $header = array())
        {
            $error = new static(\Lysine\HTTP::getStatusMessage($code), $code);

            $more = array();
            if ($body) {
                $more['body'] = $body;
            }
            if ($header) {
                $more['header'] = $header;
            }
            $error->setMore($more);

            return $error;
        }
    }
}

namespace Lysine\Service {
    class ConnectionException extends \Lysine\Exception
    {
    }
}
