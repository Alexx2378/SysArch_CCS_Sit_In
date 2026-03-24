<?php
declare(strict_types=1);

require_once "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: forgot_password.html");
    exit;
}

$loginInput = trim($_POST["loginInput"] ?? "");
$newPassword = $_POST["newPassword"] ?? "";
$confirmNewPassword = $_POST["confirmNewPassword"] ?? "";

if ($loginInput === "" || $newPassword === "" || $confirmNewPassword === "") {
    header("Location: forgot_password.html?status=error&code=required");
    exit;
}

if ($newPassword !== $confirmNewPassword) {
    header("Location: forgot_password.html?status=error&code=mismatch");
    exit;
}

if (strlen($newPassword) < 8) {
    header("Location: forgot_password.html?status=error&code=password_length");
    exit;
}

try {
    $lookup = $conn->prepare("SELECT idNumber FROM users WHERE idNumber = ? OR email = ? LIMIT 1");
    $lookup->bind_param("ss", $loginInput, $loginInput);
    $lookup->execute();
    $user = $lookup->get_result()->fetch_assoc();
    $lookup->close();

    if (!$user || !isset($user["idNumber"])) {
        header("Location: forgot_password.html?status=error&code=not_found");
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE users SET password = ? WHERE idNumber = ?");
    $update->bind_param("ss", $newHash, $user["idNumber"]);
    $update->execute();
    $update->close();

    header("Location: login.html?reset=success");
    exit;
} catch (Throwable $e) {
    header("Location: forgot_password.html?status=error&code=db");
    exit;
}
