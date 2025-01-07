<?php

// Include your database configuration
include 'db_config.php';

// Get filters from POST request
$filterState = isset($_POST['f_state']) ? $_POST['f_state'] : 'all';
$filterUnit = isset($_POST['f_unit']) ? $_POST['f_unit'] : 'BHOP';
$currentDate = isset($_POST['f_date']) ? $_POST['f_date'] : date('Y-m-d');
$baseCopyDate = isset($_POST['f_base_date']) ? $_POST['f_base_date'] : date('Y-m-d');

if (is_array($filterState)) {
    if (in_array('all', $filterState)) {
        $filterState = 'all';
    } else {
        $filterState = implode(',', $filterState);
    }
} else {
    $filterState = $filterState;
}

// Query to fetch data based on filters
$queryBaseData = "
SELECT
    MAX(base.agency_name) AS agency_name,
    MAX(zvt_p_cir.vkorg) AS unit_code,
    MAX(zvt_p_cir.city_name) AS place,
    MAX(agencystate.state_name) AS state,
    base.sold_to_party AS sap_code,
    MAX(base.base_copy) AS base_nps,
    base.sold_to_party,
    base.ship_to_party
FROM (
    SELECT
        sold_to_party,
        ship_to_party,
        agency_name,
        base_copy,
        base_copie_date
    FROM daily_po_base_copie
    WHERE base_copie_date = '$baseCopyDate'
) AS base
LEFT JOIN zvt_portal_cir AS zvt_p_cir
    ON base.sold_to_party = zvt_p_cir.sold_to_party AND base.ship_to_party = zvt_p_cir.ship_to_party
LEFT JOIN unitmaster AS unit
    ON zvt_p_cir.vkorg = unit.unit_code
LEFT JOIN agencystatemaster AS agencystate
    ON unit.state_id_id = agencystate.state_id
WHERE base.base_copie_date = '$baseCopyDate'";

if ($filterState !== 'all') {
    $queryBaseData .= " AND agencystate.state_id IN ($filterState)";
}

if (!empty($filterUnit)) {
    $queryBaseData .= " AND unit.unit_code = '$filterUnit'";
}

$queryBaseData .= " GROUP BY base.sold_to_party, base.ship_to_party LIMIT 500";

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

// Set headers for Excel export
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="exported_data.xlsx"');

// Start output buffer for Excel content
echo "State\tUnit\tSAP Code\tAgency Name\tPlace\tBase NPS\tToday NPS\tGrowth Plus Minus\tGrowth Percentage\tFirst Barrier Percent\tExcess By First Barrier\tSecond Barrier Percent\tExcess By Second Barrier\n";

// Process and output each record
foreach ($records as $record) {
    // Query for today's NPS
    $queryTodayNps = "
        SELECT * FROM `samriddhi`.`zvt_portal_cir`
        WHERE `sold_to_party` = ? AND `ord_date_f` = ? AND `ship_to_party` = ?
        ORDER BY `zvt_portal_cir_id` LIMIT 1;
    ";

    $stmtTodayNps = $conn->prepare($queryTodayNps);
    if ($stmtTodayNps === false) {
        echo 'SQL error: ' . $conn->error;
        exit();
    }

    $stmtTodayNps->bind_param('sss', $record['sold_to_party'], $currentDate, $record['ship_to_party']);
    $stmtTodayNps->execute();
    $resultTodayNps = $stmtTodayNps->get_result()->fetch_assoc();

    $todayNps = $resultTodayNps['paid_copy'];
    $growthDelta = $todayNps - $record['base_nps'];
    $growthPercentage = ($record['base_nps'] == 0) ? 0 : ($growthDelta / $record['base_nps']) * 100;

    // Barrier calculation
    $queryBarrier = "
    SELECT *
    FROM emm_agent_barrier_slab
    WHERE ? BETWEEN slab_from AND slab_to;
    ";

    $stmtBarrier = $conn->prepare($queryBarrier);
    if ($stmtBarrier === false) {
        echo 'SQL error: ' . $conn->error;
        exit();
    }

    $stmtBarrier->bind_param('i', $record['base_nps']);
    $stmtBarrier->execute();
    $barrierData = $stmtBarrier->get_result()->fetch_assoc();

    $firstBarrierPercent = $barrierData['barrier_first'] ?? 0;
    $secondBarrierPercent = $barrierData['barrier_second'] ?? 0;
    $excessFirstBarrier = number_format($growthPercentage - $firstBarrierPercent, 2);
    $excessSecondBarrier = number_format($growthPercentage - $secondBarrierPercent, 2);

    // Output row data for each record
    echo implode("\t", [
        $record['state'],
        $record['unit_code'],
        $record['sap_code'],
        $record['agency_name'],
        $record['place'],
        $record['base_nps'],
        $todayNps,
        $growthDelta,
        number_format($growthPercentage, 2) . '%',
        $firstBarrierPercent . '%',
        $excessFirstBarrier . '%',
        $secondBarrierPercent . '%',
        $excessSecondBarrier . '%',
    ]) . "\n";
}

$stmtBaseData->close();
$conn->close();
exit();
?>
