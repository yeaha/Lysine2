<?php
namespace Model;

use Lysine\HTTP;
use Model\User;

class Rbac {
    protected $rules = array();

    public function __construct(array $rules) {
        $this->rules = $rules;
    }

    public function check($class, $path) {
        $rules = $this->rules;
        $token = strtolower(trim($class, '\\'));

        try {
            // 从下向上一层一层检查namespace权限设置
            $pos = null;
            do {
                if ($pos !== null)
                    $token = substr($token, 0, $pos);

                if (isset($rules[$token]) && $this->execute($rules[$token]))
                    return true;

                $pos = strrpos($token, '\\');
            } while ($pos !== false);

            $this->execute($rules['__default__']);
        } catch (HTTP\Error $ex) {
            $ex->class = $class;
            $ex->path = $path;

            throw $ex;
        }
    }

    protected function halt() {
        throw User::current()->hasRole(ROLE_ANONYMOUS)  // login?
            ? HTTP\Error::factory(401)                  // 401
            : HTTP\Error::factory(403);                 // 403
    }

    protected function execute($rule) {
        if (isset($rule['deny'])) {
            if ($rule['deny'] == '*') $this->halt();

            if (array_intersect(
                User::current()->getRoles(),
                preg_split('/\s*,\s*/', $rule['deny'])
            )) $this->halt();
        }

        if (isset($rule['allow'])) {
            if ($rule['allow'] == '*') return true;

            if (array_intersect(
                User::current()->getRoles(),
                preg_split('/\s*,\s*/', $rule['allow'])
            )) return true;

            // 如果设置了allow，但是当前登录用户又没有包括这些角色，就不允许访问
            $this->halt();
        }

        // 返回false会继续检查上一级rule
        return false;
    }
}
