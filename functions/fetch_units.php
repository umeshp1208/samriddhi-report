<?php

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db_config.php';

$f_state = isset($_POST['f_state']) ? $_POST['f_state'] : 'all';

if (!$conn) {
    echo json_encode([]);
    exit();
}

if ($f_state == 'all') {
    $sql = "SELECT `unit_code`, `unit_name` FROM `samriddhi`.`unitmaster` ORDER BY `unit_name` ASC";
} else {
    $f_state = implode(',', $f_state);
    $sql = "SELECT `unit_code`, `unit_name` FROM `samriddhi`.`unitmaster` WHERE `state_id_id` IN ($f_state) ORDER BY `unit_name` ASC";
}

if ($result = $conn->query($sql)) {
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    echo json_encode($units);
} else {
    echo json_encode([]);
}

$conn->close();
exit();