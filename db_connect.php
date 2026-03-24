<?php
declare(strict_types=1);

$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "ccs_sit_in";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit("Database connection failed. Please check your database settings.");
}
