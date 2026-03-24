<?php
session_start();

if (!isset($_SESSION["idNumber"])) {
    header("Location: login.html");
    exit;
}

require_once "db_connect.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function userValue(array $user, array $keys, string $fallback = ""): string {
  foreach ($keys as $key) {
    if (isset($user[$key]) && trim((string)$user[$key]) !== "") {
      return trim((string)$user[$key]);
    }
  }

  return $fallback;
}

function ensureDashboardTables(mysqli $conn): void {
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

  $conn->query("CREATE TABLE IF NOT EXISTS reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idNumber VARCHAR(50) NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    lab_room VARCHAR(50) NOT NULL,
    reservation_date DATE NOT NULL,
    time_in TIME NOT NULL,
    remaining_sessions INT DEFAULT 30,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
  if ($colCheck instanceof mysqli_result && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL");
  }

}

function addNotification(mysqli $conn, string $idNumber, string $title, string $message): void {
  try {
    $stmt = $conn->prepare("INSERT INTO notifications (idNumber, title, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $idNumber, $title, $message);
    $stmt->execute();
    $stmt->close();
  } catch (Throwable $e) {
    // Do not block page actions if notification insert fails.
  }
}

ensureDashboardTables($conn);

$successMessage = "";
$errorMessage = "";
$warningMessage = "";
$allowedPages = ["home-page", "edit-profile-page", "notification-page", "history-page", "reservation-page"];
$activePage = "home-page";

// Load current user by idNumber from session
$stmt = $conn->prepare("SELECT idNumber, firstName, lastName, middleName, course, courseLevel, email, address, profile_image FROM users WHERE idNumber = ? LIMIT 1");
$stmt->bind_param("s", $_SESSION["idNumber"]);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.html");
    exit;
}

// Handle Edit Profile submit
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {
  $activePage = "edit-profile-page";
  $idNumber    = trim($_POST["idNumber"] ?? "");
  $firstName   = trim($_POST["firstName"] ?? "");
  $lastName    = trim($_POST["lastName"] ?? "");
  $middleName  = trim($_POST["middleName"] ?? "");
  $course      = trim($_POST["course"] ?? "");
  $courseLevel = trim($_POST["courseLevel"] ?? "");
  $email       = trim($_POST["email"] ?? "");
  $address     = trim($_POST["address"] ?? "");
  $profileImage = $user["profile_image"] ?? null;
  $uploadWarning = "";

  if (
    $idNumber === "" || $firstName === "" || $lastName === "" ||
    $course === "" || $courseLevel === "" || $email === ""
  ) {
    $errorMessage = "Please fill in all required fields.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errorMessage = "Invalid email address.";
  } else {
    try {
      $currentIdNumber = $_SESSION["idNumber"];

      $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "profile";
      if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        $uploadWarning = "Profile details were saved, but image folder is not writable.";
      }

      // File upload path.
      if ($uploadWarning === "" && isset($_FILES["profile_image"]) && (int)$_FILES["profile_image"]["error"] === UPLOAD_ERR_OK && !empty($_FILES["profile_image"]["tmp_name"])) {
        $tmpPath = (string)$_FILES["profile_image"]["tmp_name"];
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
            $extension = $mimeMap[$mime];
            $safeName = preg_replace('/[^A-Za-z0-9_-]/', '', $idNumber);
            $fileName = $safeName . "_" . time() . "." . $extension;
            $targetAbs = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
            $targetRel = "uploads/profile/" . $fileName;

            $saved = @file_put_contents($targetAbs, $rawBytes) !== false;

            if ($saved) {
              $profileImage = $targetRel;
            } else {
              $uploadWarning = "Profile details were saved, but the image could not be written to disk.";
            }
          } else {
            $uploadWarning = "Profile details were saved, but image type is not supported.";
          }
        } else {
          $uploadWarning = "Profile details were saved, but the selected file is not a valid image.";
        }
      }

      // Camera snapshot fallback path.
      $cameraImageData = trim($_POST["camera_image_data"] ?? "");
      if ($uploadWarning === "" && $cameraImageData !== "" && str_starts_with($cameraImageData, "data:image/")) {
        if (preg_match('/^data:image\/(png|jpeg|jpg|webp|gif);base64,(.+)$/', $cameraImageData, $matches)) {
          $ext = strtolower($matches[1]) === "jpeg" ? "jpg" : strtolower($matches[1]);
          $raw = base64_decode(str_replace(' ', '+', $matches[2]), true);

          if ($raw !== false) {
            $safeName = preg_replace('/[^A-Za-z0-9_-]/', '', $idNumber);
            $fileName = $safeName . "_" . time() . "." . $ext;
            $targetAbs = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
            $targetRel = "uploads/profile/" . $fileName;
            if (@file_put_contents($targetAbs, $raw) !== false) {
              $profileImage = $targetRel;
            } else {
              $uploadWarning = "Profile details were saved, but the camera image could not be written to disk.";
            }
          }
        }
      }

      if ($uploadWarning === "" && isset($_FILES["profile_image"]) && (int)$_FILES["profile_image"]["error"] !== UPLOAD_ERR_NO_FILE && (int)$_FILES["profile_image"]["error"] !== UPLOAD_ERR_OK) {
        $uploadWarning = "Profile details were saved, but image upload failed.";
      }

      // Unlimited edits: if new ID/email conflicts, keep current ID/email and still save everything else.
      $identityConflict = false;
      $identityCheck = $conn->prepare("SELECT idNumber FROM users WHERE (idNumber = ? OR email = ?) AND idNumber <> ? LIMIT 1");
      $identityCheck->bind_param("sss", $idNumber, $email, $currentIdNumber);
      $identityCheck->execute();
      $identityConflict = (bool)$identityCheck->get_result()->fetch_assoc();
      $identityCheck->close();

      $identityWarning = "";

      if ($identityConflict) {
        $update = $conn->prepare("\n                    UPDATE users\n                    SET firstName = ?, lastName = ?, middleName = ?,\n                        course = ?, courseLevel = ?, address = ?,\n                        profile_image = ?\n                    WHERE idNumber = ?\n                ");
        $update->bind_param(
          "ssssssss",
          $firstName,
          $lastName,
          $middleName,
          $course,
          $courseLevel,
          $address,
          $profileImage,
          $currentIdNumber
        );
        $update->execute();
        $update->close();

        $identityWarning = "ID Number and Email remained unchanged because they already exist.";
      } else {
        try {
          $update = $conn->prepare("\n                    UPDATE users\n                    SET idNumber = ?, firstName = ?, lastName = ?, middleName = ?,\n                        course = ?, courseLevel = ?, email = ?, address = ?,\n                        profile_image = ?\n                    WHERE idNumber = ?\n                ");
          $update->bind_param(
            "ssssssssss",
            $idNumber,
            $firstName,
            $lastName,
            $middleName,
            $course,
            $courseLevel,
            $email,
            $address,
            $profileImage,
            $currentIdNumber
          );
          $update->execute();
          $update->close();
        } catch (mysqli_sql_exception $ex) {
          // Race-safe fallback for duplicate key: keep current identity but save all other fields.
          if ((int)$ex->getCode() === 1062) {
            $identityConflict = true;
            $identityWarning = "ID Number and Email remained unchanged because they already exist.";

            $fallback = $conn->prepare("\n                        UPDATE users\n                        SET firstName = ?, lastName = ?, middleName = ?,\n                            course = ?, courseLevel = ?, address = ?,\n                            profile_image = ?\n                        WHERE idNumber = ?\n                    ");
            $fallback->bind_param(
              "ssssssss",
              $firstName,
              $lastName,
              $middleName,
              $course,
              $courseLevel,
              $address,
              $profileImage,
              $currentIdNumber
            );
            $fallback->execute();
            $fallback->close();
          } else {
            throw $ex;
          }
        }
      }

      if ($identityWarning !== "") {
        $uploadWarning = trim($uploadWarning . " " . $identityWarning);
      }

      if (!$identityConflict) {
        $_SESSION["idNumber"] = $idNumber;
        $_SESSION["email"] = $email;
      }
      $_SESSION["firstName"] = $firstName;
      $_SESSION["lastName"] = $lastName;

      // Post-save refresh and notifications are non-critical; failures here should not mark update as failed.
      try {
        $reload = $conn->prepare("SELECT idNumber, firstName, lastName, middleName, course, courseLevel, email, address, profile_image FROM users WHERE idNumber = ? LIMIT 1");
        if ($reload !== false) {
          $reload->bind_param("s", $_SESSION["idNumber"]);
          $reload->execute();
          $freshUser = $reload->get_result()->fetch_assoc();
          if (is_array($freshUser)) {
            $user = $freshUser;
          }
          $reload->close();
        }
      } catch (Throwable $ignore) {
      }

      addNotification($conn, $_SESSION["idNumber"], "Profile Updated", "Your profile information was updated successfully.");

      $redirectUrl = "dashboard.php?result=profile_success";
      if ($uploadWarning !== "") {
        addNotification($conn, $_SESSION["idNumber"], "Profile Image Notice", $uploadWarning);
      }

      header("Location: " . $redirectUrl);
      exit;
    } catch (mysqli_sql_exception $ex) {
      addNotification($conn, $_SESSION["idNumber"], "Profile Update Failed", "profile update is unsuccessful");
      header("Location: dashboard.php");
      exit;
    } catch (Throwable $ex) {
      header("Location: dashboard.php");
      exit;
    }
  }
}

      if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["book_reservation"])) {
        $reserveId = trim((string)($_SESSION["idNumber"] ?? ""));
        $reserveName = trim(
          userValue($user, ["firstName"], "") . " " .
          userValue($user, ["middleName"], "") . " " .
          userValue($user, ["lastName"], "")
        );
        $reservePurpose = trim($_POST["reserve_purpose"] ?? "");
        $reserveLab = trim($_POST["reserve_lab"] ?? "");
        $reserveTime = trim($_POST["reserve_time"] ?? "");
        $reserveDate = trim($_POST["reserve_date"] ?? "");
        $reserveSessions = (int)($_POST["reserve_sessions"] ?? 30);

        if ($reserveName === "" || $reservePurpose === "" || $reserveLab === "" || $reserveTime === "" || $reserveDate === "") {
          $errorMessage = "Please complete all reservation fields.";
          $activePage = "reservation-page";
        } else {
          try {
            $book = $conn->prepare("INSERT INTO reservations (idNumber, student_name, purpose, lab_room, reservation_date, time_in, remaining_sessions, status)
              VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $book->bind_param("ssssssi", $reserveId, $reserveName, $reservePurpose, $reserveLab, $reserveDate, $reserveTime, $reserveSessions);
            $book->execute();
            $book->close();

            $activity = $conn->prepare("INSERT INTO user_history (idNumber, activity, details, status) VALUES (?, 'Reservation Submitted', ?, 'Pending')");
            $details = "Purpose: {$reservePurpose}, Lab: {$reserveLab}, Date: {$reserveDate} {$reserveTime}";
            $activity->bind_param("ss", $reserveId, $details);
            $activity->execute();
            $activity->close();

            $notify = $conn->prepare("INSERT INTO notifications (idNumber, title, message) VALUES (?, 'Reservation Submitted', ?)");
            $notifyMsg = "Your reservation for {$reserveDate} at {$reserveTime} is now pending review.";
            $notify->bind_param("ss", $reserveId, $notifyMsg);
            $notify->execute();
            $notify->close();

            header("Location: dashboard.php?page=reservation-page&success=reservation_submitted");
            exit;
          } catch (mysqli_sql_exception $ex) {
            $errorMessage = "Unable to submit reservation right now. Please try again.";
            $activePage = "reservation-page";
          }
        }
      }

      if ($_SERVER["REQUEST_METHOD"] !== "POST" && isset($_GET["result"])) {
        if ($_GET["result"] === "profile_success") {
          $successMessage = "profile edited successfully";
        }
      }

      if ($_SERVER["REQUEST_METHOD"] !== "POST" && isset($_GET["success"])) {
        if ($_GET["success"] === "reservation_submitted") {
          $successMessage = "Reservation submitted successfully.";
        }
      }

      $notifications = [];
      $noteStmt = $conn->prepare("SELECT title, message, created_at FROM notifications WHERE idNumber = ? ORDER BY created_at DESC LIMIT 10");
      $noteStmt->bind_param("s", $_SESSION["idNumber"]);
      $noteStmt->execute();
      $noteResult = $noteStmt->get_result();
      while ($row = $noteResult->fetch_assoc()) {
        $notifications[] = $row;
      }
      $noteStmt->close();

$studentId = userValue($user, ["idNumber"], (string)($_SESSION["idNumber"] ?? ""));
$studentFirstName = userValue($user, ["firstName", "firstname"], (string)($_SESSION["firstName"] ?? ""));
$studentLastName = userValue($user, ["lastName", "lastname"], (string)($_SESSION["lastName"] ?? ""));
$studentMiddleName = userValue($user, ["middleName", "middlename"]);
$studentCourse = userValue($user, ["course", "program"]);
$studentYearLevel = userValue($user, ["courseLevel", "yearLevel", "year"]);
$studentEmail = userValue($user, ["email"], (string)($_SESSION["email"] ?? ""));
$studentAddress = userValue($user, ["address"]);

$fullName = trim($studentFirstName . " " . $studentMiddleName . " " . $studentLastName);
if ($fullName === "") {
    $fullName = "Student";
}

$historyRows = [];
$historyStmt = $conn->prepare("SELECT idNumber, student_name, purpose, lab_room, time_in, reservation_date, status
  FROM reservations
  WHERE idNumber = ?
  ORDER BY reservation_date DESC, time_in DESC
  LIMIT 100");
$historyStmt->bind_param("s", $_SESSION["idNumber"]);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
while ($row = $historyResult->fetch_assoc()) {
  $historyRows[] = [
    "idNumber" => $row["idNumber"] ?? $studentId,
    "name" => trim((string)($row["student_name"] ?? "")) !== "" ? $row["student_name"] : $fullName,
    "sitpurpose" => $row["purpose"] ?? "-",
    "laboratory" => $row["lab_room"] ?? "-",
    "login" => $row["time_in"] ?? "-",
    "logout" => "-",
    "date" => $row["reservation_date"] ?? "-",
    "action" => $row["status"] ?? "Pending",
  ];
}
$historyStmt->close();

$profileImageSrc = "profile_image.php?id=" . rawurlencode($studentId) . "&v=" . (string)time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    #dashboard-message-modal {
      position: fixed;
      inset: 0;
      background: rgba(15, 18, 35, 0.55);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1200;
      padding: 20px;
    }

    #dashboard-message-modal.show {
      display: flex;
    }

    #dashboard-message-modal .message-box {
      width: min(420px, 100%);
      background: #fff;
      border: 1px solid rgba(18, 28, 58, 0.12);
      border-radius: 16px;
      box-shadow: 0 20px 50px rgba(10, 15, 35, 0.24);
      padding: 22px;
      text-align: center;
    }
  </style>
