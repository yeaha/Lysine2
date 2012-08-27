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

    protected function assertController($except, $uri, $router) {
        list($controller,) = $router->dispatch($uri);

        $this->assertEquals($except, $controller);
    }

    public function testNamespace() {
        $this->router->setNamespace(array(
            '/admin' => '\Admin\Controller',
            '/blog/' => '\Blog\Controller',
            '/' => '\Action',
        ));

        $this->assertController('\Blog\Controller\Index', '/blog', $this->router);
        $this->assertcontroller('\Action\Blogs', '/blogs', $this->router);
        $this->assertcontroller('\Admin\Controller\Index', '/admin', $this->router);
        $this->assertcontroller('\Admin\Controller\User', '/admin/user', $this->router);
        $this->assertcontroller('\Admin\Controller\User', '/admin/user/', $this->router);
        $this->assertcontroller('\Admin\Controller\User', '/aDmin/User/', $this->router);
        $this->assertcontroller('\Action\Index', '/', $this->router);
    }

    public function testBaseUri() {
        $this->router->setBaseUri('/admin');

        $this->assertController('\Controller\Index', '/admin', $this->router);
        $this->assertController('\Controller\Test', '/admin/test', $this->router);
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
            '#^/news/(\d{4}\-\d{1,2}\-\d{1,2})/(\d+)/comment#' => '\Controller\News\Comment',
            '#^/news/(\d{4}\-\d{1,2}\-\d{1,2})/(\d+)#' => '\Controller\News',
        ));

        list($class, $params) = $this->router->dispatch('/topic/123');
        $this->assertEquals('\Controller\Topic', $class);
        $this->assertContains(123, $params);

        list($class, $params) = $this->router->dispatch('/news/2012-08-23/123');
        $this->assertSame(array('2012-08-23', '123'), $params);

        list($class, $params) = $this->router->dispatch('/news/2012-08-23/123/comment');
        $this->assertEquals('\Controller\News\Comment', $class);
        $this->assertSame(array('2012-08-23', '123'), $params);

        $this->assertController('\Controller\Topic\Comment', '/topic/comment', $this->router);
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
