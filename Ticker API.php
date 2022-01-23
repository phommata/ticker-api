<?php
// db config
const HOST = '';
const USER = '';
const PASSWORD = '';
const DB = '';

// required
const DATE_FROM = 'date_from';
const DATE_TO = 'date_to';
const TICKERS = 'tickers';

// optional
const GROUP_WEEKLY = 'group_weekly';

const TICKER = 'ticker';
const WEEK = 'week';
const DAY_NAME = 'dayname';

$servername = HOST;
$username = USER;
$password = PASSWORD;
$dbname = DB;

$required = [DATE_FROM, DATE_TO, TICKERS];
$dates = [DATE_FROM, DATE_TO];
$err = [];
$dateValid = true;
$hashMap = [];

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    return;
}

foreach ($required as $reqArg) {
    if (empty($_GET[$reqArg])) {
        $err['error'][] = 'missing required: ' . $reqArg;
    }
}

if (!empty($err)) {
    header('Content-type: application/json', true, 400);
    echo json_encode($err);
    return;
}

foreach ($dates as $date) {
    if (!DateTime::createFromFormat('Y-m-d', $_GET[$date])) {
        $err['error'][] = 'invalid date: ' . $date;
        $dateValid = false;
    }
}

if ($dateValid) {
    $dateFrom = $_GET[DATE_FROM]; // = '2017-02-03';
    $dateTo = $_GET[DATE_TO]; // = '2017-04-03';

    if ($dateFrom >= $dateTo) {
        $err['error'][] = 'invalid date range from ' . $dateFrom . ' to ' .  $dateTo;
    }
}

$tickers = explode(',', $_GET[TICKERS]); // = 'WMT,TGT';
$groupWeekly = empty($_GET[GROUP_WEEKLY]) ? false : (bool) $_GET[GROUP_WEEKLY]; // = true

if (!is_bool($groupWeekly)) {
    $err['error'][] = GROUP_WEEKLY . ' invalid bool value: ' . $groupWeekly;
}

if (!empty($err)) {
    header('Content-type: application/json', true, 400);
    echo json_encode($err);
    return;
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $in = join(',', array_fill(0, count($tickers), '?'));

    // prepare sql and bind parameters
    $query = "SELECT *
            FROM companies c
            WHERE c.ticker IN ($in)";

    $stmt = $conn->prepare($query);
    $stmt->execute($tickers);

    $tickersResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tickersResults)) {
        error_log("Could not successfully run query ($query) from DB: " . print_r($stmt->errorInfo(), 1));

        header('Content-type: application/json', true, 404);
        echo json_encode(['error' => 'ticker(s) not found']);
        return;
    }

    foreach ($tickersResults as $tickersResult) {
        $tickersArr[] = $tickersResult[TICKER];
    }

    $params = array_merge($tickersArr, [$dateFrom], [$dateTo]);

    $in = join(',', array_fill(0, count($tickersArr), '?'));

    // prepare sql and bind parameters
    $query = "SELECT dt.name, dt.ticker, h.d, h.high, h.low, h.close, DAYNAME(h.d) dayname, WEEK(h.d) week 
                FROM historical h
                INNER JOIN 
                (
                    SELECT c.company_id, c.name, c.ticker, h.d, DAYNAME(h.d) dayname, WEEK(h.d) week
                    FROM companies c
                    JOIN historical h ON h.company_id = c.company_id
                    WHERE c.ticker IN ($in)
                    AND h.d >= ?
                    AND h.d <= ?
                    GROUP BY company_id, c.name, c.ticker, h.d, dayname, week
                ) dt ON dt.dayname = DAYNAME(h.d) AND h.d = dt.d AND dt.week = WEEK(h.d) AND dt.company_id = h.company_id
                ORDER BY h.d DESC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date_from', $dateFrom);
    $stmt->bindParam(':date_to', $dateTo);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $key = $groupWeekly ? WEEK : DAY_NAME;

    $hashMap[$key] = [];

    foreach ( $result as $row) {
        $rowTmp = $row;
        $week = $rowTmp[$key];

        unset($rowTmp[DAY_NAME]);
        unset($rowTmp[WEEK]);

        $hashMap[$key][$week][] = $rowTmp;
    }

    header('Content-type: application/json', true, 200);
    echo json_encode($hashMap);
    return;
} catch(Exception $e) {
    error_log("Could not successfully run query ($query) from DB: " . print_r($stmt->errorInfo(), 1));

    echo json_encode(["error" => $e->getMessage()]);
    return;
}