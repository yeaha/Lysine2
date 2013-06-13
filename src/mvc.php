<?php
namespace Lysine\MVC;

use Lysine\HTTP;

class Application {
    static protected $support_methods = array('HEAD', 'GET', 'POST', 'PUT', 'DELETE');

    protected $router;

    protected $include_path = array();

    public function __construct(array $options = array()) {
        if (isset($options['include_path']) && is_array($options['include_path'])) {
            foreach ($options['include_path'] as $path) {
                if (!$path = realpath($path))
                    continue;

                $this->include_path[] = $path . DIRECTORY_SEPARATOR;
            }
        }

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

        $response = $this->getRouter()->execute($uri, $method);

        return $response instanceof \Lysine\HTTP\Response
             ? $response
             : resp()->setBody($response);
    }

    public function loadClass($class) {
        $file = str_replace('\\', DIRECTORY_SEPARATOR, strtolower($class)) .'.php';
        foreach ($this->include_path as $path) {
            if (!$file = realpath($path . $file))
                continue;

            if (strpos($file, $path) !== 0)
                continue;

            require $file;
            return true;
        }
    }
}

class Router {
    use \Lysine\Traits\Event;

    const BEFORE_DISPATCH_EVENT = 'before dispatch';
    const AFTER_DISPATCH_EVENT = 'after dispatch';

    protected $base_uri;
    protected $rewrite = array();
    protected $namespace = array();

    public function __construct(array $config = null) {
        if (isset($config['namespace']))
            $this->namespace = $config['namespace'];

        if (isset($config['rewrite']))
            $this->rewrite = $config['rewrite'];

        if (isset($config['base_uri']))
            $this->base_uri = $this->normalizePath($config['base_uri']);
    }

    public function execute($uri, $method) {
        list($class, $params, $path) = $this->dispatch($uri);

        \Lysine\logger()->debug('Dispatch to controller: '. $class);

        if (!$class || !class_exists($class)) {
            $exception = HTTP\Error::factory(HTTP::NOT_FOUND);
            $exception->setMore(array(
                'class' => $class,
                'path' => $path,
            ));

            throw $exception;
        }

        $this->fireEvent(self::BEFORE_DISPATCH_EVENT, array($class, $path));

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

        $this->fireEvent(self::AFTER_DISPATCH_EVENT, array($class, $response));

        return $response;
    }

    // return array($class, $params, $path)
    public function dispatch($uri) {
        $path = $this->normalizePath( parse_url($uri, PHP_URL_PATH) );

        if ($base_uri = $this->base_uri) {
            if (strpos($path.'/', $base_uri.'/') !== 0)
                throw HTTP\Error::factory(HTTP::NOT_FOUND);

            $path = $this->normalizePath(substr($path, strlen($base_uri)));
        }

        $dest = $this->rewrite($path) ?: $this->convert($path);
        if ($dest) return $dest;

        throw HTTP\Error::factory(HTTP::NOT_FOUND);
    }

    //////////////////// protected method ////////////////////
    // return array($class, $params, $path)
    protected function rewrite($path) {
        foreach ($this->rewrite as $re => $class) {
            if (preg_match($re, $path, $match))
                return array($class, array_slice($match, 1), $path);
        }
        return false;
    }

    // 把路径转换为controller
    // return array($class, $params, $path)
    protected function convert($path) {
        // 匹配controller之前，去掉路径里的文件扩展名
        $pathinfo = pathinfo($path);
        $path = $this->normalizePath( $pathinfo['dirname'] .'/'. $pathinfo['filename'] );

        // 路径对应的controller namespace
        foreach ($this->namespace as $ns_path => $ns) {
            $ns_path = $this->normalizePath($ns_path);
            if ($ns_path != '/' && strpos($path.'/', $ns_path.'/') !== 0)
                continue;

            $class = array();
            $path = substr($path, strlen($ns_path)) ?: '/index';
            foreach (explode('/', $path) as $word)
                if ($word) $class[] = ucfirst($word);

            $class = implode('\\', $class);
            return array($ns.'\\'.$class, array(), $this->normalizePath($path));
        }

        return false;
    }

    protected function normalizePath($path) {
        return '/'. trim(strtolower($path), '/');
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

    protected $include_views = array();

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
        $this->include_views = array();

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
        $this->include_views[$view] = 1;

        $file = $this->findFile($view);
        $vars = $vars ? array_merge($this->vars, $vars) : $this->vars;

        $ob_level = ob_get_level();
        ob_start();

        try {
            extract($vars);
            require $file;
        } catch (\Exception $ex) {
            // 外面可能已经调用过ob_start()，所以这里不能全部删除干净
            while (ob_get_level() > $ob_level)
                ob_end_clean();

            throw $ex;
        }

        $output = ob_get_clean();

        if ($return_content)
            return $output;

        echo $output;
    }

    protected function includeOnce($view) {
        if (!isset($this->include_views[$view]))
            $this->includes($view);
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

        if (isset($this->block_content[$block_name])) {
            if ($block_method == self::BLOCK_PREPEND) {
                $output = $this->block_content[$block_name] . $output;
            } elseif ($block_method == self::BLOCK_APPEND) {
                $output = $output . $this->block_content[$block_name];
            } else {
                $output = $this->block_content[$block_name];
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

        if (!is_file($file))
            throw new \Lysine\RuntimeError('View file '.$file.' not exist!');

        return $file;
    }
}
