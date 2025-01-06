<?php

$host = '34.100.186.93';
$port = '3306';
$username = 'samriddhi';
$password = 'v^6TKH""Q#y7Ng<@';
$dbname = 'samriddhi';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
