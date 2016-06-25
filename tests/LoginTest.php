<?php

include_once 'src/Client.php';

class LoginTest extends PHPUnit_Framework_TestCase {


    public function testLoginSuccessful() {

        $abc = new Alignak_Backend_Client('http://127.0.0.1:5000/');
        $abc->login('admin', 'admin');

        $this->assertFalse($abc->token === null);
    }

}
