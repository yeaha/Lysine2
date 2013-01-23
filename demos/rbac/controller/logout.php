<?php
namespace Controller;

class Logout {
    public function GET() {
        \Model\User::logout();
        return resp()->redirect('/');
    }
}
