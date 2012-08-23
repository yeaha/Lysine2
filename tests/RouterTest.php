<?php
namespace Test;

class RouterTest extends \PHPUnit_Framework_TestCase {
    protected $router;

    protected function setUp() {
        $config = array(
            'namespace' => array(
                '/' => '\Controller',
            ),
        );

        $this->router = new \Test\Mock\Router($config);
    }

    public function testNamespace() {
        $this->router->setNamespace(array(
            '/admin' => '\Admin\Controller',
            '/blog/' => '\Blog\Controller',
            '/' => '\Action',
        ));

        list($class,) = $this->router->dispatch('/blog');
        $this->assertEquals('\Blog\Controller\Index', $class);

        list($class,) = $this->router->dispatch('/blogs');
        $this->assertEquals('\Action\Blogs', $class);

        list($class,) = $this->router->dispatch('/admin');
        $this->assertEquals('\Admin\Controller\Index', $class);

        list($class,) = $this->router->dispatch('/admin/user');
        $this->assertEquals('\Admin\Controller\User', $class);

        list($class,) = $this->router->dispatch('/admin/user/');
        $this->assertEquals('\Admin\Controller\User', $class);

        list($class,) = $this->router->dispatch('/aDmin/User');
        $this->assertEquals('\Admin\Controller\User', $class);

        list($class,) = $this->router->dispatch('/');
        $this->assertEquals('\Action\Index', $class);
    }

    public function testBaseUri() {
        $this->router->setBaseUri('/admin');

        list($class,) = $this->router->dispatch('/admin/');
        $this->assertEquals('\Controller\Index', $class);

        list($class,) = $this->router->dispatch('/admin/test');
        $this->assertEquals('\Controller\Test', $class);
    }

    /**
     * @expectedException \Lysine\HTTP\Error
     * @expectedExceptionCode 404
     */
    public function testBaseUriException() {
        $this->router->setBaseUri('/admin');
        $this->router->dispatch('/admina');
    }

    public function testRewrite() {
        $this->router->setRewrite(array(
            '#^/topic/(\d+)#' => '\Controller\Topic',
        ));

        list($class, $params) = $this->router->dispatch('/topic/123');
        $this->assertEquals('\Controller\Topic', $class);
        $this->assertContains(123, $params);

        list($class, $params) = $this->router->dispatch('/topic/abc');
        $this->assertEquals('\Controller\Topic\Abc', $class);
    }

    /**
     * @expectedException \Lysine\HTTP\Error
     * @expectedExceptionCode 405
     */
    public function testMethodNotAllowedException() {
        $this->router->setRewrite(array(
            '#^/#' => '\Test\Fixture\Controller',
        ));

        $this->router->execute('/', 'POST');
    }

    public function testExecute() {
        $this->router->setRewrite(array(
            '#^/#' => '\Test\Fixture\Controller',
        ));

        $response = $this->router->execute('/', 'GET');
        $this->assertEquals('GET', $response);
    }
}
