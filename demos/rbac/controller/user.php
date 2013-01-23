<?php
namespace Controller;

class User extends \Controller {
    public function GET() {
        return $this->render('user');
    }
}
