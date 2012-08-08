<?php
namespace Controller;

class Index extends \Controller {
    public function GET() {
        return $this->render('index', array('output' => 'Hello world!'));
    }
}
