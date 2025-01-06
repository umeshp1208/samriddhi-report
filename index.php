<?php

include 'db_config.php';

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$stateQuery = "SELECT `state_id`, `state_name` FROM `samriddhi`.`agencystatemaster` WHERE `state_name` != '' ORDER BY `state_name` ASC";

$stateResult = $conn->query($stateQuery);
$stateData = [];
while ($row = $stateResult->fetch_assoc()) {
    $stateData[$row['state_id']] = $row['state_name'];
}

// Default WHERE state = 'Madhya Pradesh'
$defaultStateId = array_search('Madhya Pradesh', $stateData);

// Units
$unitQuery = 'SELECT `unit_code`, `unit_name` FROM `samriddhi`.`unitmaster`';

if ($defaultStateId) {
    $unitQuery .= " WHERE `state_id_id` = $defaultStateId";
}

$unitQuery .= ' ORDER BY `unit_name` ASC';

if ($unitResult = $conn->query($unitQuery)) {
    $unitData = [];
    while ($row = $unitResult->fetch_assoc()) {
        $unitData[$row['unit_code']] = $row['unit_name'];
    }
}

$selectedState = isset($_POST['state']) ? $_POST['state'] : $defaultStateId;
$selectedUnit = isset($_POST['unit']) ? $_POST['unit'] : 'BHOP';
$selectedDate = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$baseCopyDate = isset($_POST['base_date']) ? $_POST['base_date'] : '2024-12-10';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent's Slab Barriers Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.2.0/css/buttons.bootstrap5.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <style>
        body {
            font-family: Nunito, "sans-serif" !important;
            font-size: 14px;
        }

        .dt-paging {
            float: right;
        }

        .dt-buttons {
            margin-bottom: 15px !important;
        }

        .dt-buttons button {
            background: #28a745 !important;
            border-color: #28a745 !important;
        }

        .card .card-header {
            background-image: linear-gradient(to right, green, yellow);
            color: white;
            font-weight: bold;
            font-size: 18px;
            padding: 15px;
            line-height: 20px;
            letter-spacing: 1px;
            text-align: center;
        }

        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            height: 38px;
            border: 1px solid #dee2e6;
        }

        /* Loader */
        .loader {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loader i {
            font-size: 48px;
            color: #fff;
        }
    </style>
</head>

<body class="bg-light mt-5">

    <!-- Loader -->
    <div class="loader" id="loader">
        <i class="fas fa-spinner fa-spin"></i>
    </div>


    <div class="container">
        <div class="row mb-3">
            <div class="card shadow-lg p-0 border-0">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-12 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label fw-bold" for="state">State</label>
                                <select id="state" name="state[]" class="form-select">
                                    <?php
                                    foreach ($stateData as $stateId => $stateName) {
                                        echo '<option value="' . $stateId . '" ' . ($selectedState == $stateId ? 'selected' : '') . '>' . $stateName . '</option>';
                                    }
                                    ?>
                                    <option value="all">All</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-sm-12 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label fw-bold" for="unit">Unit</label>
                                <select id="unit" name="unit" class="form-select">
                                    <option value="">Select Unit</option>
                                    <?php
                                    foreach ($unitData as $unitCode => $unitName) {
                                        echo '<option value="' . $unitCode . '" ' . ($selectedUnit == $unitCode ? 'selected' : '') . '>' . $unitName . '</option>';
                                    }
                                    ?>
                                    <option value="all">All</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-sm-12 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label fw-bold" for="date">Base Date</label>
                                <input type="text" class="form-control" id="base_date" name="base_date" readonly
                                    value="2024-12-10" placeholder="Select Date">
                            </div>
                        </div>

                        <div class="col-sm-12 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label fw-bold" for="date">Date</label>
                                <input type="text" class="form-control" id="date" name="date" readonly
                                    value="<?php echo $selectedDate; ?>" placeholder="Select Date">
                            </div>
                        </div>

                        <div class="col-sm-12 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label fw-bold" for="growth">Growth</label>
                                <select id="growth" name="growth" class="form-select">
                                    <option value="all">All</option>
                                    <option value="zero">Zero(0)</option>
                                    <option value="positive" selected>Positive(+)</option>
                                    <option value="negative">Negative(-)</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-sm-12 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label fw-bold" for="channel">Channel</label>
                                <select id="channel" name="channel" class="form-select">
                                    <option value="DA" selected>DA</option>
                                    <option value="CA">CA</option>
                                    <option value="IH">IH</option>
                                    <option value="IN">IN</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <button type="submit" class="btn btn-info btn-md" id="applyFilter">
                                Apply Filter(s)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="card shadow-lg p-0 border-0">
                <div class="card-header bg-warning text-center">
                    <h3 class="card-title">Agent's Slab Barriers Report</h3>
                </div>

                <div class="card-body p-3">
                    <table class="table table-striped table-bordered nowrap dt-responsive display" cellspacing="0" width="100%"
                        id="reportTable" title="Agent's Slab Barriers Report">
                        <thead class="bg-secondary text-white">
                            <tr>
                                <th>State</th>
                                <th>Unit</th>
                                <th>Sap Code</th>
                                <th>Name of Agency</th>
                                <th>Place</th>
                                <th>Base NPS (<span id="l_base_date"><?php echo $baseCopyDate; ?></span>)</th>
                                <th>Today NPS (<span id="l_date"><?php echo $selectedDate; ?></span>)</th>
                                <th>Growth (+/-)</th>
                                <th>Growth %</th>
                                <th>1st Barrier %</th>
                                <th>Excess by</th>
                                <th>2nd Barrier %</th>
                                <th>Excess by</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
    <script src="assets/js/datatable.js"></script>

    <script src="https://cdn.datatables.net/buttons/3.2.0/js/dataTables.buttons.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.bootstrap5.js"></script>

    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.3.1/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.3.1/js/buttons.html5.min.js"></script>
    <script>
        $(function() {
            $("#date").datepicker({
                dateFormat: "yy-mm-dd",
                defaultDate: new Date(),
                minDate: '-3M',
                maxDate: '+2D'
            });

            $("#base_date").datepicker({
                dateFormat: "yy-mm-dd",
                defaultDate: '2024-12-10',
            });

            $('#state').select2({
                placeholder: 'Select State',
                width: '100%',
                multiple: true,
            });

            $('#unit').select2({
                placeholder: 'Select Unit',
                width: '100%',
                multiple: false,
            });

            $('#growth').select2({
                placeholder: 'Select Growth',
                width: '100%',
                multiple: false,
            });

            $('#channel').select2({
                placeholder: 'Select Channel',
                width: '100%',
                multiple: false,
            });

            // On state change, fetch units
            $('#state').on('change', function() {
                var states = $(this).val();
                $.ajax({
                    url: 'functions/fetch_units.php',
                    type: 'POST',
                    data: {
                        f_state: states
                    },
                    success: function(units) {
                        if (units) {
                            // Clear existing options
                            $('#unit').empty();

                            units = JSON.parse(units);

                            // Add new options
                            units.forEach(function(unit) {
                                $('#unit').append('<option value="' + unit.unit_code + '">' + unit.unit_name + '</option>');
                            });

                            // Add "all" option
                            $('#unit').append('<option value="all">All</option>');

                            // Trigger change event
                            $('#unit').trigger('change');
                        }
                    }
                });
            });

            $('#applyFilter').on('click', function() {
                var date = $('#date').val();
                var state = $('#state').val();
                var unit = $('#unit').val();
                var baseDate = $('#base_date').val();
                var growth = $('#growth').val();
                var channel = $('#channel').val();

                $('#reportTable').DataTable().ajax.url('ajax.php?f_date=' + date + '&f_state=' + state + '&f_unit=' + unit + '&f_base_date=' + baseDate + '&f_growth=' + growth + '&f_channel=' + channel).load();
                $('#l_date').text(date);
                $('#l_base_date').text(baseDate);
            });
        });
    </script>
</body>


</html>