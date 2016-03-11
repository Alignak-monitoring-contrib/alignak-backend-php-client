<?php

include('../src/Client.php');

$abc = new Alignak_Backend_Client('http://127.0.0.1:5000/');

$abc->login('admin', 'admin');

$resp = $abc->get('contact');

print_r($resp);

