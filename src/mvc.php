<?php
namespace Lysine\MVC;

use Lysine\HTTP;

class Application {
    static protected $support_methods = array('HEAD', 'GET', 'POST', 'PUT', 'DELETE', 'PATCH');

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
        $method = $method ?: req()->getMethod();
        if (!in_array($method, self::$support_methods))
            throw HTTP\Exception::factory(HTTP::NOT_IMPLEMENTED);

        $uri = $uri ?: req()->getRequestURI();

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
        if (!preg_match('/[0-9a-z\._\/\\\\]+/', $file))
            return false;

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

        $this->fireEvent(self::BEFORE_DISPATCH_EVENT, array($class, $path));

        $controller = new $class;
        if (method_exists($controller, '__before_run')) {
            $response = $params
                      ? call_user_func_array(array($controller, '__before_run'), $params)
                      : $controller->__before_run();

            if ($response)
                return $response;
        }

        $method = $method ?: req()->getMethod();
        if ($method == 'HEAD') $method = 'GET';

        if (!is_callable(array($controller, $method)))
            throw HTTP\Exception::factory(HTTP::METHOD_NOT_ALLOWED);

        $response = $params
                  ? call_user_func_array(array($controller, $method), $params)
                  : $controller->$method();

        if (method_exists($controller, '__after_run')) {
            $result = $controller->__after_run($response);
            if ($result !== null) {
                $response = $result;
            }
        }

        $this->fireEvent(self::AFTER_DISPATCH_EVENT, array($class, $response));

        return $response;
    }

    // return array($class, $params, $path)
    public function dispatch($uri) {
        $path = $this->normalizePath( parse_url($uri, PHP_URL_PATH) );
        $exception_more = array();

        do {
            if ($base_uri = $this->base_uri) {
                if (strpos($path.'/', $base_uri.'/') !== 0)
                    break;

                $path = $this->normalizePath(substr($path, strlen($base_uri)));
            }

            $exception_more['path'] = $path;

            if (!$result = $this->rewrite($path) ?: $this->convert($path))
                break;

            list($class, $params,) = $result;
            $exception_more['controller'] = $class;
            $exception_more['params'] = $params;

            if (!class_exists($class))
                break;

            return $result;
        } while (false);

        $exception = HTTP\Exception::factory(HTTP::NOT_FOUND);
        $exception->setMore($exception_more);
        throw $exception;
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
        $path = strtolower($path);

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
        return '/'. trim($path, '/');
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

    protected $included_views = array();

    public function __construct($view_dir) {
        if (!$dir = realpath($view_dir))
            throw new \RuntimeException('View directory '.$view_dir.' not exist!');

        $this->dir = $dir.DIRECTORY_SEPARATOR;
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
        $this->included_views = array();

        return $this;
    }

    public function render($view, array $vars = array()) {
        if ($vars) {
            $this->vars = array_merge($this->vars, $vars);
        }

        $output = $this->includes($view, array(), true);

        while ($this->block_stack) {
            $this->endBlock();
        }

        if (!$extend = $this->extend) {
            return $output;
        }

        $this->extend = null;
        return $this->render($extend);
    }

    //////////////////// protected method ////////////////////

    protected function includes($view, array $vars = array(), $return_content = false) {
        $view_file = $this->dir.$view.'.php';

        if (!$file = realpath($view_file)) {
            throw new \RuntimeException('View file '.$view_file.' not exist!');
        }

        if (strpos($file, $this->dir) !== 0) {
            throw new \RuntimeException('Invalid view file '. $file);
        }

        $this->included_views[$view] = true;

        $vars = $vars ? array_merge($this->vars, $vars) : $this->vars;

        $ob_level = ob_get_level();
        ob_start();

        try {
            extract($vars);
            require $file;
        } catch (\Exception $ex) {
            // 外面可能已经调用过ob_start()，所以这里不能全部删除干净
            while (ob_get_level() > $ob_level) {
                ob_end_clean();
            }

            throw $ex;
        }

        $output = ob_get_clean();

        if ($return_content) {
            return $output;
        }

        echo $output;
    }

    protected function includeOnce($view) {
        if (!isset($this->included_views[$view])) {
            $this->includes($view);
        }
    }

    protected function beginBlock($name, $method = null) {
        $this->block_stack[] = array($name, $method ?: self::BLOCK_REPLACE);
        ob_start();
    }

    protected function endBlock() {
        if (!$this->block_stack) {
            return;
        }

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
        if (isset($this->block_content[$name])) {
            echo $this->block_content[$name];
            unset($this->block_content[$name]);
        }
    }

    protected function getBlock($name) {
        return isset($this->block_content[$name])
             ? $this->block_content[$name]
             : '';
    }

    protected function extend($view) {
        $this->extend = $view;
    }

    protected function showElement($tag, array $properties) {
        echo $this->element($tag, $attributes);
    }

    protected function element($tag, array $properties) {
        $self_close = array(
            'input' => true,
            'link' => true,
            'meta' => true,
        );

        $props = array();
        foreach ($properties as $key => $value) {
            $props[] = $key.'="'.$value.'"';
        }
        $props = $props ? ' '.implode(' ', $props) : '';

        return isset($self_close[$tag])
             ? sprintf('<%s%s/>', $tag, $props)
             : sprintf('<%s%s></%s>', $tag, $props, $tag);
    }

    /**
     * @deprecated
     */
    protected function block($name, $method = null) {
        return $this->beginBlock($name, $method);
    }
}
