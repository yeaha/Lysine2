<?php
namespace Lysine\MVC;

use Lysine\HTTP;

class Application {
    static protected $support_methods = array('HEAD', 'GET', 'POST', 'PUT', 'DELETE');

    protected $router;

    protected $include_path = array();

    public function __construct(array $options = array()) {
        if (isset($options['include_path']) && is_array($options['include_path']))
            $this->include_path = $options['include_path'];

        spl_autoload_register(array($this, 'loadClass'));
    }

    public function setRouter($router) {
        $this->router = $router;
        return $this;
    }

    public function getRouter() {
        return $this->router;
    }

    public function execute($uri = null, $method = null) {
        $method = $method ?: req()->method();
        if (!in_array($method, self::$support_methods))
            throw HTTP\Error::factory(HTTP::NOT_IMPLEMENTED);

        $uri = $uri ?: req()->requestUri();

        \Lysine\logger()->debug($method .' '. $uri);
        if (!req()->isGET() && ($params = post() ?: put()))
            \Lysine\logger()->debug('Parameters: '. http_build_query($params));

        $response = $this->getRouter()->dispatch($uri, $method);

        return $response instanceof \Lysine\HTTP\Response
             ? $response
             : resp()->setBody($response);
    }

    //////////////////// protected method ////////////////////

    public function loadClass($class) {
        $file = str_replace('\\', DIRECTORY_SEPARATOR, strtolower($class)) .'.php';
        foreach ($this->include_path as $path) {
            $f = $path .DIRECTORY_SEPARATOR. $file;
            if (!is_file($f)) continue;

            require $f;
            return class_exists($class, false) || interface_exists($class, false);
        }

        return false;
    }
}

class Router {
    const BEFORE_DISPATCH_EVENT = 'before dispatch';
    const AFTER_DISPATCH_EVENT = 'after dispatch';

    protected $base_uri;
    protected $rewrite = array();
    protected $namespace = array();

    public function __construct(array $config = null) {
        if (isset($config['namespace']))
            foreach ($config['namespace'] as $path => $ns) {
                $path = '/'. trim($path, '/');
                $this->namespace[$path] = $ns;
            }

        if (isset($config['rewrite']))
            $this->rewrite = $config['rewrite'];

        if (isset($config['base_uri']) && ($base_uri = rtrim($config['base_uri'], '/')))
            $this->base_uri = strtolower($base_uri) .'/';
    }

    public function dispatch($uri, $method) {
        $uri = parse_url(strtolower($uri), PHP_URL_PATH);
        $uri = rtrim($uri, '/') .'/';

        if ($base_uri = $this->base_uri) {
            if (strpos($uri, $base_uri) !== 0)
                throw HTTP\Error::factory(HTTP::NOT_FOUND);

            $uri = '/'.substr($uri, strlen($base_uri));
        }

        list($class, $params) = $this->matchClass($uri);

        \Lysine\logger()->debug('Dispatch to controller: '. $class);

        if (!$class || !class_exists($class))
            throw HTTP\Error::factory(HTTP::NOT_FOUND);

        \Lysine\Event::instance()->fire($this, self::BEFORE_DISPATCH_EVENT, array($class));

        $controller = new $class;
        if (method_exists($controller, '__before_run')) {
            $response = $params
                      ? call_user_func_array(array($controller, '__before_run'), $params)
                      : $controller->__before_run();

            if ($response)
                return $response;
        }

        $method = $method ?: req()->method();
        if ($method == 'HEAD') $method = 'GET';

        if (!is_callable(array($controller, $method)))
            throw HTTP\Error::factory(HTTP::METHOD_NOT_ALLOWED);

        $response = $params
                  ? call_user_func_array(array($controller, $method), $params)
                  : $controller->$method();

        if (method_exists($controller, '__after_run'))
            $controller->__after_run($response);

        \Lysine\Event::instance()->fire($this, self::AFTER_DISPATCH_EVENT, array($class, $response));

        return $response;
    }

    //////////////////// protected method ////////////////////

    protected function matchClass($uri) {
        foreach ($this->rewrite as $re => $class) {
            if (preg_match($re, $uri, $match))
                return array($class, array_slice($match, 1));
        }

        // 路径对应的controller namespace
        foreach ($this->namespace as $path => $namespace) {
            if (strpos($uri, $path) !== 0) continue;

            $class = array();
            $uri = substr($uri, strlen($path)) ?: '/index';
            foreach (explode('/', $uri) as $word)
                if ($word) $class[] = ucfirst($word);

            $class = implode('\\', $class);
            return array($namespace.'\\'.$class, array());
        }

        throw HTTP\Error::factory(HTTP::NOT_FOUND);
    }
}

class View {
    const BLOCK_REPLACE = 'replace';
    const BLOCK_PREPEND = 'prepend';
    const BLOCK_APPEND = 'append';

    protected $dir;

    protected $extend;
    protected $vars = array();
    protected $block_stack = array();
    protected $block_content = array();

    public function __construct($dir) {
        $this->dir = $dir;
    }

    public function __clone() {
        $this->reset();
    }

    public function set($name, $val) {
        $this->vars[$name] = $val;
    }

    public function get($name) {
        return isset($this->vars[$name])
             ? $this->vars[$name]
             : null;
    }

    public function reset() {
        $this->extend = null;
        $this->vars = array();
        $this->block_stack = array();
        $this->block_content = array();

        return $this;
    }

    public function render($view, array $vars = array()) {
        if ($vars)
            $this->vars = array_merge($this->vars, $vars);

        $output = $this->includes($view, array(), true);

        while ($this->block_stack)
            $this->endBlock();

        if (!$extend = $this->extend)
            return $output;

        $this->extend = null;
        return $this->render($extend);
    }

    //////////////////// protected method ////////////////////

    protected function includes($view, array $vars = array(), $return_content = false) {
        $file = $this->findFile($view);
        $vars = $vars ? array_merge($this->vars, $vars) : $this->vars;

        ob_start();

        try {
            extract($vars);
            require $file;
        } catch (\Exception $ex) {
            while (ob_get_level())
                ob_end_clean();

            throw $ex;
        }

        $output = ob_get_clean();

        if ($return_content)
            return $output;

        echo $output;
    }

    protected function block($name, $method = null) {
        $this->block_stack[] = array($name, $method ?: self::BLOCK_REPLACE);
        ob_start();
    }

    protected function endBlock() {
        if (!$this->block_stack)
            return false;

        list($block_name, $block_method) = array_pop($this->block_stack);
        $output = ob_get_clean();

        if (isset($this->block[$block_name])) {
            if ($block_method == self::BLOCK_PREPEND) {
                $output = $this->block[$block_name] . $output;
            } elseif ($block_method == self::BLOCK_APPEND) {
                $output = $output . $this->block[$block_name];
            } else {
                $output = $this->block[$block_name];
            }
        }

        if ($this->extend && !$this->block_stack) {
            $this->block_content[$block_name] = $output;
        } else {
            unset($this->block_content[$block_name]);
            echo $output;
        }
    }

    protected function showBlock($name) {
        if (!isset($this->block_content[$name]))
            return false;

        echo $this->block_content[$name];
        unset($this->block_content[$name]);
    }

    protected function extend($view) {
        $this->extend = $view;
    }

    protected function findFile($view) {
        $file = $this->dir .DIRECTORY_SEPARATOR. $view .'.php';

        if (!$result = realpath($file))
            throw new \Lysine\RuntimeError('View file '.$file.' not exist!');

        return $result;
    }
}
