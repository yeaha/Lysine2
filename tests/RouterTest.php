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

    protected function assertController($uri, $expect, $router = null) {
        list($controller,) = $this->dispatch($uri, $router);

        $this->assertEquals($expect, $controller);
    }

    protected function dispatch($uri, $router = null) {
        $router = $router ?: $this->router;

        try {
            return $router->dispatch($uri);
        } catch (\Lysine\HTTP\Error $error) {
            if (!$controller = $error->getMore('controller'))
                throw $error;

            $params = $error->getMore('params') ?: array();
            $path = $error->getMore('path') ?: $uri;

            return array($controller, $params, $path);
        }
    }

    public function testNamespace() {
        $this->router->setNamespace(array(
            '/admin' => '\Admin\Controller',
            '/blog/' => '\Blog\Controller',
            '/' => '\Action',
        ));

        $test = array(
            '/blog' => '\Blog\Controller\Index',
            '/blogs' => '\Action\Blogs',
            '/admin' => '\Admin\Controller\Index',
            '/admin/user' => '\Admin\Controller\User',
            '/admin/user/' => '\Admin\Controller\User',
            '/admin/User/' => '\Admin\Controller\User',
            '/' => '\Action\Index',
        );

        foreach ($test as $uri => $expect)
            $this->assertController($uri, $expect);
    }

    public function testBaseUri() {
        $this->router->setBaseUri('/admin');

        $this->assertController('/admin', '\Controller\Index');
        $this->assertController('/admin/test', '\Controller\Test');
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

        list($class, $params) = $this->dispatch('/topic/123');
        $this->assertEquals('\Controller\Topic', $class);
        $this->assertContains(123, $params);

        list($class, $params) = $this->dispatch('/news/2012-08-23/123');
        $this->assertSame(array('2012-08-23', '123'), $params);

        list($class, $params) = $this->dispatch('/news/2012-08-23/123/comment');
        $this->assertEquals('\Controller\News\Comment', $class);
        $this->assertSame(array('2012-08-23', '123'), $params);

        $this->assertController('/topic/comment', '\Controller\Topic\Comment');
    }

    public function testExtension() {
        list($class,) = $this->dispatch('/topic');
        $this->assertEquals('\Controller\Topic', $class);

        list($class,) = $this->dispatch('/topic.json');
        $this->assertEquals('\Controller\Topic', $class);

        $this->router->setRewrite(array(
            '#^/topic.json#' => '\Controller\JsonTopic',
            '#^/topic#' => '\Controller\Topic',
        ));

        list($class,) = $this->dispatch('/topic.json');
        $this->assertEquals('\Controller\JsonTopic', $class);
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
