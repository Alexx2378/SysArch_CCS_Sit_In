<?php
declare(strict_types=1);

session_start();
require_once "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
    exit;
}

$loginInput = trim($_POST["idNumber"] ?? "");
$password = $_POST["password"] ?? "";
$remember = isset($_POST["remember"]) && (string)$_POST["remember"] === "1";

if ($loginInput === "" || $password === "") {
    header("Location: login.html?error=empty");
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT idNumber, firstName, lastName, email, password
        FROM users
        WHERE idNumber = ? OR email = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $loginInput, $loginInput);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $validLogin = false;

    if ($user) {
        if (password_verify($password, $user["password"])) {
            $validLogin = true;
        } elseif (hash_equals((string)$user["password"], $password)) {
            // Legacy plain-text fallback, then upgrade to hashed
            $validLogin = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upgrade = $conn->prepare("UPDATE users SET password = ? WHERE idNumber = ?");
            $upgrade->bind_param("ss", $newHash, $user["idNumber"]);
            $upgrade->execute();
            $upgrade->close();
        }
    }

    if (!$validLogin) {
        header("Location: login.html?error=invalid");
        exit;
    }

    session_regenerate_id(true);
    $_SESSION["idNumber"]  = $user["idNumber"];
    $_SESSION["firstName"] = $user["firstName"];
    $_SESSION["lastName"]  = $user["lastName"];
    $_SESSION["email"]     = $user["email"];

    // Remember only the login identifier for convenience; never store raw password.
    if ($remember) {
        setcookie("remembered_login", $loginInput, [
            "expires" => time() + (60 * 60 * 24 * 30),
            "path" => "/",
            "secure" => isset($_SERVER["HTTPS"]),
            "httponly" => false,
            "samesite" => "Lax",
        ]);
    } else {
        setcookie("remembered_login", "", [
            "expires" => time() - 3600,
            "path" => "/",
            "secure" => isset($_SERVER["HTTPS"]),
            "httponly" => false,
            "samesite" => "Lax",
        ]);
    }

    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        idNumber VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS user_history (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        idNumber VARCHAR(50) NOT NULL,
        activity VARCHAR(255) NOT NULL,
        details TEXT,
        activity_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50) DEFAULT 'Completed'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $log = $conn->prepare("INSERT INTO user_history (idNumber, activity, details, status) VALUES (?, 'Login', 'User logged in successfully', 'Completed')");
    $log->bind_param("s", $user["idNumber"]);
    $log->execute();
    $log->close();

    $notify = $conn->prepare("INSERT INTO notifications (idNumber, title, message) VALUES (?, 'Welcome Back', 'You logged in successfully.')");
    $notify->bind_param("s", $user["idNumber"]);
    $notify->execute();
    $notify->close();

    header("Location: dashboard.php");
    exit;
} catch (Throwable $e) {
    header("Location: login.html?error=db");
    exit;
}