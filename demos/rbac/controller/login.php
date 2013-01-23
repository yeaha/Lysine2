<?php
namespace Controller;

class Login extends \Controller {
    public function GET() {
        return $this->render('login');
    }

    public function POST() {
        if (!$user = \Model\User::login(post('email'), post('passwd'), post('remember')))
            return $this->render('login', array('message' => '错误的用户名或密码'));

        $url = get('ref') ?: '/';
        return resp()->redirect($url);
    }
}