</head>
<body data-page="<?php echo e($activePage); ?>">
  <div class="bg-shape shape-one" aria-hidden="true"></div>
  <div class="bg-shape shape-two" aria-hidden="true"></div>

  <div id="dashboard-message-modal" class="message-modal" aria-hidden="true">
    <div class="message-box" role="alertdialog" aria-live="polite" aria-modal="true">
      <h3 id="dashboard-message-title">Message</h3>
      <p id="dashboard-message-text"></p>
      <button type="button" id="dashboard-message-close" class="message-close-btn">OK</button>
    </div>
  </div>

  <header class="topbar">
    <div class="brand-wrap">
      <p class="brand-eyebrow">University of Cebu</p>
      <div class="logo">Student Dashboard</div>
    </div>

    <nav class="nav-links" aria-label="Dashboard Navigation">
      <a href="#" id="home-link" class="<?php echo $activePage === 'home-page' ? 'active-nav' : ''; ?>"><i class="fa-solid fa-house"></i> Home</a>
      <a href="#" id="notification-link"><i class="fa-solid fa-bell"></i> Notifications</a>
      <a href="#" id="edit-profile-link"><i class="fa-solid fa-user-pen"></i> Edit Profile</a>
      <a href="#" id="history-link"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
      <a href="#" id="reservation-link"><i class="fa-solid fa-calendar-check"></i> Reservation</a>
      <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</a>
    </nav>
  </header>

  <main class="main-content">
    <?php
      $modalType = "";
      $modalText = "";
      if ($errorMessage !== "") {
        $modalType = "error";
        $modalText = $errorMessage;
      } elseif ($warningMessage !== "") {
        $modalType = "warning";
        $modalText = $warningMessage;
      } elseif ($successMessage !== "") {
        $modalType = "success";
        $modalText = $successMessage;
      }
    ?>

    <section id="home-page" class="page <?php echo $activePage === 'home-page' ? 'active-page' : ''; ?>" aria-label="Home Page">
      <div class="home-shell">
        <section class="home-hero" aria-label="Welcome Section">
          <div class="hero-copy">
            <p class="hero-kicker">STUDENT PORTAL</p>
            <h1>Welcome back, <?php echo e($studentFirstName !== "" ? $studentFirstName : "Student"); ?></h1>
            <p class="hero-subtitle">Track announcements, laboratory rules, and your latest activity in one clean dashboard.</p>

            <div class="hero-actions">
              <a href="#" id="hero-reservation-link" class="hero-btn hero-btn-primary">
                <i class="fa-solid fa-calendar-check"></i>
                New Reservation
              </a>
              <a href="#" id="hero-edit-link" class="hero-btn hero-btn-outline">
                <i class="fa-solid fa-user-pen"></i>
                Edit Profile
              </a>
            </div>
          </div>

          <aside class="hero-image-wrapper" aria-label="Greeting Illustration">
            <img src="greeting.png" alt="Greeting" class="hero-greeting-image">
          </aside>
        </section>

        <div class="quick-stats">
          <article class="stat-card">
            <div class="stat-top">
              <span class="stat-label">Remaining Sessions</span>
              <i class="fa-regular fa-hourglass-half"></i>
            </div>
            <h3>30</h3>
            <p class="stat-sub">Available this term</p>
          </article>
          <article class="stat-card">
            <div class="stat-top">
              <span class="stat-label">Current Year</span>
              <i class="fa-solid fa-layer-group"></i>
            </div>
            <h3><?php echo e($studentYearLevel !== "" ? $studentYearLevel : "N/A"); ?></h3>
            <p class="stat-sub">Academic standing</p>
          </article>
          <article class="stat-card">
            <div class="stat-top">
              <span class="stat-label">Program</span>
              <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h3><?php echo e($studentCourse !== "" ? $studentCourse : "N/A"); ?></h3>
            <p class="stat-sub">Registered program</p>
          </article>
          <article class="stat-card">
            <div class="stat-top">
              <span class="stat-label">Notifications</span>
              <i class="fa-solid fa-bell"></i>
            </div>
            <h3><?php echo e((string)count($notifications)); ?></h3>
            <p class="stat-sub">Recent updates</p>
          </article>
        </div>

        <div class="home-grid">
          <section class="card student-card">
            <div class="card-header">
              <i class="fa-solid fa-user-graduate"></i>
              <span>Student Information</span>
            </div>

            <div class="profile-section">
              <img src="<?php echo e($profileImageSrc); ?>" alt="Student Profile" class="profile-img" id="home-student-profile-img">
              <h3><?php echo e($fullName); ?></h3>
              <p class="student-role"><?php echo e($studentCourse); ?></p>
            </div>

            <div class="info-list">
              <div class="info-item">
                <i class="fa-solid fa-id-card"></i>
                <span><strong>ID Number:</strong> <?php echo e($studentId); ?></span>
              </div>
              <div class="info-item">
                <i class="fa-solid fa-book"></i>
                <span><strong>Course:</strong> <?php echo e($studentCourse); ?></span>
              </div>
              <div class="info-item">
                <i class="fa-solid fa-layer-group"></i>
                <span><strong>Year:</strong> <?php echo e($studentYearLevel); ?></span>
              </div>
              <div class="info-item">
                <i class="fa-solid fa-envelope"></i>
                <span><strong>Email:</strong> <?php echo e($studentEmail); ?></span>
              </div>
              <div class="info-item">
                <i class="fa-solid fa-location-dot"></i>
                <span><strong>Address:</strong> <?php echo e($studentAddress); ?></span>
              </div>
            </div>
          </section>

          <div class="home-main-stack">
            <section class="card announcement-card">
              <div class="card-header">
                <i class="fa-solid fa-bullhorn"></i>
                <span>Latest Announcements</span>
              </div>

              <div class="announcement-list">
                <article class="announcement-item">
                  <h4>CCS Admin • 2026-Feb-11</h4>
                  <p>Stay updated with campus activities, laboratory schedules, and department notices posted weekly.</p>
                </article>
                <article class="announcement-item">
                  <h4>CCS Admin • 2026-Jan-28</h4>
                  <p>New reservation windows are now available for the software engineering and networking laboratories.</p>
                </article>
                <article class="announcement-item">
                  <h4>CCS Admin • 2025-Dec-10</h4>
                  <p>Please check your reservation status 24 hours before class to avoid schedule conflicts.</p>
                </article>
              </div>
            </section>

            <section class="card rules-card">
              <div class="card-header">
                <i class="fa-solid fa-scale-balanced"></i>
                <span>Laboratory Rules and Regulation</span>
              </div>

              <div class="rules-content">
                <h2>University of Cebu</h2>
                <h3>College of Information and Computer Studies</h3>
                <h4>Laboratory Guidelines</h4>
                <p>To maintain safety, respect, and productivity in the laboratory, observe these rules:</p>
                <ol>
                  <li>Maintain silence, proper decorum, and discipline inside the laboratory.</li>
                  <li>Games and unrelated activities are not allowed during laboratory sessions.</li>
                  <li>Internet use is for academic purposes only, with instructor approval.</li>
                  <li>Observe cleanliness and keep workstations free from clutter or trash.</li>
                  <li>Eating, drinking, and vandalism are strictly prohibited in all labs.</li>
                </ol>
              </div>
            </section>
          </div>
        </div>
      </div>
    </section>

    <section id="edit-profile-page" class="page <?php echo $activePage === 'edit-profile-page' ? 'active-page' : ''; ?>" aria-label="Edit Profile Page">
      <div class="form-page-wrapper edit-profile-wrapper">
        <div class="page-head">
          <p class="hero-kicker">Account Settings</p>
          <h2 class="page-title">Edit Profile</h2>
          <p>Update your personal and academic details in one organized workspace.</p>
        </div>

        <form class="simple-form edit-profile-form" method="POST" autocomplete="off" enctype="multipart/form-data">
          <div class="profile-editor-grid">
            <aside class="profile-media-panel">
              <div class="media-card">
                <h3>Profile Photo</h3>
                <p>Upload an image or take one using your camera.</p>

                <div class="profile-circle-wrap">
                  <img src="<?php echo e($profileImageSrc); ?>" alt="Profile Preview" class="profile-preview" id="profile-preview">
                  <button type="button" id="quick-camera-btn" class="circle-action camera-trigger" title="Take photo">
                    <i class="fa-solid fa-camera"></i>
                  </button>
                  <label for="profile-image" class="circle-action upload-trigger" title="Upload image">
                    <i class="fa-solid fa-image"></i>
                  </label>
                </div>
                <p class="media-hint">Tap the circle photo to upload. Use camera icon to take a picture.</p>

                <input type="file" id="profile-image" name="profile_image" accept="image/*" capture="user" class="hidden-file-input">
                <input type="hidden" id="camera-image-data" name="camera_image_data" value="">

                <div class="camera-block">
                  <video id="camera-preview" class="camera-preview" autoplay playsinline muted></video>
                  <canvas id="camera-canvas" class="camera-canvas"></canvas>

                  <div class="camera-actions">
                      <button type="button" class="ghost-btn" id="start-camera-btn"><i class="fa-solid fa-play"></i> Start</button>
                      <button type="button" class="ghost-btn" id="capture-camera-btn"><i class="fa-solid fa-camera"></i> Capture</button>
                      <button type="button" class="ghost-btn" id="stop-camera-btn"><i class="fa-solid fa-stop"></i> Stop</button>
                  </div>
                </div>
              </div>
            </aside>

            <div class="profile-fields-panel">
              <div class="fields-panel-head">
                <h3>Student Details</h3>
                <p>Keep your profile information accurate and up to date.</p>
              </div>

              <div class="field-groups">
                <section class="field-group">
                  <h4 class="group-title">Academic Information</h4>
                  <div class="group-grid group-grid-2col">
                    <div class="simple-form-row">
                      <label for="student-id">ID Number</label>
                      <input type="text" id="student-id" name="idNumber" value="<?php echo e($studentId); ?>" required>
                    </div>

                    <div class="simple-form-row">
                      <label for="course">Course</label>
                      <select id="course" name="course" required>
                        <option value="Information Technology" <?php echo $studentCourse === "Information Technology" ? "selected" : ""; ?>>Information Technology</option>
                        <option value="Computer Science" <?php echo $studentCourse === "Computer Science" ? "selected" : ""; ?>>Computer Science</option>
                      </select>
                    </div>

                    <div class="simple-form-row">
                      <label for="year-level">Year Level</label>
                      <select id="year-level" name="courseLevel" required>
                        <option value="1" <?php echo in_array((string)$studentYearLevel, ["1", "1st Year"], true) ? "selected" : ""; ?>>1st Year</option>
                        <option value="2" <?php echo in_array((string)$studentYearLevel, ["2", "2nd Year", "Second Year"], true) ? "selected" : ""; ?>>2nd Year</option>
                        <option value="3" <?php echo in_array((string)$studentYearLevel, ["3", "3rd Year"], true) ? "selected" : ""; ?>>3rd Year</option>
                        <option value="4" <?php echo in_array((string)$studentYearLevel, ["4", "4th Year"], true) ? "selected" : ""; ?>>4th Year</option>
                      </select>
                    </div>
                  </div>
                </section>

                <section class="field-group">
                  <h4 class="group-title">Personal Information</h4>
                  <div class="group-grid group-grid-2col">
                    <div class="simple-form-row">
                      <label for="last-name">Last Name</label>
                      <input type="text" id="last-name" name="lastName" value="<?php echo e($studentLastName); ?>" required>
                    </div>

                    <div class="simple-form-row">
                      <label for="first-name">First Name</label>
                      <input type="text" id="first-name" name="firstName" value="<?php echo e($studentFirstName); ?>" required>
                    </div>

                    <div class="simple-form-row">
                      <label for="middle-name">Middle Name</label>
                      <input type="text" id="middle-name" name="middleName" value="<?php echo e($studentMiddleName); ?>">
                    </div>

                    <div class="simple-form-row">
                      <label for="email">Email</label>
                      <input type="email" id="email" name="email" value="<?php echo e($studentEmail); ?>" required>
                    </div>

                    <div class="simple-form-row full-width">
                      <label for="address">Address</label>
                      <input type="text" id="address" name="address" value="<?php echo e($studentAddress); ?>">
                    </div>
                  </div>
                </section>
              </div>
            </div>
          </div>

          <div class="simple-form-actions">
            <button type="submit" name="update_profile" value="1" class="primary-btn">Save Changes</button>
          </div>
        </form>
      </div>
    </section>

    <section id="notification-page" class="page <?php echo $activePage === 'notification-page' ? 'active-page' : ''; ?>" aria-label="Notifications Page">
      <div class="placeholder-page">
        <div class="page-head">
          <h2 class="page-title"><i class="fa-solid fa-bell"></i> Notifications</h2>
          <p>Recent updates from administrators and instructors.</p>
        </div>

        <div class="timeline-list">
          <?php if (count($notifications) === 0): ?>
            <article class="timeline-item">
              <span class="timeline-date">No Notifications</span>
              <h4>You're all caught up</h4>
              <p>No notifications yet. Reservation and login updates will appear here.</p>
            </article>
          <?php else: ?>
            <?php foreach ($notifications as $note): ?>
              <article class="timeline-item">
                <span class="timeline-date"><?php echo e(date('M d, Y h:i A', strtotime($note['created_at']))); ?></span>
                <h4><?php echo e($note['title']); ?></h4>
                <p><?php echo e($note['message']); ?></p>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section id="history-page" class="page <?php echo $activePage === 'history-page' ? 'active-page' : ''; ?>" aria-label="History Page">
      <div class="placeholder-page">
        <div class="page-head">
          <h2 class="page-title"><i class="fa-solid fa-clock-rotate-left"></i> Activity History</h2>
          <p>Review your latest lab entries and reservation activity.</p>
        </div>

        <div class="history-toolbar">
          <label for="history-search"><i class="fa-solid fa-magnifying-glass"></i> Search</label>
          <input type="text" id="history-search" placeholder="Search history..." autocomplete="off">
        </div>

        <div class="history-table-wrap">
          <table class="history-table">
            <thead>
              <tr>
                <th>ID Number</th>
                <th>Name</th>
                <th>Sit Purpose</th>
                <th>Laboratory</th>
                <th>Login</th>
                <th>Logout</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="history-table-body">
              <?php if (count($historyRows) === 0): ?>
                <tr>
                  <td colspan="8">No history data available yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($historyRows as $item): ?>
                  <tr data-history-row="1">
                    <td><?php echo e($item['idNumber']); ?></td>
                    <td><?php echo e($item['name']); ?></td>
                    <td><?php echo e($item['sitpurpose']); ?></td>
                    <td><?php echo e($item['laboratory']); ?></td>
                    <td><?php echo e($item['login'] !== '-' ? date('H:i', strtotime((string)$item['login'])) : '-'); ?></td>
                    <td><?php echo e($item['logout']); ?></td>
                    <td><?php echo e($item['date'] !== '-' ? date('Y-m-d', strtotime((string)$item['date'])) : '-'); ?></td>
                    <td><span class="status-pill <?php echo strtolower((string)$item['action']) === 'pending' ? 'pending' : 'done'; ?>"><?php echo e($item['action']); ?></span></td>
                  </tr>
                <?php endforeach; ?>
                <tr id="history-no-results" style="display:none;">
                  <td colspan="8">No matching history records.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section id="reservation-page" class="page <?php echo $activePage === 'reservation-page' ? 'active-page' : ''; ?>" aria-label="Reservation Page">
      <div class="form-page-wrapper">
        <div class="page-head">
          <h2 class="page-title"><i class="fa-solid fa-calendar-check"></i> New Reservation</h2>
          <p>Complete your laboratory reservation details.</p>
        </div>

        <form class="simple-form" method="POST" autocomplete="off">
          <div class="simple-form-grid">
            <div class="simple-form-row">
              <label for="reserve-id"><i class="fa-solid fa-id-card"></i> ID Number</label>
              <input type="text" id="reserve-id" name="reserve_id" value="<?php echo e($studentId); ?>" readonly>
            </div>

            <div class="simple-form-row">
              <label for="reserve-name"><i class="fa-solid fa-user"></i> Student Name</label>
              <input type="text" id="reserve-name" name="reserve_name" value="<?php echo e($fullName); ?>" readonly>
            </div>

            <div class="simple-form-row">
              <label for="reserve-purpose"><i class="fa-solid fa-bullseye"></i> Purpose</label>
              <input type="text" id="reserve-purpose" name="reserve_purpose" placeholder="e.g. C Programming" required>
            </div>

            <div class="simple-form-row">
              <label for="reserve-lab"><i class="fa-solid fa-flask"></i> Laboratory</label>
              <input type="text" id="reserve-lab" name="reserve_lab" placeholder="e.g. 524" required>
            </div>

            <div class="simple-form-row">
              <label for="reserve-time"><i class="fa-regular fa-clock"></i> Time In</label>
              <input type="time" id="reserve-time" name="reserve_time" required>
            </div>

            <div class="simple-form-row">
              <label for="reserve-date"><i class="fa-regular fa-calendar"></i> Date</label>
              <input type="date" id="reserve-date" name="reserve_date" required>
            </div>

            <div class="simple-form-row full-width">
              <label for="reserve-sessions"><i class="fa-regular fa-hourglass-half"></i> Remaining Sessions</label>
              <input type="text" id="reserve-sessions" name="reserve_sessions" value="30" readonly>
            </div>
          </div>

          <div class="simple-form-actions action-split">
            <button type="submit" name="book_reservation" value="1" class="primary-btn"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
            <button type="button" class="ghost-btn"><i class="fa-solid fa-bolt"></i> Reserve Now</button>
          </div>
        </form>
      </div>
    </section>
  </main>

  <script src="script.js"></script>
  <script>
    (function () {
      const type = <?php echo json_encode($modalType, JSON_UNESCAPED_UNICODE); ?>;
      const text = <?php echo json_encode($modalText, JSON_UNESCAPED_UNICODE); ?>;
      if (!type || !text) {
        return;
      }

      const modal = document.getElementById("dashboard-message-modal");
      const title = document.getElementById("dashboard-message-title");
      const body = document.getElementById("dashboard-message-text");
      const closeBtn = document.getElementById("dashboard-message-close");

      if (!modal || !title || !body || !closeBtn) {
        return;
      }

      const titles = {
        error: "Error",
        warning: "Notice",
        success: "Success"
      };

      title.textContent = titles[type] || "Message";
      body.textContent = text;
      modal.classList.add("show");
      modal.setAttribute("aria-hidden", "false");

      closeBtn.addEventListener("click", function () {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        window.history.replaceState({}, document.title, window.location.pathname);
      });
    })();
  </script>
</body>
</html>
<?php $conn->close(); ?>