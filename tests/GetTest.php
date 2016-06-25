<?php

include('src/Client.php');

class GetTest extends PHPUnit_Framework_TestCase {


    public function testGetTimeperiods() {

        $abc = new Alignak_Backend_Client('http://127.0.0.1:5000/');
        $abc->login('admin', 'admin');
        $timeperiods = $abc->get('timeperiod');

        $this->assertEquals(4, count($timeperiods));
        $this->assertArrayHasKey('_items', $timeperiods);
        $this->assertArrayHasKey('_links', $timeperiods);
        $this->assertArrayHasKey('_meta', $timeperiods);
        $this->assertEquals("OK", $timeperiods['_status']);

        $this->assertEquals("24x7", $timeperiods['_items'][0]['name']);
    }

    public function testGetAll() {
        $abc = new Alignak_Backend_Client('http://127.0.0.1:5000/', 8);
        $abc->login('admin', 'admin');

        $realms = $abc->get('realm');
        $realm_id = $realms['_items'][0]['_id'];

        // Add commands
        $data = array('command_line' => 'check_ping', '_realm' => $realm_id);
        for ($i=1; $i <= 2000; $i++) {
            $data['name'] = "cmd".$i;
            $abc->post('command', $data);
        }

        $commands = $abc->get_all('command');

        $this->assertEquals(4, count($commands));
        $this->assertEquals(2000, count($commands['_items']));


    }
}
