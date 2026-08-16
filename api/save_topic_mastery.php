<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorised']); exit; }

if (!isLoggedIn()) { echo json_encode(['ok'=>false]); exit; }

$data    = json_decode(file_get_contents('php://input'), true);
$uid     = $_SESSION['user_id'];
$subject = trim($data['subject'] ?? '');
$topics  = $data['mastery'] ?? [];

if (!$subject || !$topics) { echo json_encode(['ok'=>false]); exit; }

try {
    $db = getDB();

    // Ensure table exists
    $db->exec("CREATE TABLE IF NOT EXISTS user_topic_performance (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
        subject_name VARCHAR(60), topic VARCHAR(255),
        mastery_score DECIMAL(5,2) DEFAULT 0, total_attempted INT DEFAULT 0,
        total_correct INT DEFAULT 0, consecutive_correct INT DEFAULT 0,
        difficulty_level VARCHAR(10) DEFAULT 'easy',
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_topic (user_id, subject_name, topic))");

    $stmt = $db->prepare("
        INSERT INTO user_topic_performance
            (user_id, subject_name, topic, mastery_score, total_attempted, total_correct, consecutive_correct, difficulty_level)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            total_attempted    = total_attempted + VALUES(total_attempted),
            total_correct      = total_correct + VALUES(total_correct),
            mastery_score      = ROUND((total_correct / total_attempted) * 100, 2),
            consecutive_correct = VALUES(consecutive_correct),
            difficulty_level   = VALUES(difficulty_level),
            last_updated       = NOW()
    ");

    foreach ($topics as $t) {
        $stmt->execute([
            $uid, $subject,
            substr($t['topic'] ?? '', 0, 255),
            round($t['score'] ?? 0, 2),
            intval($t['attempted'] ?? 0),
            intval($t['correct'] ?? 0),
            intval($t['consecutive'] ?? 0),
            $t['difficulty'] ?? 'easy'
        ]);
    }

    // Also update overall subject mastery
    $overall = $db->prepare("
        SELECT SUM(total_correct) as tc, SUM(total_attempted) as ta
        FROM user_topic_performance WHERE user_id=? AND subject_name=?");
    $overall->execute([$uid, $subject]);
    $row = $overall->fetch();
    if ($row && $row['ta'] > 0) {
        $score = round($row['tc'] / $row['ta'] * 100, 2);
        $db->prepare("
            INSERT INTO user_performance (user_id, subject_name, mastery_score, total_attempted, total_correct)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE mastery_score=VALUES(mastery_score),
                total_attempted=VALUES(total_attempted), total_correct=VALUES(total_correct),
                last_updated=NOW()
        ")->execute([$uid, $subject, $score, $row['ta'], $row['tc']]);
    }

    echo json_encode(['ok'=>true]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'err'=>$e->getMessage()]);
}
