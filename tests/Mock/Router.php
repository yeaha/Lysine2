<?php
namespace Test\Mock;

class Router extends \Lysine\MVC\Router {
    public function setNamespace(array $namespace) {
        $this->namespace = $namespace;
    }

    public function setRewrite(array $rules) {
        $this->rewrite = array_merge($this->rewrite, $rules);
    }

    public function setBaseUri($base_uri) {
        $this->base_uri = $base_uri;
    }
}
