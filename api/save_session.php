<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorised']); exit; }

if (!isLoggedIn()) { echo json_encode(['ok'=>false]); exit; }

$data      = json_decode(file_get_contents('php://input'), true);
$uid       = $_SESSION['user_id'];
$subject   = trim($data['subject'] ?? '');
$correct   = intval($data['correct'] ?? 0);
$totalQ    = intval($data['total_q'] ?? 0);
$scorePct  = floatval($data['score_pct'] ?? 0);
$category  = strtolower(trim($data['category'] ?? ''));
$year      = trim($data['year'] ?? '');

if (!$subject || !$totalQ) { echo json_encode(['ok'=>false]); exit; }

try {
    $db = getDB();

    // Save session record
    $db->prepare("
        INSERT INTO user_sessions (user_id, subject_name, category, year, total_q, correct, score_pct)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([$uid, $subject, $category, $year, $totalQ, $correct, $scorePct]);

    echo json_encode(['ok'=>true]);
} catch(Exception $e) {
    echo json_encode(['ok'=>false, 'err'=>$e->getMessage()]);
}
