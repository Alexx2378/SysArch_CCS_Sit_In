<?php
declare(strict_types=1);

// Show mysqli errors during development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.html");
    exit;
}

try {
    $conn = new mysqli("localhost", "root", "", "ccs_sit_in");
    $conn->set_charset("utf8mb4");

    // Ensure profile image column exists even if dashboard hasn't been opened yet.
    $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if ($colCheck instanceof mysqli_result && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL");
    }

    // Collect and sanitize
    $idNumber        = trim($_POST["idNumber"] ?? "");
    $firstName       = trim($_POST["firstName"] ?? "");
    $lastName        = trim($_POST["lastName"] ?? "");
    $middleName      = trim($_POST["middleName"] ?? "");
    $course          = trim($_POST["course"] ?? "");
    $courseLevel     = trim($_POST["courseLevel"] ?? "");
    $email           = trim($_POST["email"] ?? "");
    $address         = trim($_POST["address"] ?? "");
    $passwordRaw     = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirmPassword"] ?? "";

    // Basic validation
    if (
        $idNumber === "" || $firstName === "" || $lastName === "" ||
        $course === "" || $courseLevel === "" || $email === "" ||
        $passwordRaw === "" || $confirmPassword === ""
    ) {
        header("Location: register.html?status=error&code=required");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.html?status=error&code=email");
        exit;
    }

    if ($passwordRaw !== $confirmPassword) {
        header("Location: register.html?status=error&code=password_mismatch");
        exit;
    }

    if (strlen($passwordRaw) < 8) {
        header("Location: register.html?status=error&code=password_length");
        exit;
    }

    $profileImage = null;
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "profile";
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    // Prefer uploaded file when present.
    if (isset($_FILES["profile_image"]) && (int)($_FILES["profile_image"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmpPath = (string)($_FILES["profile_image"]["tmp_name"] ?? "");
        if ($tmpPath !== "") {
            $rawBytes = @file_get_contents($tmpPath);
            $imageInfo = $rawBytes !== false ? @getimagesizefromstring($rawBytes) : false;

            if ($imageInfo !== false && $rawBytes !== false) {
                $mime = (string)($imageInfo["mime"] ?? "");
                $mimeMap = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp",
                    "image/gif" => "gif",
                ];

                if (isset($mimeMap[$mime])) {
                    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '', $idNumber);
                    $fileName = $safeName . "_" . time() . "." . $mimeMap[$mime];
                    $targetAbs = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                    if (@file_put_contents($targetAbs, $rawBytes) !== false) {
                        $profileImage = "uploads/profile/" . $fileName;
                    }
                }
            }
        }
    }

    // Camera capture fallback when no file upload was saved.
    if ($profileImage === null) {
        $cameraImageData = trim((string)($_POST["camera_image_data"] ?? ""));
        if ($cameraImageData !== "" && str_starts_with($cameraImageData, "data:image/")) {
            if (preg_match('/^data:image\/(png|jpeg|jpg|webp|gif);base64,(.+)$/', $cameraImageData, $matches)) {
                $ext = strtolower($matches[1]) === "jpeg" ? "jpg" : strtolower($matches[1]);
                $raw = base64_decode(str_replace(' ', '+', $matches[2]), true);
                if ($raw !== false) {
                    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '', $idNumber);
                    $fileName = $safeName . "_" . time() . "." . $ext;
                    $targetAbs = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                    if (@file_put_contents($targetAbs, $raw) !== false) {
                        $profileImage = "uploads/profile/" . $fileName;
                    }
                }
            }
        }
    }

    $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);

    // Prepared statement prevents SQL injection
    $stmt = $conn->prepare("
        INSERT INTO users (
            idNumber, firstName, lastName, middleName,
            course, courseLevel, email, address, password, profile_image
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssssssss",
        $idNumber, $firstName, $lastName, $middleName,
        $course, $courseLevel, $email, $address, $passwordHash, $profileImage
    );

    $stmt->execute();
    $stmt->close();
    $conn->close();

    header("Location: register.html?status=success&code=registered");
    exit;
} catch (mysqli_sql_exception $e) {
    // Duplicate key (e.g., duplicate idNumber or email)
    if ((int)$e->getCode() === 1062) {
        header("Location: register.html?status=error&code=duplicate");
        exit;
    }
    header("Location: register.html?status=error&code=db");
    exit;
}
?>