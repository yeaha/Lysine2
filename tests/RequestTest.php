<?php
namespace Test;

use Test\Mock\Sandbox;

class RequestTest extends \PHPUnit_Framework_TestCase {
    public function testAccept() {
        $sandbox = Sandbox::getInstance();

        $sandbox->request('/', 'GET');
        $sandbox->setHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
        $sandbox->setHeader('Accept-Encoding', 'gzip,deflate,sdch');
        $sandbox->setHeader('Accept-Language', 'en-US,en;q=0.8');
        $sandbox->setHeader('Accept-Charset', 'UTF-8,*;q=0.5');

        $this->assertEquals(req()->getAcceptTypes(), array('text/html','application/xhtml+xml','application/xml','*/*'));
        $this->assertEquals(req()->getAcceptEncoding(), array('gzip','deflate','sdch'));
        $this->assertEquals(req()->getAcceptLanguage(), array('en-us', 'en'));
        $this->assertEquals(req()->getAcceptCharset(), array('utf-8', '*'));

        $this->assertTrue(req()->isAcceptType('text/html'));
        $this->assertFalse(req()->isAcceptType('application/json'));

        $this->assertTrue(req()->isAcceptEncoding('gzip'));

        $this->assertTrue(req()->isAcceptLanguage('en-us'));
        $this->assertFalse(req()->isAcceptLanguage('zh'));

        $this->assertTrue(req()->isAcceptCharset('utf-8'));
    }

    protected function setUp() {
        Sandbox::getInstance()->reset();
    }

    protected function tearDown() {
        Sandbox::getInstance()->reset();
    }
}
