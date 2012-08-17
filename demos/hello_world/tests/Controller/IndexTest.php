<?php
namespace Tests\Controller;

class IndexTest extends \Test\Controller {
    /**
     * @group controller
     */
    public function testGet() {
        $response = $this->GET('/');

        $this->assertEquals($response->getCode(), 200);
    }

    /**
     * @group controller
     * @expectedException \Lysine\HTTP\Error
     * @expectedExceptionCode 405
     */
    public function testPost() {
        $this->POST('/', array('a' => 'b'));
    }
}
