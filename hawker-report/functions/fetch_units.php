<?php

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db_config.php';

$f_state = isset($_POST['f_state']) ? $_POST['f_state'] : 'all';

if (is_array($f_state) && !empty($f_state) && in_array('all', $f_state)) {
    $f_state = 'all';
}

if (!$conn) {
    echo json_encode([]);
    exit();
}

if ($f_state == 'all') {
    $sql = "SELECT DISTINCT
        dm.unit_id,
        um.unit_full_name
    FROM
        depot_master dm
        LEFT JOIN unit_master um ON dm.unit_id = um.unit_id;
    ";
} else {
    $f_state = implode(',', $f_state);
    $sql = "
    SELECT DISTINCT
        dm.unit_id,
        um.unit_full_name
    FROM
        depot_master dm
        LEFT JOIN state_master sm ON dm.state_id = sm.state_id
        LEFT JOIN unit_master um ON dm.unit_id = um.unit_id;
    WHERE
        dm.state_id IN ($f_state);
    ";
}

if ($result = $conn->query($sql)) {
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = [
            'unit_id' => $row['unit_id'],
            'unit_name' => $row['unit_full_name'],
        ];
    }

    echo json_encode($units);
} else {
    echo json_encode([]);
}

$conn->close();
exit();