<?php

// Configuration and static settings
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include 'db_config.php';

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Static variables
$logDirectory = __DIR__ . '/log';
$logFile = $logDirectory . '/log.txt';

// Filters
$filterState = isset($_GET['f_state']) && !empty($_GET['f_state']) ? $_GET['f_state'] : 'all';
$filterUnit = isset($_GET['f_unit']) ? $_GET['f_unit'] : 'all';
$currentDate = isset($_GET['f_date']) ? $_GET['f_date'] : date('Y-m-d');
$growth = isset($_GET['f_growth']) ? $_GET['f_growth'] : 'positive';

if (is_array($filterState)) {
    if (in_array('all', $filterState)) {
        $filterState = 'all';
    } else {
        $filterState = implode(',', $filterState);
    }
} else {
    $filterState = $filterState;
}

// SET sql_mode='';

$setMode = "SET sql_mode='';";

// Run set mode
$conn->query($setMode);

// Optimized SQL Query to fetch both basic data and total record count
$queryBaseData = "
SELECT
  state.`state_name`,
  unit.`unit_name`,
  depot.`depot_name`,
  ds.`hawker_code`,
  ds.`edtn_code`,
  hm.`hawker_name`,
  ROUND(AVG(ds.`supl_copies`), 0) AS `seven_day_avg`,
  (
    SELECT
      `barrier_1_percent`
    FROM
      `circulation`.`hawker_slab`
    WHERE
      ROUND(AVG(ds.`supl_copies`), 0) BETWEEN `hawker_slab`.`from_slab`
      AND `hawker_slab`.`to_slab`
  ) AS `barrier1`,
  (
    SELECT
      `barrier_2_percent`
    FROM
      `circulation`.`hawker_slab`
    WHERE
      ROUND(AVG(ds.`supl_copies`), 0) BETWEEN `hawker_slab`.`from_slab`
      AND `hawker_slab`.`to_slab`
  ) AS `barrier2`,
  (
    SELECT
      SUM(b.`supl_copies`)
    FROM
      `circulation`.`datewisesupply` b
    WHERE
      b.`supplydate` = '$currentDate'
      AND b.`hawker_id` = ds.`hawker_id`
      AND b.`edtn_code` = ds.`edtn_code`
      AND b.`status` = '1'
    GROUP BY
      b.`hawker_id`,
      b.`edtn_code`
  ) AS `today_copy`
FROM
  (
    SELECT
      `supplydate`,
      `hawker_code`,
      `edtn_code`,
      `state_id`,
      `unit_id`,
      `depot_id`,
      `hawker_id`,
      SUM(`supl_copies`) AS `supl_copies`
    FROM
      `circulation`.`datewisesupply`
    WHERE
      `status` = '1'
    GROUP BY
      `supplydate`,
      `hawker_code`,
      `edtn_code`
  ) ds
  LEFT JOIN unit_master AS unit ON ds.unit_id = unit.unit_id
  LEFT JOIN state_master AS state ON ds.state_id = state.state_id
  LEFT JOIN depot_master AS depot ON ds.depot_id = depot.depot_id
  LEFT JOIN hawker_master AS hm ON ds.hawker_id = hm.hawker_id
WHERE
  ds.`supplydate` BETWEEN '2024-12-04' AND '2024-12-10'
";

if ($filterState !== 'all') {
    $queryBaseData .= " AND state.state_id IN ($filterState)";
}

if ($filterUnit !== 'all') {
    $queryBaseData .= " AND unit.unit_id = $filterUnit";
}

// Finalizing the query
$queryBaseData .= " GROUP BY
  ds.`hawker_code`,
  ds.`edtn_code`;
";

logMessage($queryBaseData);

// Prepare the statement
$stmtBaseData = $conn->prepare($queryBaseData);
if ($stmtBaseData === false) {
    echo 'SQL error: ' . $conn->error;
    exit();
}

$stmtBaseData->execute();
$resultBaseData = $stmtBaseData->get_result();

// Fetch data
$records = [];
while ($record = $resultBaseData->fetch_assoc()) {
    $records[] = $record;
}

// Process data and calculations
$recordsData = [];
foreach ($records as $record) {
    $firstBarrierPercent = $record['barrier1'] ?? 0;
    $secondBarrierPercent = $record['barrier2'] ?? 0;

    $growthDelta = $record['today_copy'] - $record['seven_day_avg'];
    $growthDelta = $growthDelta ?? 0;
    $growthDelta = $growthDelta ? number_format($growthDelta, 2) : 0;
    $record['seven_day_avg'] = $record['seven_day_avg'] ?? 0;
    $growthDelta = floatval($growthDelta);
    $record['seven_day_avg'] = floatval($record['seven_day_avg']);

    $growthPercentage = $record['seven_day_avg'] == 0 ? 0 : ($growthDelta / $record['seven_day_avg']) * 100;
    $growthPercentage = number_format($growthPercentage, 2);
    $growthPercentage = floatval($growthPercentage);

    $excessFirstBarrier = number_format($growthPercentage - $firstBarrierPercent, 2);
    $excessSecondBarrier = number_format($growthPercentage - $secondBarrierPercent, 2);

    $needToAdd = false;
    switch ($growth) {
        case 'positive':
            if ($growthDelta > 0) {
                $needToAdd = true;
            }
            break;
        case 'negative':
            if ($growthDelta < 0) {
                $needToAdd = true;
            }
            break;
        case 'zero':
            if ($growthDelta == 0) {
                $needToAdd = true;
            }
            break;
        default:
            $needToAdd = false;
            break;
    }

    if (!$needToAdd) {
        continue;
    }

    $recordsData[] = [
        'state' => $record['state_name'] ?? '',
        'unit' => $record['unit_name'] ?? '',
        'sap_code' => $record['hawker_code'] ?? '',
        'vendor_name' => $record['hawker_name'] ?? '',
        'depot_name' => $record['depot_name'] ?? '',
        'base_nps' => $record['seven_day_avg'] ?? '',
        'today_nps' => $record['today_copy'] ?? '',
        'growth_plus_minus' => $growthDelta,
        'growth_percentage' => $growthPercentage,
        'first_barrier_percent' => $firstBarrierPercent,
        'excess_by_first_barrier' => $excessFirstBarrier,
        'second_barrier_percent' => $secondBarrierPercent,
        'excess_by_second_barrier' => $excessSecondBarrier,
    ];
}

$totalRecords = count($recordsData);

// Response for DataTable
$response = [
    'draw' => intval($_GET['draw'] ?? true),
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalRecords,
    'data' => $recordsData,
];

// Output JSON response
echo json_encode($response);

$stmtBaseData->close();
$conn->close();

function logMessage($message)
{
    global $logFile;
    $log = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
    file_put_contents($logFile, $log, FILE_APPEND);
    return true;
}
