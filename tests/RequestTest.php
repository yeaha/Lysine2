<?php
namespace Test;

use \Test\Mock\Environment as ENV;

class RequestTest extends \PHPUnit_Framework_TestCase {
    protected function tearDown() {
        ENV::reset();
    }

    public function testAccept() {
        ENV::begin('/', 'GET');
        ENV::setHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
        ENV::setHeader('Accept-Encoding', 'gzip,deflate,sdch');
        ENV::setHeader('Accept-Language', 'en-US,en;q=0.8');
        ENV::setHeader('Accept-Charset', 'UTF-8,*;q=0.5');

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
}
