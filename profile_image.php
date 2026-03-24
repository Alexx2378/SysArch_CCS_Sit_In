<?php
declare(strict_types=1);

require_once "db_connect.php";

function outputPlaceholder(): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0%" stop-color="#2b124c"/><stop offset="100%" stop-color="#7a4a8a"/>'
        . '</linearGradient></defs>'
        . '<rect width="320" height="320" fill="url(#g)"/>'
        . '<circle cx="160" cy="122" r="56" fill="#ffffff" fill-opacity="0.92"/>'
        . '<path d="M68 284c18-46 55-72 92-72s74 26 92 72" fill="#ffffff" fill-opacity="0.92"/>'
        . '</svg>';

    header("Content-Type: image/svg+xml; charset=UTF-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    echo $svg;
    exit;
}

$idNumber = trim($_GET["id"] ?? "");
if ($idNumber === "") {
    outputPlaceholder();
}

try {
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE idNumber = ? LIMIT 1");
    $stmt->bind_param("s", $idNumber);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        outputPlaceholder();
    }

    if (empty($row["profile_image"])) {
        outputPlaceholder();
    }

    $relative = str_replace(["..\\", "../"], "", (string)$row["profile_image"]);
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relative);

    if (!is_file($fullPath) || !is_readable($fullPath)) {
        outputPlaceholder();
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string)finfo_file($finfo, $fullPath) : "application/octet-stream";
    if ($finfo) {
        finfo_close($finfo);
    }

    if (strpos($mime, "image/") !== 0) {
        outputPlaceholder();
    }

    header("Content-Type: " . $mime);
    header("Content-Length: " . (string)filesize($fullPath));
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    readfile($fullPath);
    exit;
} catch (Throwable $e) {
    outputPlaceholder();
}
