<?php

// Quick Inclusion of inline database elements - no escaping necessary
include ('.db_creds.php');

// Create connection
try {
    $conn = new PDO('mysql:host=' . $data['server'] . ';dbname=' . $data['database'] . ';charset=utf8', $data['username'], $data['password']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Connection failed: ' . $e->getMessage());
}

$orderCommentsProcessed = [];

// Records about Candy
$recordsCandy = findTheRecords($conn, $orderCommentsProcessed, 'candy');
echo '<h2>Records about Candy (' . count($recordsCandy) . ')</h2>';
presentTheRecords($recordsCandy);

// Records about Calling
$recordsCall = findTheRecords($conn, $orderCommentsProcessed, 'call');
echo '<h2>Records about Calling / Not Calling (' . count($recordsCall) . ')</h2>';
presentTheRecords($recordsCall);

// Records about Refer (not reefer)
$recordsRefer = findTheRecords($conn, $orderCommentsProcessed, 'refer', 2);
echo '<h2>Records about Refer(als) (' . count($recordsRefer) . ')</h2>';
presentTheRecords($recordsRefer);

// Records about Signature
$recordsSignature = findTheRecords($conn, $orderCommentsProcessed, 'signature');
echo '<h2>Records about Signature Delivery (' . count($recordsSignature) . ')</h2>';
presentTheRecords($recordsSignature);

// Records Regarding All Else
$recordsRemainder = findTheRecords($conn, $orderCommentsProcessed, null, 0, true);
echo '<h2>All Other Records (' . count($recordsRemainder) . ')</h2>';
presentTheRecords($recordsRemainder);

// Reset Counter
$orderCommentsProcessed = [];

// Gather Records that include an Expected Ship Date
$recordsExpected = findTheRecords($conn, $orderCommentsProcessed, 'Expected Ship Date');
echo '<h2>Updating Orders with Expected Ship Date (' . count($recordsExpected) . ')</h2>';

// Update the Shipdates
updateExpectedShipDate($conn, $recordsExpected);

function findTheRecords($conn, &$orderCommentsProcessed, string $textString = null, int $mode = 0, bool $getRemainder = false): array
{
    $records = [];

    $query = $conn->prepare("SELECT
        *
        FROM sweetwater_test
        " . (!empty($textString) ? "WHERE comments LIKE '" . (($mode !== 2) ? '%' : '') . $textString . ( ($mode === 0 || $mode === 2) ? '%' : '') . "'" : '') . "
        ORDER BY orderid DESC");

    try {
        $query->execute();
    } catch (Exception $e) {
        die('Query failed: ' . $e->getMessage());
    }

    while ($record = $query->fetch(PDO::FETCH_ASSOC)) {
        if ($getRemainder && !in_array($record['orderid'], $orderCommentsProcessed) || !$getRemainder) {
            $records[$record['orderid']] = $record;
            $orderCommentsProcessed[] = $record['orderid'];
        }
    }

    return $records;
}

function presentTheRecords($records): void
{
    echo '<table>';
    echo '<thead><tr><th>orderid</th><th>comments</th></thead>';
    echo '<tbody>';
    foreach ($records as $record) {
        echo '<tr>';
        echo '<td>' . $record['orderid'] . '</td>';
        echo '<td>' . $record['comments'] . '</td>';
        // echo '<td>' . $record['shipdate_expected'] . '</td>'; // redundant
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}

function updateExpectedShipDate($conn, $records) : void
{
    foreach ($records as $record) {

        $extractedDate = [];

        // Parse Shipdate
        preg_match("^\\d{1,2}/\\d{2}/\\d{2}^", $record['comments'], $extractedDate);

        if (count($extractedDate)) {
            
            // Get datetime format for sql
            $sqlDate = date("Y-m-d H:i:s", strtotime(reset($extractedDate)));

            // Status update for logging
            echo 'updating ' . $record['orderid'] . ' with ship date ' . $sqlDate . ' ... <br>';

            // Update record            
            $query = $conn->prepare("UPDATE
                sweetwater_test
                SET shipdate_expected = '" . $sqlDate . "'
                WHERE orderid = '" . $record['orderid'] . "'");

            try {
                $query->execute();
            } catch (Exception $e) {
                die('Query failed: ' . $e->getMessage());
            }
        }
    }
}

exit;
