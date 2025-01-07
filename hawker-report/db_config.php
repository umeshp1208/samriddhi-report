<?php

$host = 'cir-db-prd.dbcorp.in';
$port = '3306';
$username = 'routetaxi';
$password = 'u{F%*#p?B[F?*GQ@';
$dbname = 'circulation';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
