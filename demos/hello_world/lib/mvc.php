<?php
class Controller {
    static public function view() {
        return new \Lysine\MVC\View( ROOT_DIR .'/view' );
    }

    protected function render($view, array $vars = array()) {
        return static::view()->render($view, $vars);
    }
}
