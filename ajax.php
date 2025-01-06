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
$filterState = isset($_GET['f_state']) ? $_GET['f_state'] : 'all';
$filterUnit = isset($_GET['f_unit']) ? $_GET['f_unit'] : 'BHOP';
$currentDate = isset($_GET['f_date']) ? $_GET['f_date'] : date('Y-m-d');
$baseCopyDate = isset($_GET['f_base_date']) ? $_GET['f_base_date'] : date('Y-m-d');
$growth = isset($_GET['f_growth']) ? $_GET['f_growth'] : 'positive';
$channel = isset($_GET['f_channel']) ? $_GET['f_channel'] : 'DA';

if (is_array($filterState)) {
    if (in_array('all', $filterState)) {
        $filterState = 'all';
    } else {
        $filterState = implode(',', $filterState);
    }
} else {
    $filterState = $filterState;
}

// Optimized SQL Query to fetch both basic data and total record count
$queryBaseData = "
SELECT
    base.sold_to_party AS sap_code,
    MAX(base.agency_name) AS agency_name,
    MAX(zvt_p_cir.vkorg) AS unit_code,
    MAX(zvt_p_reg.city1) AS place,
    MAX(agencystate.state_name) AS state,
    MAX(base.total_base_copy) AS total_base_nps
FROM (
    SELECT
        sold_to_party,
        agency_name,
        SUM(base_copy) AS total_base_copy
    FROM daily_po_base_copie
    WHERE base_copie_date = '$baseCopyDate'
    GROUP BY sold_to_party, agency_name
) AS base
LEFT JOIN zvt_portal_cir AS zvt_p_cir
    ON base.sold_to_party = zvt_p_cir.sold_to_party
LEFT JOIN unitmaster AS unit
    ON zvt_p_cir.vkorg = unit.unit_code
LEFT JOIN agencystatemaster AS agencystate
    ON unit.state_id_id = agencystate.state_id
LEFT JOIN zvt_portal_reg AS zvt_p_reg
    ON zvt_p_cir.sold_to_party = zvt_p_reg.partner
WHERE 1 = 1 AND zvt_p_cir.vtweg = '$channel'";

// Adding filters dynamically
if ($filterState !== 'all') {
    $queryBaseData .= " AND agencystate.state_id IN ($filterState)";
}

if (!empty($filterUnit)) {
    $queryBaseData .= " AND unit.unit_code = '$filterUnit'";
}

// Finalizing the query
$queryBaseData .= " GROUP BY base.sold_to_party";
// $queryBaseData .= ' LIMIT ?, ?';


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

$placeholders = implode(',', array_fill(0, count($records), '?'));

// Get all barrier slab data in one query
$queryBarrier = "
SELECT slab_from, slab_to, barrier_first, barrier_second
FROM emm_agent_barrier_slab
WHERE ? BETWEEN slab_from AND slab_to;
";
$stmtBarrier = $conn->prepare($queryBarrier);

foreach ($records as $record) {
    // Get the barrier data
    $stmtBarrier->bind_param('i', $record['total_base_nps']);
    $stmtBarrier->execute();
    $barrierData = $stmtBarrier->get_result()->fetch_assoc();

    $firstBarrierPercent = $barrierData['barrier_first'] ?? 0;
    $secondBarrierPercent = $barrierData['barrier_second'] ?? 0;

    $soldToParty = $record['sap_code'];
    $ordDateF = $currentDate;

    $queryTodayNps = "
        SELECT SUM(`paid_copy`) as paid_copy FROM `samriddhi`.`zvt_portal_cir`
        WHERE `sold_to_party` = '$soldToParty' AND `ord_date_f` = '$ordDateF'
        GROUP BY `sold_to_party` LIMIT 1;
    ";

    $stmtTodayNps = $conn->prepare($queryTodayNps);
    $stmtTodayNps->execute();
    $resultTodayNps = $stmtTodayNps->get_result();
    $todayNps = $resultTodayNps->fetch_assoc();
    $todayNps = $todayNps['paid_copy'] ?? 0;

    $growthDelta = $todayNps - $record['total_base_nps'];
    $growthPercentage = ($record['total_base_nps'] == 0) ? 0 : ($growthDelta / $record['total_base_nps']) * 100;
    $excessFirstBarrier = number_format($growthPercentage - $firstBarrierPercent, 2);
    $excessSecondBarrier = number_format($growthPercentage - $secondBarrierPercent, 2);

    // Check if the record needs to be added
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
        case 'all':
            $needToAdd = true;
            break;
        default:
            $needToAdd = false;
            break;
    }

    if (!$needToAdd) {
        continue;
    }

    $recordsData[] = [
        'state' => $record['state'],
        'unit' => $record['unit_code'],
        'sap_code' => $record['sap_code'],
        'agency_name' => $record['agency_name'],
        'place' => $record['place'],
        'base_nps' => $record['total_base_nps'],
        'today_nps' => $todayNps,
        'growth_plus_minus' => $growthDelta,
        'growth_percentage' => number_format($growthPercentage, 2) . '%',
        'first_barrier_percent' => $firstBarrierPercent . '%',
        'excess_by_first_barrier' => $excessFirstBarrier . '%',
        'second_barrier_percent' => $secondBarrierPercent . '%',
        'excess_by_second_barrier' => $excessSecondBarrier . '%',
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
$stmtBarrier->close();
$conn->close();

function logMessage($message)
{
    global $logFile;
    $log = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
    file_put_contents($logFile, $log, FILE_APPEND);
    return true;
}
