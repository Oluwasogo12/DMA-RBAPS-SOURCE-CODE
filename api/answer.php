<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorised']); exit; }

if (!isLoggedIn()) { echo json_encode(['ok'=>false]); exit; }

$data       = json_decode(file_get_contents('php://input'), true);
$uid        = $_SESSION['user_id'];
$qId        = intval($data['question_id'] ?? 0);
$chosen     = substr($data['chosen'] ?? '', 0, 2);
$isCorrect  = intval($data['is_correct'] ?? 0);
$subject    = trim($data['subject'] ?? '');
$topic      = trim($data['topic'] ?? '');
$masteryScr = floatval($data['mastery'] ?? 0);
$consec     = intval($data['consecutive'] ?? 0);
$difficulty = $data['difficulty'] ?? 'easy';

if (!$qId || !$subject) { echo json_encode(['ok'=>false]); exit; }

try {
    $db = getDB();

    // Store answer
    $db->prepare("INSERT INTO user_answers (user_id, question_id, chosen, is_correct) VALUES (?,?,?,?)")
       ->execute([$uid, $qId, $chosen, $isCorrect]);

    // Update overall subject mastery
    $db->prepare("
        INSERT INTO user_performance (user_id, subject_name, mastery_score, total_attempted, total_correct)
        VALUES (?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            total_attempted = total_attempted + 1,
            total_correct   = total_correct + VALUES(total_correct),
            mastery_score   = ROUND((total_correct / total_attempted) * 100, 2),
            last_updated    = NOW()
    ")->execute([$uid, $subject, $isCorrect ? 100 : 0, $isCorrect]);

    echo json_encode(['ok'=>true]);
} catch(Exception $e) {
    echo json_encode(['ok'=>false, 'err'=>$e->getMessage()]);
}
