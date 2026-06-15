<?php
// One-time setup script — creates required tables not in project.sql
require_once 'includes/db.php';

$results = [];

$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(60) NOT NULL UNIQUE,
        email VARCHAR(120) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        exam_target VARCHAR(10) DEFAULT 'both',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "user_sessions" => "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject_name VARCHAR(60),
        category VARCHAR(8),
        year VARCHAR(8),
        total_q INT DEFAULT 0,
        correct INT DEFAULT 0,
        score_pct DECIMAL(5,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "user_performance" => "CREATE TABLE IF NOT EXISTS user_performance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject_name VARCHAR(60),
        mastery_score DECIMAL(5,2) DEFAULT 0,
        total_attempted INT DEFAULT 0,
        total_correct INT DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_subj (user_id, subject_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "user_topic_performance" => "CREATE TABLE IF NOT EXISTS user_topic_performance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject_name VARCHAR(60),
        topic VARCHAR(255),
        mastery_score DECIMAL(5,2) DEFAULT 0,
        total_attempted INT DEFAULT 0,
        total_correct INT DEFAULT 0,
        consecutive_correct INT DEFAULT 0,
        difficulty_level VARCHAR(10) DEFAULT 'easy',
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_topic (user_id, subject_name, topic)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "user_answers" => "CREATE TABLE IF NOT EXISTS user_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id INT,
        question_id INT NOT NULL,
        chosen VARCHAR(2),
        is_correct TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

try {
    $db = getDB();
    foreach ($tables as $name => $sql) {
        $db->exec($sql);
        $results[] = ['table' => $name, 'status' => 'OK ✔'];
    }

    // Verify main tables exist
    $check = ['questions', 'subjectname', 'subjectyear'];
    foreach ($check as $t) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            $results[] = ['table' => $t . ' (existing)', 'status' => "OK ✔ — $count rows"];
        } catch(Exception $e) {
            $results[] = ['table' => $t, 'status' => '✘ Not found — make sure you imported project.sql'];
        }
    }
} catch(Exception $e) {
    $results[] = ['table' => 'CONNECTION', 'status' => '✘ ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RBAPS Setup</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div style="max-width:600px;margin:4rem auto;padding:2rem">
  <div class="card">
    <h2 style="font-family:'DM Serif Display',serif;font-size:1.8rem;margin-bottom:.5rem">🔧 RBAPS Setup</h2>
    <p style="color:var(--text2);margin-bottom:1.5rem">Creates the required application tables in your database.</p>

    <?php foreach($results as $r): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem .75rem;border-radius:8px;margin-bottom:.4rem;background:var(--bg3)">
      <code style="font-family:'JetBrains Mono',monospace;font-size:.85rem;color:var(--text2)"><?= htmlspecialchars($r['table']) ?></code>
      <span style="font-size:.85rem"><?= $r['status'] ?></span>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap">
      <a href="index.php" class="btn btn-primary">Go to Homepage →</a>
      <a href="register.php" class="btn btn-ghost">Create Account</a>
    </div>

    <div class="alert alert-info" style="margin-top:1.5rem">
      ℹ️ You can delete or restrict access to this file (<code>setup.php</code>) after setup is complete.
    </div>
  </div>
</div>
</body>
</html>
