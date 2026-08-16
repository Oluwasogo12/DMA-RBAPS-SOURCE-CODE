<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// ── Admin guard ───────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db  = getDB();
$uid = $_SESSION['user_id'];
$uname = $_SESSION['username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    // Create settings table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $hide_2024 = isset($_POST['hide_2024']) ? '1' : '0';
    $only_2024 = isset($_POST['only_2024']) ? '1' : '0';
    
    // Mutual exclusivity
    if ($only_2024 === '1') {
        $hide_2024 = '0';
    }
    
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $stmt->execute(['hide_2024', $hide_2024]);
    $stmt->execute(['only_2024', $only_2024]);
    
    header("Location: dashboard.php#settings");
    exit;
}

// Ensure is_admin column exists
try { $db->query("SELECT is_admin FROM users LIMIT 1"); }
catch (Exception $e) { $db->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0"); }

// ── Auto-create app tables if they don't exist yet ────────────────────────
// This prevents a blank/blue page when setup.php hasn't been run
try {
    $db->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject_name VARCHAR(60),
        category VARCHAR(8),
        year VARCHAR(8),
        total_q INT DEFAULT 0,
        correct INT DEFAULT 0,
        score_pct DECIMAL(5,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS user_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id INT,
        question_id INT NOT NULL,
        chosen VARCHAR(2),
        is_correct TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS user_topic_performance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject_name VARCHAR(60),
        topic VARCHAR(255),
        mastery_score DECIMAL(5,2) DEFAULT 0,
        total_attempted INT DEFAULT 0,
        total_correct INT DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_topic (user_id, subject_name, topic)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$adminCheck = $db->prepare("SELECT is_admin FROM users WHERE id=?");
$adminCheck->execute([$uid]);
$adminRow = $adminCheck->fetch();

if (!$adminRow || !$adminRow['is_admin']) {
    http_response_code(403);
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;background:#0d0f14;color:#e8eaf0;"><h2>403 — Access Denied</h2><p style="color:#8b90a8">Your account does not have admin privileges.</p><br><a href="index.php" style="color:#7c89ff">← Admin Login</a> &nbsp; <a href="../dashboard.php" style="color:#8b90a8">Student Dashboard</a></body></html>');
}

// ── Summary stats ────────────────────────────────────────────────────────
// Every query is wrapped in try/catch so a missing table never blanks the page
$totalUsers = $activeToday = $activeWeek = $totalSessions = $totalAnswers = $avgScore = $totalQs = 0;
try { $totalUsers    = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch(Exception $e){}
try { $activeToday   = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE DATE(created_at) = CURDATE()")->fetchColumn(); } catch(Exception $e){}
try { $activeWeek    = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(); } catch(Exception $e){}
try { $totalSessions = (int)$db->query("SELECT COUNT(*) FROM user_sessions")->fetchColumn(); } catch(Exception $e){}
try { $totalAnswers  = (int)$db->query("SELECT COUNT(*) FROM user_answers")->fetchColumn(); } catch(Exception $e){}
try { $avgScore      = round((float)($db->query("SELECT AVG(score_pct) FROM user_sessions WHERE total_q > 0")->fetchColumn() ?? 0), 1); } catch(Exception $e){}
try { $totalQs       = (int)$db->query("SELECT COUNT(*) FROM questions")->fetchColumn(); } catch(Exception $e){}

// ── Daily sessions for last 14 days ──────────────────────────────────────
$dailySessions = [];
try { $dailySessions = $db->query("
    SELECT DATE(created_at) as day, COUNT(*) as cnt, AVG(score_pct) as avg_score
    FROM user_sessions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// ── Subject popularity ────────────────────────────────────────────────────
$subjectPop = [];
try { $subjectPop = $db->query("
    SELECT subject_name, COUNT(*) as sessions, AVG(score_pct) as avg_score, SUM(total_q) as questions
    FROM user_sessions
    GROUP BY subject_name
    ORDER BY sessions DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// ── All users with activity ───────────────────────────────────────────────
$users = [];
try { $users = $db->query("
    SELECT u.id, u.username, u.email, u.exam_target, u.created_at,
           COUNT(DISTINCT s.id) as sessions,
           COALESCE(SUM(s.total_q),0) as questions,
           COALESCE(AVG(s.score_pct),0) as avg_score,
           MAX(s.created_at) as last_active
    FROM users u
    LEFT JOIN user_sessions s ON s.user_id = u.id
    GROUP BY u.id
    ORDER BY last_active DESC
")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// ── Top performing users ──────────────────────────────────────────────────
$topUsers = [];
try { $topUsers = $db->query("
    SELECT u.username, AVG(s.score_pct) as avg_score, COUNT(s.id) as sessions
    FROM users u
    JOIN user_sessions s ON s.user_id = u.id
    GROUP BY u.id
    HAVING sessions >= 3
    ORDER BY avg_score DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// ── Recent sessions (audit log) ────────────────────────────────────────────
$recentSessions = [];
try { $recentSessions = $db->query("
    SELECT u.username, s.subject_name, s.category, s.year, s.total_q,
           s.correct, s.score_pct, s.created_at
    FROM user_sessions s
    JOIN users u ON u.id = s.user_id
    ORDER BY s.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// ── Struggling users (avg score < 40, >= 3 sessions) ─────────────────────
$struggling = [];
try { $struggling = $db->query("
    SELECT u.username, u.email, AVG(s.score_pct) as avg_score,
           COUNT(s.id) as sessions, MAX(s.created_at) as last_active
    FROM users u
    JOIN user_sessions s ON s.user_id = u.id
    GROUP BY u.id
    HAVING sessions >= 3 AND avg_score < 40
    ORDER BY avg_score ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// ── Registrations per day (last 30 days) ──────────────────────────────────
$regTrend = [];
try { $regTrend = $db->query("
    SELECT DATE(created_at) as day, COUNT(*) as cnt
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// ── New users this week ────────────────────────────────────────────────────
$newUsersWeek = 0;
try { $newUsersWeek = (int)$db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(); } catch(Exception $e){}

// ── Category breakdown ─────────────────────────────────────────────────────
$catBreakdown = [];
try { $catBreakdown = $db->query("
    SELECT UPPER(category) as cat, COUNT(*) as cnt
    FROM user_sessions
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category
")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// ── Final Year Project Evaluation Metrics ──────────────────────────────────
$evalMetrics = ['mode_comparison' => [], 'avg_mastery' => ['overall_mastery'=>0, 'topics_tracked'=>0], 'engagement' => 0];
try {
    $evalMetrics['mode_comparison'] = $db->query("
        SELECT 
            CASE 
                WHEN category = 'adaptive' OR category IS NULL OR category = '' THEN 'Adaptive Mode'
                ELSE 'Year-Based Mode'
            END AS mode,
            COUNT(*) as sessions,
            AVG(score_pct) as avg_score,
            SUM(total_q) as total_questions
        FROM user_sessions
        GROUP BY mode
    ")->fetchAll(PDO::FETCH_ASSOC);

    $evalMetrics['avg_mastery'] = $db->query("
        SELECT COALESCE(AVG(mastery_score), 0) as overall_mastery, COUNT(*) as topics_tracked
        FROM user_topic_performance
    ")->fetch(PDO::FETCH_ASSOC);

    $evalMetrics['engagement'] = $db->query("
        SELECT COALESCE(AVG(session_count), 0) as avg_sessions_per_user 
        FROM (SELECT user_id, COUNT(*) as session_count FROM user_sessions GROUP BY user_id) as sub
    ")->fetchColumn();
} catch(Exception $e){}

$systemEvals = [];
$avgUsability = 0;
$avgAccuracy = 0;
$evalAvgs = [
    'learning_effectiveness' => 0, 'system_speed' => 0, 'personalized_learning' => 0,
    'ui_design' => 0, 'recommendation_accuracy' => 0, 'overall_satisfaction' => 0,
    'future_usage' => 0, 'system_reliability' => 0,
];
try {
    $db->exec("CREATE TABLE IF NOT EXISTS system_evaluations (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, usability_score INT NOT NULL, adaptive_accuracy INT NOT NULL, feedback TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_eval (user_id)
    )");
    // Add new columns if missing
    $addCols = [
        'learning_effectiveness' => 'INT DEFAULT NULL',
        'system_speed'           => 'INT DEFAULT NULL',
        'personalized_learning'  => 'INT DEFAULT NULL',
        'ui_design'              => 'INT DEFAULT NULL',
        'recommendation_accuracy'=> 'INT DEFAULT NULL',
        'overall_satisfaction'   => 'INT DEFAULT NULL',
        'future_usage'           => 'INT DEFAULT NULL',
        'system_reliability'     => 'INT DEFAULT NULL',
        'liked_most'             => 'TEXT DEFAULT NULL',
        'suggestions'            => 'TEXT DEFAULT NULL',
    ];
    foreach ($addCols as $col => $type) {
        try { $db->exec("ALTER TABLE system_evaluations ADD COLUMN $col $type"); } catch(Exception $ex) {}
    }
    $systemEvals = $db->query("SELECT e.*, u.username FROM system_evaluations e JOIN users u ON e.user_id = u.id ORDER BY e.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    if(count($systemEvals) > 0) {
        $cnt = count($systemEvals);
        $avgUsability = array_sum(array_column($systemEvals, 'usability_score')) / $cnt;
        $avgAccuracy = array_sum(array_column($systemEvals, 'adaptive_accuracy')) / $cnt;
        foreach (array_keys($evalAvgs) as $key) {
            $vals = array_filter(array_column($systemEvals, $key), fn($v) => $v !== null && $v !== '');
            $evalAvgs[$key] = count($vals) > 0 ? array_sum($vals) / count($vals) : 0;
        }
    }
} catch(Exception $e){}

// Encode data for JS charts
$dailyLabels   = json_encode(array_map(fn($r) => date('d M', strtotime($r['day'])), $dailySessions));
$dailyCounts   = json_encode(array_map(fn($r) => (int)$r['cnt'], $dailySessions));
$dailyAvgScore = json_encode(array_map(fn($r) => round($r['avg_score'],1), $dailySessions));

$subjLabels = json_encode(array_map(fn($r) => $r['subject_name'], $subjectPop));
$subjCounts = json_encode(array_map(fn($r) => (int)$r['sessions'], $subjectPop));
$subjScores = json_encode(array_map(fn($r) => round($r['avg_score'],1), $subjectPop));

$regLabels = json_encode(array_map(fn($r) => date('d M', strtotime($r['day'])), $regTrend));
$regCounts = json_encode(array_map(fn($r) => (int)$r['cnt'], $regTrend));

$catLabels = json_encode(array_map(fn($r) => $r['cat'], $catBreakdown));
$catCounts = json_encode(array_map(fn($r) => (int)$r['cnt'], $catBreakdown));

// ── User Report: per-user sessions (for the User Reports section) ──────────
// We load ALL sessions (not just 50) for the report + print view
$allSessions = [];
try { $allSessions = $db->query("
    SELECT u.id as uid, u.username, u.email, u.exam_target, u.created_at as joined,
           s.id as sid, s.subject_name, s.category, s.year, s.total_q,
           s.correct, s.score_pct, s.created_at as sess_date
    FROM user_sessions s
    JOIN users u ON u.id = s.user_id
    ORDER BY u.username ASC, s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

// Group sessions by user_id for JS
$sessionsByUser = [];
foreach ($allSessions as $row) {
    $sessionsByUser[$row['uid']][] = $row;
}

// ── Topic Mastery: all users × topics ────────────────────────────────────
$topicMasteryAll = [];
try {
    $topicMasteryAll = $db->query("
        SELECT u.id as uid, u.username, utp.subject_name, utp.topic,
               utp.mastery_score, utp.total_attempted, utp.total_correct,
               utp.last_updated
        FROM user_topic_performance utp
        JOIN users u ON u.id = utp.user_id
        ORDER BY u.username ASC, utp.subject_name ASC, utp.mastery_score ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Group topic mastery by user for JS
$topicsByUser = [];
foreach ($topicMasteryAll as $row) {
    $topicsByUser[$row['uid']][] = $row;
}

// Subjects each user has taken (distinct)
$subjectsByUser = [];
try {
    $subjectRows = $db->query("
        SELECT DISTINCT user_id, subject_name FROM user_sessions ORDER BY subject_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($subjectRows as $r) {
        $subjectsByUser[$r['user_id']][] = $r['subject_name'];
    }
} catch (Exception $e) {}

$sessionsByUserJson = json_encode($sessionsByUser, JSON_HEX_TAG);
$topicsByUserJson   = json_encode($topicsByUser,   JSON_HEX_TAG);
$subjectsByUserJson = json_encode($subjectsByUser,  JSON_HEX_TAG);

$sessionsByUser2024 = [];
$usersWith2024 = [];
foreach ($allSessions as $row) {
    if ($row['year'] === '2024') {
        $sessionsByUser2024[$row['uid']][] = $row;
        if (!isset($usersWith2024[$row['uid']])) {
            $usersWith2024[$row['uid']] = [
                'id' => $row['uid'],
                'username' => $row['username'],
                'email' => $row['email'],
                'last_active' => $row['sess_date'],
                'avg_score' => 0,
                'sessions' => 0
            ];
        } else {
            if ($row['sess_date'] > $usersWith2024[$row['uid']]['last_active']) {
                $usersWith2024[$row['uid']]['last_active'] = $row['sess_date'];
            }
        }
    }
}
foreach ($usersWith2024 as $uid => &$u24) {
    $sum = 0;
    foreach ($sessionsByUser2024[$uid] as $s) { $sum += $s['score_pct']; }
    $u24['avg_score'] = count($sessionsByUser2024[$uid]) ? ($sum / count($sessionsByUser2024[$uid])) : 0;
    $u24['sessions'] = count($sessionsByUser2024[$uid]);
}
unset($u24);
$sessionsByUser2024Json = json_encode($sessionsByUser2024, JSON_HEX_TAG);

// ── 2024 answer-level data with topics ────────────────────────────────────
$answers2024 = [];
try {
    $answers2024 = $db->query("
        SELECT ua.user_id, ua.question_id, ua.is_correct, ua.chosen,
               q.question, q.correct_option, q.option_a, q.option_b, q.option_c, q.option_d,
               sn.name AS subject_name, sy.category
        FROM user_answers ua
        JOIN questions q ON q.id = ua.question_id
        JOIN subjectyear sy ON sy.id = q.subjectyear_id
        JOIN subjectname sn ON sn.id = sy.subjectnamrid
        WHERE sy.year = '2024'
        ORDER BY ua.user_id, sn.name, ua.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Build topic map for 2024 question IDs
$qIds2024 = array_values(array_unique(array_column($answers2024, 'question_id')));
$topicMap2024 = [];
if ($qIds2024) {
    $topicTables2024 = [
        'mapping_chemistry'       => 'new',
        'mapping_physics'         => 'new',
        'mapping_mathematics'     => 'new',
        'mapping_biology'         => 'new',
        'mapping_english'         => 'new',
        'mapping_civic'           => 'new',
        'mapping_economics'       => 'new',
        'mapping_government'      => 'new', // Updated to modern format
        'mapping_history'         => 'new', // Updated to modern format
        'mapping_ict'             => 'new', // Updated to modern format
        'mapping_geography'       => 'new', // New mapping table
        'mapping_commerce'        => 'new', // New mapping table
        'mapping_financial_accounting' => 'new',
    ];
    $placeholders2024 = implode(',', array_fill(0, count($qIds2024), '?'));
    foreach ($topicTables2024 as $tbl => $style) {
        try {
            $topicCol = ($style === 'new')
                ? "COALESCE(NULLIF(TRIM(best_topic_name),''), 'General')"
                : "COALESCE(NULLIF(TRIM(best_subtopic),''), NULLIF(TRIM(best_topic),''), 'General')";
            $tStmt = $db->prepare("SELECT question_id, $topicCol AS topic FROM `$tbl` WHERE question_id IN ($placeholders2024)");
            $tStmt->execute($qIds2024);
            foreach ($tStmt->fetchAll() as $r) {
                if (!isset($topicMap2024[$r['question_id']])) {
                    $topicMap2024[$r['question_id']] = $r['topic'];
                }
            }
        } catch(Exception $e) {}
    }
}

// Group answers by user and attach topic
$answersByUser2024 = [];
foreach ($answers2024 as $a) {
    $a['topic'] = $topicMap2024[$a['question_id']] ?? $a['subject_name'];
    $answersByUser2024[$a['user_id']][] = $a;
}
$answersByUser2024Json = json_encode($answersByUser2024, JSON_HEX_TAG);

$sysSettings = getSystemSettings();
$hide2024 = $sysSettings['hide_2024'] ?? '0';
$only2024 = $sysSettings['only_2024'] ?? '0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — RBAPS</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #0d0f14;
    --bg2:      #141720;
    --bg3:      #1c2030;
    --bg4:      #232739;
    --border:   rgba(255,255,255,0.07);
    --border2:  rgba(255,255,255,0.13);
    --text:     #e8eaf0;
    --text2:    #8b90a8;
    --text3:    #555c78;
    --accent:   #5b6af5;
    --accent2:  #7c89ff;
    --green:    #22c55e;
    --red:      #ef4444;
    --amber:    #f59e0b;
    --teal:     #14b8a6;
    --purple:   #a855f7;
    --cyan:     #06b6d4;
    --sidebar-w: 240px;
    --topbar-h:  60px;
  }

  body {
    font-family: 'Space Grotesk', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    font-size: 14px;
    line-height: 1.6;
  }

  /* ── Layout ── */
  .layout { display: flex; min-height: 100vh; }

  /* ── Sidebar ── */
  .sidebar {
    width: var(--sidebar-w);
    background: var(--bg2);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
    overflow-y: auto;
  }

  .sidebar-logo {
    padding: 1.25rem 1.25rem 1rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .sidebar-logo .logo-icon {
    width: 32px; height: 32px;
    background: var(--accent);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
    color: #fff;
    font-weight: 700;
    flex-shrink: 0;
  }

  .sidebar-logo .logo-text {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -0.02em;
  }

  .sidebar-logo .logo-sub {
    font-size: 10px;
    color: var(--text3);
    font-family: 'JetBrains Mono', monospace;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-top: -2px;
  }

  .sidebar-section {
    padding: .75rem 0 .25rem;
  }

  .sidebar-section-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text3);
    padding: 0 1.25rem .5rem;
    font-weight: 600;
  }

  .sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: .55rem 1.25rem;
    color: var(--text2);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    transition: all .15s;
    border-left: 2px solid transparent;
  }

  .sidebar-nav a:hover,
  .sidebar-nav a.active {
    background: var(--bg3);
    color: var(--text);
    border-left-color: var(--accent);
  }

  .sidebar-nav a i { width: 16px; text-align: center; font-size: 13px; }

  .sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid var(--border);
    padding: 1rem 1.25rem;
  }

  .admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(91,106,245,0.15);
    color: var(--accent2);
    border: 1px solid rgba(91,106,245,0.3);
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: .75rem;
  }

  /* ── Main ── */
  .main {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }

  .topbar {
    height: var(--topbar-h);
    border-bottom: 1px solid var(--border);
    background: var(--bg2);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    position: sticky;
    top: 0;
    z-index: 50;
  }

  .topbar-title { font-size: 16px; font-weight: 700; color: var(--text); }
  .topbar-sub   { font-size: 12px; color: var(--text3); margin-top: 1px; }

  .topbar-right {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .live-dot {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--green);
  }

  .live-dot::before {
    content: '';
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--green);
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0%,100%  { opacity: 1; }
    50%      { opacity: .4; }
  }

  .content { padding: 2rem; max-width: 1400px; }

  /* ── Section titles ── */
  .section-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text3);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }

  /* ── Stat cards ── */
  .stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 2rem;
  }

  .stat-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.25rem 1.25rem 1rem;
    position: relative;
    overflow: hidden;
    transition: border-color .2s;
  }

  .stat-card:hover { border-color: var(--border2); }

  .stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: var(--card-color, var(--accent));
  }

  .stat-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    margin-bottom: 1rem;
    background: color-mix(in srgb, var(--card-color, var(--accent)) 15%, transparent);
    color: var(--card-color, var(--accent));
  }

  .stat-val {
    font-size: 26px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 4px;
    color: var(--text);
    font-variant-numeric: tabular-nums;
  }

  .stat-lbl {
    font-size: 12px;
    color: var(--text3);
    font-weight: 500;
  }

  .stat-change {
    font-size: 11px;
    margin-top: 6px;
    color: var(--text3);
  }

  .stat-change.up   { color: var(--green); }
  .stat-change.down { color: var(--red); }

  /* ── Chart cards ── */
  .chart-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.25rem;
    margin-bottom: 2rem;
  }

  .chart-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 2rem;
  }

  .card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
  }

  .card-title {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--text);
  }

  .card-sub {
    font-size: 11.5px;
    color: var(--text3);
    margin-top: 2px;
  }

  /* ── Tables ── */
  .table-wrap {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--border);
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }

  thead th {
    background: var(--bg3);
    color: var(--text3);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    padding: .6rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }

  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
  }

  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover      { background: var(--bg3); }

  tbody td {
    padding: .65rem 1rem;
    color: var(--text2);
    vertical-align: middle;
  }

  tbody td:first-child { color: var(--text); font-weight: 600; }

  /* ── User table ── */
  .user-name {
    display: flex;
    align-items: center;
    gap: 9px;
  }

  .avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: var(--bg4);
    border: 1px solid var(--border2);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: var(--accent2);
    flex-shrink: 0;
    text-transform: uppercase;
  }

  /* ── Badges ── */
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
  }

  .badge-green   { background: rgba(34,197,94,.15);  color: var(--green); }
  .badge-red     { background: rgba(239,68,68,.15);  color: var(--red); }
  .badge-amber   { background: rgba(245,158,11,.15); color: var(--amber); }
  .badge-blue    { background: rgba(91,106,245,.15); color: var(--accent2); }
  .badge-gray    { background: rgba(255,255,255,.05); color: var(--text3); }
  .badge-teal    { background: rgba(20,184,166,.15); color: var(--teal); }
  .badge-purple  { background: rgba(168,85,247,.15); color: var(--purple); }

  /* ── Score bar ── */
  .score-bar-wrap { width: 100%; background: var(--bg4); border-radius: 4px; height: 6px; }
  .score-bar-fill { height: 6px; border-radius: 4px; transition: width .4s; }

  /* ── Tab system ── */
  .tabs {
    display: flex;
    gap: 4px;
    padding: 4px;
    background: var(--bg3);
    border-radius: 10px;
    margin-bottom: 1.5rem;
    width: fit-content;
  }

  .tab {
    padding: 6px 16px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text3);
    cursor: pointer;
    transition: all .15s;
    border: none;
    background: none;
  }

  .tab.active {
    background: var(--bg2);
    color: var(--text);
    box-shadow: 0 1px 3px rgba(0,0,0,.3);
  }

  .tab-panel { display: none; }
  .tab-panel.active { display: block; }

  /* ── Scrollable panel ── */
  .scroll-panel {
    max-height: 380px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--bg4) transparent;
  }

  /* ── Mini legend ── */
  .mini-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 11.5px;
    color: var(--text2);
  }

  .mini-legend span {
    display: flex;
    align-items: center;
    gap: 5px;
  }

  .legend-dot {
    width: 10px; height: 10px;
    border-radius: 2px;
    flex-shrink: 0;
  }

  /* ── Struggling users panel ── */
  .alert-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .75rem 0;
    border-bottom: 1px solid var(--border);
  }

  .alert-row:last-child { border-bottom: none; }

  /* ── Responsive ── */
  @media (max-width: 1100px) {
    .chart-grid   { grid-template-columns: 1fr; }
    .chart-grid-2 { grid-template-columns: 1fr; }
  }

  @media (max-width: 800px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .sidebar   { transform: translateX(-100%); transition: transform .25s ease; }
    .sidebar.open { transform: translateX(0); }
    .main      { margin-left: 0; }
    .sidebar-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 99;
    }
    .sidebar-overlay.open { display: block; }
    .mob-menu-btn {
      display: flex;
      align-items: center; justify-content: center;
      width: 36px; height: 36px;
      background: var(--bg3); border: 1px solid var(--border);
      border-radius: 8px; color: var(--text2);
      cursor: pointer; font-size: 15px;
      flex-shrink: 0;
    }
  }
  @media (min-width: 801px) {
    .mob-menu-btn { display: none; }
  }

  @media (max-width: 560px) {
    .stat-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .topbar { padding: 0 .9rem; gap: .5rem; }
    .topbar-title { font-size: 14px; }
    .topbar-sub { font-size: 10px; }
    .content { padding: 1rem .9rem; }
    .section-header { flex-direction: column; align-items: flex-start; gap: .5rem; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .search-input { width: 100%; }
  }

  /* ── Filter bar ── */
  .filter-bar {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
  }

  .search-input {
    background: var(--bg3);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 8px;
    padding: 7px 12px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    width: 240px;
    transition: border-color .15s;
  }

  .search-input:focus { border-color: var(--accent); }
  .search-input::placeholder { color: var(--text3); }

  .filter-select {
    background: var(--bg3);
    border: 1px solid var(--border);
    color: var(--text2);
    border-radius: 8px;
    padding: 7px 28px 7px 10px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23555c78' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
  }

  /* ── Chart placeholder ── */
  .chart-box { position: relative; }
  canvas { display: block; }

  /* ── Print button ── */
  .btn-print {
    display:inline-flex;align-items:center;gap:6px;
    padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;
    background:var(--accent);color:#fff;border:none;cursor:pointer;
    transition:opacity .15s;
  }
  .btn-print:hover { opacity:.85; }

  /* ── Report user row hover ── */
  .report-user-row:hover { background:rgba(255,255,255,0.04); }
  .report-user-row.selected { background:rgba(91,106,245,0.12); border-left:3px solid var(--accent); }

  /* ── Badge colours needed for topic table ── */
  .badge-green { background:rgba(34,197,94,0.15);color:#22c55e; }
  .badge-amber { background:rgba(245,158,11,0.15);color:#f59e0b; }
  .badge-red   { background:rgba(239,68,68,0.15);color:#ef4444; }

  /* ── Print styles ── */
  @media print {
    body { background:#fff !important; color:#111 !important; font-size:12px; }
    .sidebar, .topbar, .btn-print, #reportUserList, #reportSearch,
    .report-user-row, button, a[href] { display:none !important; }
    .main { margin-left:0 !important; }
    .content { padding:0 !important; }
    .tab-panel { display:block !important; }
    #section-reports { display:block !important; }
    #reportPanel { display:block !important; }
    .print-header { display:block !important; }
    * { box-shadow:none !important; }
    table { border-collapse:collapse; width:100%; }
    th, td { border:1px solid #ccc; padding:6px 8px; color:#111 !important; background:#fff !important; }
    th { background:#f0f0f0 !important; font-weight:700; }
    .print-section-title { font-size:14px; font-weight:700; margin:12px 0 6px; color:#111 !important; }
    .score-bar-wrap, .score-bar-fill { display:none !important; }
  }
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
<div class="layout">

  <!-- ── Sidebar ── -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
      <div>
        <div class="logo-text">RBAPS</div>
        <div class="logo-sub">Admin Panel</div>
      </div>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-label">Overview</div>
      <nav class="sidebar-nav">
        <a href="#overview"   class="active" onclick="showSection('overview', this)">
          <i class="fa-solid fa-chart-pie"></i> Overview
        </a>
        <a href="#users"    onclick="showSection('users', this)">
          <i class="fa-solid fa-users"></i> All Users
        </a>
        <a href="#activity" onclick="showSection('activity', this)">
          <i class="fa-solid fa-clock-rotate-left"></i> Activity Log
        </a>
        <a href="#subjects" onclick="showSection('subjects', this)">
          <i class="fa-solid fa-book"></i> Subject Analytics
        </a>
        <a href="#alerts"   onclick="showSection('alerts', this)">
          <i class="fa-solid fa-triangle-exclamation"></i> At-Risk Users
          <?php if(count($struggling) > 0): ?>
          <span class="badge badge-red" style="margin-left:auto;padding:1px 6px;"><?= count($struggling) ?></span>
          <?php endif; ?>
        </a>
        <a href="#reports"  onclick="showSection('reports', this)">
          <i class="fa-solid fa-file-lines"></i> User Reports
        </a>
        <a href="#reports2024" onclick="showSection('reports2024', this)">
          <i class="fa-solid fa-file-pdf"></i> 2024 Results
        </a>
        <a href="#topics"   onclick="showSection('topics', this)">
          <i class="fa-solid fa-brain"></i> Topic Mastery
        </a>
        <a href="#evaluation" onclick="showSection('evaluation', this)">
          <i class="fa-solid fa-flask"></i> System Evaluation
        </a>
      </nav>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-label">App</div>
      <nav class="sidebar-nav">
        <a href="#settings" onclick="showSection('settings', this)">
          <i class="fa-solid fa-sliders"></i> Global Settings
        </a>
        <a href="../dashboard.php">
          <i class="fa-solid fa-arrow-left"></i> Student View
        </a>
        <a href="../setup.php">
          <i class="fa-solid fa-gear"></i> DB Setup
        </a>
        <a href="../logout.php">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
      </nav>
    </div>

    <div class="sidebar-bottom">
      <div class="admin-badge"><i class="fa-solid fa-shield-halved" style="font-size:10px"></i> Admin</div>
      <div style="font-size:12.5px;font-weight:600;color:var(--text)"><?= htmlspecialchars($uname) ?></div>
      <div style="font-size:11px;color:var(--text3);margin-top:2px">Signed in</div>
    </div>
  </aside>

  <!-- ── Main ── -->
  <div class="main">

    <!-- Topbar -->
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:.75rem">
        <button class="mob-menu-btn" id="mob-menu-btn" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
        <div>
        <div class="topbar-title" id="topbar-title">Platform Overview</div>
        <div class="topbar-sub" id="topbar-sub"><?= date('l, d F Y') ?> · Real-time data</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="live-dot">Live</div>
        <a href="../logout.php" style="font-size:12px;color:var(--text3);text-decoration:none;display:flex;align-items:center;gap:5px">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
      </div>
    </div>

    <div class="content">

      <!-- ═══════════════════════════════════════ OVERVIEW ══ -->
      <div id="section-overview" class="tab-panel active">

        <div class="stat-grid">
          <div class="stat-card" style="--card-color:var(--accent)">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-val"><?= number_format($totalUsers) ?></div>
            <div class="stat-lbl">Total Users</div>
            <div class="stat-change up"><i class="fa-solid fa-arrow-up" style="font-size:9px"></i> <?= $newUsersWeek ?> this week</div>
          </div>
          <div class="stat-card" style="--card-color:var(--green)">
            <div class="stat-icon"><i class="fa-solid fa-circle-dot"></i></div>
            <div class="stat-val"><?= number_format($activeToday) ?></div>
            <div class="stat-lbl">Active Today</div>
            <div class="stat-change"><?= $activeWeek ?> this week</div>
          </div>
          <div class="stat-card" style="--card-color:var(--teal)">
            <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div class="stat-val"><?= number_format($totalSessions) ?></div>
            <div class="stat-lbl">Total Sessions</div>
          </div>
          <div class="stat-card" style="--card-color:var(--amber)">
            <div class="stat-icon"><i class="fa-solid fa-circle-question"></i></div>
            <div class="stat-val"><?= number_format($totalAnswers) ?></div>
            <div class="stat-lbl">Answers Submitted</div>
          </div>
          <div class="stat-card" style="--card-color:var(--purple)">
            <div class="stat-icon"><i class="fa-solid fa-bullseye"></i></div>
            <div class="stat-val"><?= $avgScore ?>%</div>
            <div class="stat-lbl">Platform Avg Score</div>
            <div class="stat-change <?= $avgScore >= 50 ? 'up' : 'down' ?>"><?= $avgScore >= 50 ? 'Healthy' : 'Needs attention' ?></div>
          </div>
          <div class="stat-card" style="--card-color:var(--cyan)">
            <div class="stat-icon"><i class="fa-solid fa-database"></i></div>
            <div class="stat-val"><?= number_format($totalQs) ?></div>
            <div class="stat-lbl">Questions in DB</div>
          </div>
        </div>

        <!-- Charts row 1 -->
        <div class="chart-grid">
          <div class="card">
            <div class="card-header">
              <div>
                <div class="card-title">Daily Sessions — Last 14 Days</div>
                <div class="card-sub">Number of practice sessions started each day</div>
              </div>
            </div>
            <div class="mini-legend">
              <span><span class="legend-dot" style="background:#5b6af5"></span>Sessions</span>
              <span><span class="legend-dot" style="background:#22c55e"></span>Avg Score %</span>
            </div>
            <div class="chart-box" style="height:240px">
              <canvas id="dailyChart" role="img" aria-label="Daily sessions and average score over the last 14 days"></canvas>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <div>
                <div class="card-title">Session Category</div>
                <div class="card-sub">WAEC vs JAMB vs Adaptive</div>
              </div>
            </div>
            <div class="chart-box" style="height:200px;display:flex;align-items:center;justify-content:center">
              <canvas id="catChart" role="img" aria-label="Pie chart of session categories"></canvas>
            </div>
            <div class="mini-legend" id="catLegend" style="margin-top:12px"></div>
          </div>
        </div>

        <!-- Charts row 2 -->
        <div class="chart-grid-2">
          <div class="card">
            <div class="card-header">
              <div>
                <div class="card-title">User Registrations</div>
                <div class="card-sub">Last 30 days</div>
              </div>
            </div>
            <div class="chart-box" style="height:200px">
              <canvas id="regChart" role="img" aria-label="User registrations over last 30 days"></canvas>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <div>
                <div class="card-title">Top Performers</div>
                <div class="card-sub">Users with ≥3 sessions, ranked by avg score</div>
              </div>
            </div>
            <div class="scroll-panel" style="max-height:200px">
              <?php if($topUsers): foreach($topUsers as $i => $u):
                $medal = ['🥇','🥈','🥉'][$i] ?? '';
                $pct = round($u['avg_score'],1);
                $color = $pct >= 70 ? 'var(--green)' : ($pct >= 50 ? 'var(--amber)' : 'var(--red)');
              ?>
              <div style="display:flex;align-items:center;gap:10px;padding:.55rem 0;border-bottom:1px solid var(--border)">
                <span style="font-size:14px;width:22px"><?= $medal ?></span>
                <div class="avatar"><?= strtoupper(substr($u['username'],0,2)) ?></div>
                <div style="flex:1">
                  <div style="font-size:13px;font-weight:600;color:var(--text)"><?= htmlspecialchars($u['username']) ?></div>
                  <div style="font-size:11px;color:var(--text3)"><?= $u['sessions'] ?> sessions</div>
                </div>
                <span style="font-weight:700;font-size:14px;color:<?= $color ?>"><?= $pct ?>%</span>
              </div>
              <?php endforeach; else: ?>
              <div style="text-align:center;padding:2rem;color:var(--text3)">No data yet</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div><!-- /overview -->


      <!-- ═══════════════════════════════════════ USERS ══ -->
      <div id="section-users" class="tab-panel">

        <div class="filter-bar">
          <input type="text" class="search-input" id="userSearch" placeholder="Search username or email…" oninput="filterUsers()">
          <select class="filter-select" id="userSortBy" onchange="filterUsers()">
            <option value="last_active">Sort: Last Active</option>
            <option value="sessions">Sort: Most Sessions</option>
            <option value="avg_score">Sort: Avg Score</option>
            <option value="questions">Sort: Questions</option>
            <option value="created">Sort: Newest First</option>
          </select>
          <span id="userCount" style="font-size:12px;color:var(--text3);margin-left:4px"></span>
        </div>

        <div class="table-wrap">
          <table id="userTable">
            <thead>
              <tr>
                <th>User</th>
                <th>Email</th>
                <th>Exam Target</th>
                <th>Sessions</th>
                <th>Questions</th>
                <th>Avg Score</th>
                <th>Progress</th>
                <th>Last Active</th>
                <th>Joined</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($users as $u):
                $avg = round($u['avg_score'],1);
                $color = $avg >= 70 ? 'var(--green)' : ($avg >= 50 ? 'var(--amber)' : 'var(--red)');
                $la = $u['last_active'] ? date('d M Y', strtotime($u['last_active'])) : 'Never';
                $joined = date('d M Y', strtotime($u['created_at']));
                $initials = strtoupper(substr($u['username'],0,2));
              ?>
              <tr data-username="<?= htmlspecialchars($u['username']) ?>"
                  data-email="<?= htmlspecialchars($u['email']) ?>"
                  data-sessions="<?= $u['sessions'] ?>"
                  data-questions="<?= $u['questions'] ?>"
                  data-avg="<?= $avg ?>"
                  data-last="<?= $u['last_active'] ?? '0' ?>"
                  data-created="<?= $u['created_at'] ?>">
                <td>
                  <div class="user-name">
                    <div class="avatar"><?= $initials ?></div>
                    <div>
                      <div style="font-size:13px;font-weight:600;color:var(--text)"><?= htmlspecialchars($u['username']) ?></div>
                      <div style="font-size:11px;color:var(--text3)">ID <?= $u['id'] ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-size:12px;font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge badge-blue"><?= strtoupper($u['exam_target'] ?? 'BOTH') ?></span></td>
                <td><?= number_format($u['sessions']) ?></td>
                <td><?= number_format($u['questions']) ?></td>
                <td style="color:<?= $color ?>;font-weight:700"><?= $avg ?>%</td>
                <td style="min-width:100px">
                  <div class="score-bar-wrap">
                    <div class="score-bar-fill" style="width:<?= min(100,$avg) ?>%;background:<?= $color ?>"></div>
                  </div>
                </td>
                <td style="color:var(--text3);font-size:12px"><?= $la ?></td>
                <td style="color:var(--text3);font-size:12px"><?= $joined ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /users -->


      <!-- ═══════════════════════════════════════ ACTIVITY LOG ══ -->
      <div id="section-activity" class="tab-panel">

        <div class="filter-bar">
          <input type="text" class="search-input" id="actSearch" placeholder="Search user or subject…" oninput="filterActivity()">
          <select class="filter-select" id="actSubject" onchange="filterActivity()">
            <option value="">All Subjects</option>
            <?php foreach($subjectPop as $s): ?>
            <option value="<?= htmlspecialchars($s['subject_name']) ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <span id="actCount" style="font-size:12px;color:var(--text3);margin-left:4px"></span>
        </div>

        <div class="table-wrap">
          <table id="actTable">
            <thead>
              <tr>
                <th>User</th>
                <th>Subject</th>
                <th>Category</th>
                <th>Year</th>
                <th>Questions</th>
                <th>Correct</th>
                <th>Score</th>
                <th>Date/Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($recentSessions as $s):
                $pct = round($s['score_pct']);
                $color = $pct >= 70 ? 'var(--green)' : ($pct >= 50 ? 'var(--amber)' : 'var(--red)');
                $catBadge = match(strtolower($s['category'])) {
                  'adaptive' => 'badge-purple',
                  'waec'     => 'badge-teal',
                  'jamb'     => 'badge-blue',
                  default    => 'badge-gray',
                };
              ?>
              <tr data-user="<?= htmlspecialchars($s['username']) ?>"
                  data-subject="<?= htmlspecialchars($s['subject_name']) ?>">
                <td><?= htmlspecialchars($s['username']) ?></td>
                <td><?= htmlspecialchars($s['subject_name']) ?></td>
                <td><span class="badge <?= $catBadge ?>"><?= strtoupper($s['category']) ?></span></td>
                <td><?= htmlspecialchars($s['year']) ?></td>
                <td><?= $s['total_q'] ?></td>
                <td><?= $s['correct'] ?></td>
                <td><span style="font-weight:700;color:<?= $color ?>"><?= $pct ?>%</span></td>
                <td style="font-size:12px;color:var(--text3);font-family:'JetBrains Mono',monospace"><?= date('d M Y H:i', strtotime($s['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /activity -->


      <!-- ═══════════════════════════════════════ SUBJECTS ══ -->
      <div id="section-subjects" class="tab-panel">

        <div class="card" style="margin-bottom:1.5rem">
          <div class="card-header">
            <div>
              <div class="card-title">Subject Engagement</div>
              <div class="card-sub">Sessions count vs average score per subject</div>
            </div>
          </div>
          <div class="mini-legend">
            <span><span class="legend-dot" style="background:#5b6af5"></span>Sessions (bars)</span>
            <span><span class="legend-dot" style="background:#f59e0b"></span>Avg Score % (line)</span>
          </div>
          <div class="chart-box" style="height:280px">
            <canvas id="subjChart" role="img" aria-label="Subject sessions and average scores"></canvas>
          </div>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Subject</th>
                <th>Total Sessions</th>
                <th>Total Questions</th>
                <th>Avg Score</th>
                <th>Performance</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($subjectPop as $s):
                $avg = round($s['avg_score'],1);
                $color = $avg >= 70 ? 'var(--green)' : ($avg >= 50 ? 'var(--amber)' : 'var(--red)');
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($s['subject_name']) ?></strong></td>
                <td><?= number_format($s['sessions']) ?></td>
                <td><?= number_format($s['questions']) ?></td>
                <td style="color:<?= $color ?>;font-weight:700"><?= $avg ?>%</td>
                <td style="min-width:140px">
                  <div class="score-bar-wrap">
                    <div class="score-bar-fill" style="width:<?= min(100,$avg) ?>%;background:<?= $color ?>"></div>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /subjects -->


      <!-- ═══════════════════════════════════════ AT-RISK ══ -->
      <div id="section-alerts" class="tab-panel">

        <?php if(!$struggling): ?>
        <div class="card" style="text-align:center;padding:3rem">
          <i class="fa-solid fa-circle-check" style="font-size:2.5rem;color:var(--green);margin-bottom:1rem"></i>
          <div style="font-size:1rem;font-weight:700;color:var(--text)">All users performing well</div>
          <div style="color:var(--text3);margin-top:.5rem">No users with ≥3 sessions have an average score below 40%.</div>
        </div>
        <?php else: ?>
        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:10px">
          <i class="fa-solid fa-triangle-exclamation" style="color:var(--red);font-size:1.1rem"></i>
          <span style="font-size:13.5px;color:var(--text)"><?= count($struggling) ?> user<?= count($struggling)>1?'s':'' ?> may need intervention — consistently scoring below 40% after multiple sessions.</span>
        </div>
        <div class="card">
          <?php foreach($struggling as $u):
            $avg = round($u['avg_score'],1);
            $la = $u['last_active'] ? date('d M Y', strtotime($u['last_active'])) : 'Never';
          ?>
          <div class="alert-row">
            <div style="display:flex;align-items:center;gap:12px">
              <div class="avatar" style="background:rgba(239,68,68,0.15);color:var(--red)"><?= strtoupper(substr($u['username'],0,2)) ?></div>
              <div>
                <div style="font-weight:600;color:var(--text)"><?= htmlspecialchars($u['username']) ?></div>
                <div style="font-size:11.5px;color:var(--text3)"><?= htmlspecialchars($u['email']) ?> · <?= $u['sessions'] ?> sessions · Last active: <?= $la ?></div>
              </div>
            </div>
            <div style="text-align:right">
              <div style="font-size:18px;font-weight:700;color:var(--red)"><?= $avg ?>%</div>
              <div style="font-size:11px;color:var(--text3)">avg score</div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div><!-- /alerts -->


      <!-- ═══════════════════════════════════════ USER REPORTS ══ -->
      <div id="section-reports" class="tab-panel">

        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem">
          <input type="text" class="search-input" id="reportSearch" placeholder="Search username or email…" oninput="filterReportUsers()" style="flex:1;min-width:200px">
          <button onclick="printCurrentReport()" class="btn-print" id="printBtn" style="display:none">
            <i class="fa-solid fa-print"></i> Print Report
          </button>
        </div>

        <!-- User picker list -->
        <div id="reportUserList" class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem">
          <div style="padding:.75rem 1rem;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);border-bottom:1px solid var(--border)">
            Select a user to view their full report
          </div>
          <div id="reportUserItems" style="max-height:260px;overflow-y:auto">
            <?php foreach ($users as $u):
              $avg = round($u['avg_score'], 1);
              $color = $avg >= 70 ? '#22c55e' : ($avg >= 50 ? '#f59e0b' : '#ef4444');
              $la = $u['last_active'] ? date('d M Y', strtotime($u['last_active'])) : 'Never';
            ?>
            <div class="report-user-row" data-uid="<?= $u['id'] ?>"
                 data-username="<?= htmlspecialchars($u['username']) ?>"
                 data-email="<?= htmlspecialchars($u['email']) ?>"
                 onclick="loadUserReport(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')"
                 style="display:flex;align-items:center;gap:12px;padding:.7rem 1rem;cursor:pointer;border-bottom:1px solid var(--border);transition:background .15s">
              <div class="avatar" style="flex-shrink:0"><?= strtoupper(substr($u['username'],0,2)) ?></div>
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:13px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($u['username']) ?></div>
                <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($u['email']) ?> · Last active: <?= $la ?></div>
              </div>
              <div style="text-align:right;flex-shrink:0">
                <div style="font-weight:700;color:<?= $color ?>;font-size:14px"><?= $avg ?>%</div>
                <div style="font-size:11px;color:var(--text3)"><?= $u['sessions'] ?> sessions</div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Report panel (populated by JS) -->
        <div id="reportPanel" style="display:none">
          <div id="reportContent"></div>
        </div>

      </div><!-- /reports -->

      <!-- ═══════════════════════════════════════ 2024 REPORTS ══ -->
      <div id="section-reports2024" class="tab-panel">

        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem">
          <input type="text" class="search-input" id="reportSearch2024" placeholder="Search username or email…" oninput="filterReportUsers2024()" style="flex:1;min-width:200px">
          <button onclick="printCurrentReport2024()" class="btn-print" id="printBtn2024" style="display:none">
            <i class="fa-solid fa-print"></i> Print Report
          </button>
        </div>

        <!-- User picker list -->
        <div id="reportUserList2024" class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem">
          <div style="padding:.75rem 1rem;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);border-bottom:1px solid var(--border)">
            Select a user to view their 2024 report
          </div>
          <div id="reportUserItems2024" style="max-height:260px;overflow-y:auto">
            <?php foreach ($usersWith2024 as $u):
              $avg = round($u['avg_score'], 1);
              $color = $avg >= 70 ? '#22c55e' : ($avg >= 50 ? '#f59e0b' : '#ef4444');
              $la = $u['last_active'] ? date('d M Y', strtotime($u['last_active'])) : 'Never';
            ?>
            <div class="report-user-row2024" data-uid="<?= $u['id'] ?>"
                 data-username="<?= htmlspecialchars($u['username']) ?>"
                 data-email="<?= htmlspecialchars($u['email']) ?>"
                 onclick="loadUserReport2024(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')"
                 style="display:flex;align-items:center;gap:12px;padding:.7rem 1rem;cursor:pointer;border-bottom:1px solid var(--border);transition:background .15s">
              <div class="avatar" style="flex-shrink:0"><?= strtoupper(substr($u['username'],0,2)) ?></div>
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:13px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($u['username']) ?></div>
                <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($u['email']) ?> · Last 2024 session: <?= $la ?></div>
              </div>
              <div style="text-align:right;flex-shrink:0">
                <div style="font-weight:700;color:<?= $color ?>;font-size:14px"><?= $avg ?>%</div>
                <div style="font-size:11px;color:var(--text3)"><?= $u['sessions'] ?> 2024 sessions</div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($usersWith2024)): ?>
              <div style="padding:1rem;color:var(--text3);font-size:13px">No users have completed 2024 sessions yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Report panel (populated by JS) -->
        <div id="reportPanel2024" style="display:none">
          <div id="reportContent2024"></div>
        </div>

      </div><!-- /reports2024 -->


      <!-- ═══════════════════════════════════════ TOPIC MASTERY ══ -->
      <div id="section-topics" class="tab-panel">

        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem">
          <input type="text" class="search-input" id="topicSearch" placeholder="Search username or topic…" oninput="filterTopics()" style="flex:1;min-width:200px">
          <select class="filter-select" id="topicSubjectFilter" onchange="filterTopics()">
            <option value="">All Subjects</option>
            <?php foreach ($subjectPop as $s): ?>
            <option value="<?= htmlspecialchars($s['subject_name']) ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="filter-select" id="topicMasteryFilter" onchange="filterTopics()">
            <option value="">All Mastery Levels</option>
            <option value="weak">Weak (&lt; 60%)</option>
            <option value="medium">Medium (60–79%)</option>
            <option value="strong">Strong (≥ 80%)</option>
          </select>
          <span id="topicCount" style="font-size:12px;color:var(--text3)"></span>
        </div>

        <div class="table-wrap">
          <table id="topicTable">
            <thead>
              <tr>
                <th>User</th>
                <th>Subject</th>
                <th>Topic</th>
                <th>Attempted</th>
                <th>Correct</th>
                <th>Mastery</th>
                <th>Level</th>
                <th>Last Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topicMasteryAll as $t):
                $pct   = round($t['mastery_score'], 1);
                $level = $pct >= 80 ? 'strong' : ($pct >= 60 ? 'medium' : 'weak');
                $color = $pct >= 80 ? '#22c55e' : ($pct >= 60 ? '#f59e0b' : '#ef4444');
                $badgeClass = $pct >= 80 ? 'badge-green' : ($pct >= 60 ? 'badge-amber' : 'badge-red');
                $lu = $t['last_updated'] ? date('d M Y', strtotime($t['last_updated'])) : '—';
              ?>
              <tr data-user="<?= htmlspecialchars(strtolower($t['username'])) ?>"
                  data-subject="<?= htmlspecialchars(strtolower($t['subject_name'])) ?>"
                  data-topic="<?= htmlspecialchars(strtolower($t['topic'])) ?>"
                  data-level="<?= $level ?>"
                  data-mastery="<?= $pct ?>">
                <td>
                  <div class="user-name">
                    <div class="avatar" style="width:28px;height:28px;font-size:11px"><?= strtoupper(substr($t['username'],0,2)) ?></div>
                    <span style="font-size:13px;font-weight:600"><?= htmlspecialchars($t['username']) ?></span>
                  </div>
                </td>
                <td><?= htmlspecialchars($t['subject_name']) ?></td>
                <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($t['topic']) ?>"><?= htmlspecialchars($t['topic']) ?></td>
                <td><?= $t['total_attempted'] ?></td>
                <td><?= $t['total_correct'] ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-weight:700;color:<?= $color ?>;min-width:38px"><?= $pct ?>%</span>
                    <div style="flex:1;height:4px;background:rgba(255,255,255,0.08);border-radius:999px;min-width:60px;overflow:hidden">
                      <div style="height:100%;width:<?= min(100,$pct) ?>%;background:<?= $color ?>;border-radius:999px"></div>
                    </div>
                  </div>
                </td>
                <td><span class="badge <?= $badgeClass ?>"><?= ucfirst($level) ?></span></td>
                <td style="font-size:12px;color:var(--text3)"><?= $lu ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$topicMasteryAll): ?>
              <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text3)">No topic mastery data yet — students need to complete at least one session.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /topics -->

      <!-- ═══════════════════════════════════════ EVALUATION ══ -->
      <div id="section-evaluation" class="tab-panel">
        
        <div style="margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
          <div>
            <h2 style="font-size:1.3rem;font-weight:700;color:var(--text)">System Evaluation &amp; Metrics</h2>
            <p style="color:var(--text3);font-size:0.9rem;margin-top:0.2rem">Final year project data on system effectiveness and engagement.</p>
          </div>
          <button onclick="exportEvalCSV()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;background:var(--accent);color:#fff;border:none;cursor:pointer;">
            <i class="fa-solid fa-download"></i> Export Data (CSV)
          </button>
        </div>

        <!-- Aggregate Metrics -->
        <div class="stat-grid">
          <div class="stat-card" style="--card-color:var(--accent)">
            <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div class="stat-val"><?= round($evalMetrics['avg_mastery']['overall_mastery'] ?? 0, 1) ?>%</div>
            <div class="stat-lbl">Global Avg Topic Mastery</div>
          </div>
          <div class="stat-card" style="--card-color:var(--teal)">
            <div class="stat-icon"><i class="fa-solid fa-user-clock"></i></div>
            <div class="stat-val"><?= round($evalMetrics['engagement'] ?? 0, 1) ?></div>
            <div class="stat-lbl">Avg Sessions / User</div>
          </div>
          <div class="stat-card" style="--card-color:var(--gold)">
            <div class="stat-icon"><i class="fa-solid fa-star"></i></div>
            <div class="stat-val"><?= number_format($avgUsability, 1) ?> / 5</div>
            <div class="stat-lbl">Avg Usability Score</div>
          </div>
          <div class="stat-card" style="--card-color:var(--green)">
            <div class="stat-icon"><i class="fa-solid fa-bullseye"></i></div>
            <div class="stat-val"><?= number_format($avgAccuracy, 1) ?> / 5</div>
            <div class="stat-lbl">Avg Adaptive Accuracy</div>
          </div>
        </div>

        <!-- New: Per-dimension averages -->
        <?php
        $dimensionLabels = [
            'learning_effectiveness' => ['Learning Effectiveness', 'fa-graduation-cap', '--purple'],
            'system_speed'           => ['System Speed', 'fa-bolt', '--amber'],
            'personalized_learning'  => ['Personalized Learning', 'fa-user-gear', '--teal'],
            'ui_design'              => ['UI Design', 'fa-palette', '--cyan'],
            'recommendation_accuracy'=> ['Recommendation Accuracy', 'fa-crosshairs', '--green'],
            'overall_satisfaction'   => ['Overall Satisfaction', 'fa-face-smile', '--accent'],
            'future_usage'           => ['Future Usage Intention', 'fa-rotate-right', '--purple'],
            'system_reliability'     => ['System Reliability', 'fa-shield-halved', '--teal'],
        ];
        ?>
        <div class="card" style="margin-bottom:2rem">
          <div class="card-header">
            <div>
              <div class="card-title">Evaluation Dimension Averages</div>
              <div class="card-sub">Average score per evaluation question across all respondents (<?= count($systemEvals) ?> total)</div>
            </div>
          </div>
          <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:12px; padding:0 0 .5rem">
            <?php foreach ($dimensionLabels as $key => [$label, $icon, $color]): ?>
            <div style="background:var(--bg3);border-radius:10px;padding:1rem;border:1px solid var(--border);text-align:center">
              <div style="font-size:1.5rem;margin-bottom:.25rem;"><i class="fa-solid <?= $icon ?>" style="color:var(<?= $color ?>)"></i></div>
              <div style="font-size:1.3rem;font-weight:700;color:var(--text)"><?= number_format($evalAvgs[$key], 1) ?> / 5</div>
              <div style="font-size:0.8rem;color:var(--text3);margin-top:2px"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Individual Student Responses -->
        <div class="card" style="margin-bottom:2rem">
          <div class="card-header">
            <h3>Student Responses</h3>
          </div>
          <?php if(count($systemEvals) > 0): ?>
          <div class="table-container" style="overflow-x:auto">
            <table style="font-size:12px">
              <thead>
                <tr>
                  <th>Student</th>
                  <th title="System Usability">Usability</th>
                  <th title="Adaptive Accuracy">Accuracy</th>
                  <th title="Learning Effectiveness">Learning</th>
                  <th title="System Speed">Speed</th>
                  <th title="Personalized Learning">Personalized</th>
                  <th title="UI Design">UI</th>
                  <th title="Recommendation Accuracy">Recommend</th>
                  <th title="Overall Satisfaction">Satisfaction</th>
                  <th title="Future Usage">Future Use</th>
                  <th title="System Reliability">Reliability</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($systemEvals as $e): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($e['username']) ?></strong></td>
                  <td><?= $e['usability_score'] ?>/5</td>
                  <td><?= $e['adaptive_accuracy'] ?>/5</td>
                  <td><?= ($e['learning_effectiveness'] ?? '-') !== null ? ($e['learning_effectiveness'] . '/5') : '-' ?></td>
                  <td><?= ($e['system_speed'] ?? null) !== null ? ($e['system_speed'] . '/5') : '-' ?></td>
                  <td><?= ($e['personalized_learning'] ?? null) !== null ? ($e['personalized_learning'] . '/5') : '-' ?></td>
                  <td><?= ($e['ui_design'] ?? null) !== null ? ($e['ui_design'] . '/5') : '-' ?></td>
                  <td><?= ($e['recommendation_accuracy'] ?? null) !== null ? ($e['recommendation_accuracy'] . '/5') : '-' ?></td>
                  <td><?= ($e['overall_satisfaction'] ?? null) !== null ? ($e['overall_satisfaction'] . '/5') : '-' ?></td>
                  <td><?= ($e['future_usage'] ?? null) !== null ? ($e['future_usage'] . '/5') : '-' ?></td>
                  <td><?= ($e['system_reliability'] ?? null) !== null ? ($e['system_reliability'] . '/5') : '-' ?></td>
                  <td style="font-size:0.8rem;color:var(--text3);white-space:nowrap"><?= date('d M Y', strtotime($e['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div style="padding: 2rem; text-align:center; color:var(--text3);">No student evaluations submitted yet.</div>
          <?php endif; ?>
        </div>

        <!-- Open-ended Responses -->
        <div class="card" style="margin-bottom:2rem">
          <div class="card-header">
            <h3>Open-Ended Feedback</h3>
          </div>
          <?php if(count($systemEvals) > 0): ?>
          <div class="table-container" style="overflow-x:auto">
            <table>
              <thead>
                <tr>
                  <th>Student</th>
                  <th>What They Liked Most</th>
                  <th>Suggestions for Improvement</th>
                  <th>Additional Feedback</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($systemEvals as $e): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($e['username']) ?></strong></td>
                  <td style="max-width:220px;white-space:normal;font-size:0.85rem;color:var(--text2)"><?= htmlspecialchars($e['liked_most'] ?? '') ?: '<em style="opacity:.5">—</em>' ?></td>
                  <td style="max-width:220px;white-space:normal;font-size:0.85rem;color:var(--text2)"><?= htmlspecialchars($e['suggestions'] ?? '') ?: '<em style="opacity:.5">—</em>' ?></td>
                  <td style="max-width:220px;white-space:normal;font-size:0.85rem;color:var(--text2)"><?= htmlspecialchars($e['feedback'] ?? '') ?: '<em style="opacity:.5">—</em>' ?></td>
                  <td style="font-size:0.8rem;color:var(--text3);white-space:nowrap"><?= date('d M Y', strtotime($e['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div style="padding: 2rem; text-align:center; color:var(--text3);">No student evaluations submitted yet.</div>
          <?php endif; ?>
        </div>

        <div class="card" style="margin-bottom:2rem">
          <div class="card-header">
            <div>
              <div class="card-title">Performance Comparison: Adaptive vs. Year-Based</div>
              <div class="card-sub">Evaluate if the adaptive algorithm improves overall scores compared to standard practice.</div>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Practice Mode</th>
                  <th>Total Sessions</th>
                  <th>Avg Questions / Session</th>
                  <th>Avg Score (%)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($evalMetrics['mode_comparison'] as $row): ?>
                <tr>
                  <td style="color:var(--text);font-weight:700">
                    <?php if(str_contains($row['mode'], 'Adaptive')): ?>
                      <i class="fa-solid fa-robot" style="color:var(--purple);margin-right:6px"></i>
                    <?php else: ?>
                      <i class="fa-solid fa-calendar-days" style="color:var(--teal);margin-right:6px"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($row['mode']) ?>
                  </td>
                  <td><?= number_format($row['sessions']) ?></td>
                  <td><?= round(($row['total_questions'] ?? 0) / max(1, $row['sessions']), 1) ?></td>
                  <td style="color:<?= $row['avg_score'] >= 50 ? 'var(--green)' : 'var(--red)' ?>;font-weight:700"><?= round($row['avg_score'], 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <script>
        function exportEvalCSV() {
          let csv = "Practice Mode,Total Sessions,Total Questions,Avg Score %\n";
          <?php foreach($evalMetrics['mode_comparison'] as $row): ?>
          csv += `"${<?= json_encode($row['mode']) ?>}",${<?= $row['sessions'] ?>},${<?= $row['total_questions'] ?? 0 ?>},${<?= round($row['avg_score'], 1) ?>}\n`;
          <?php endforeach; ?>
          
          csv += "\nGlobal Avg Topic Mastery,Total Topics Tracked,Avg Sessions/User\n";
          csv += `${<?= round($evalMetrics['avg_mastery']['overall_mastery'] ?? 0, 1) ?>}%,${<?= $evalMetrics['avg_mastery']['topics_tracked'] ?? 0 ?>},${<?= round($evalMetrics['engagement'] ?? 0, 1) ?>}\n`;
          
          const blob = new Blob([csv], { type: 'text/csv' });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.setAttribute('hidden', '');
          a.setAttribute('href', url);
          a.setAttribute('download', 'RBAPS_Evaluation_Data.csv');
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        }
        </script>
      </div><!-- /evaluation -->

      <!-- ═══════════════════════════════════════ SETTINGS ══ -->
      <div id="section-settings" class="tab-panel">
        <div class="card" style="max-width: 600px;">
          <div class="card-header">
            <div>
              <div class="card-title">Global Question Settings</div>
              <div class="card-sub">Control which questions are available to students across all practice modes.</div>
            </div>
          </div>
          
          <form method="POST" action="dashboard.php">
            <input type="hidden" name="action" value="update_settings">
            
            <div style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg3); border-radius: 8px; border: 1px solid var(--border);">
              <div>
                <div style="font-weight: 600; color: var(--text);">Hide all 2024 Questions</div>
                <div style="font-size: 0.85rem; color: var(--text3); margin-top: 0.2rem;">If enabled, questions tagged with the year "2024" will not be served in any practice mode.</div>
              </div>
              <label style="position: relative; display: inline-block; width: 44px; height: 24px;">
                <input type="checkbox" name="hide_2024" value="1" <?= $hide2024 === '1' ? 'checked' : '' ?> style="opacity: 0; width: 0; height: 0;" onchange="if(this.checked) document.getElementById('only_2024_toggle').checked = false;">
                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?= $hide2024 === '1' ? 'var(--accent)' : 'var(--bg4)' ?>; transition: .4s; border-radius: 24px;">
                  <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; transform: <?= $hide2024 === '1' ? 'translateX(20px)' : 'translateX(0)' ?>;"></span>
                </span>
              </label>
            </div>

            <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg3); border-radius: 8px; border: 1px solid var(--border);">
              <div>
                <div style="font-weight: 600; color: var(--text);">Show ONLY 2024 Questions</div>
                <div style="font-size: 0.85rem; color: var(--text3); margin-top: 0.2rem;">If enabled, ONLY questions tagged with the year "2024" will be served, across all subjects and sections. (Overrides the setting above).</div>
              </div>
              <label style="position: relative; display: inline-block; width: 44px; height: 24px;">
                <input type="checkbox" id="only_2024_toggle" name="only_2024" value="1" <?= $only2024 === '1' ? 'checked' : '' ?> style="opacity: 0; width: 0; height: 0;" onchange="if(this.checked) document.querySelector('input[name=hide_2024]').checked = false;">
                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?= $only2024 === '1' ? 'var(--accent)' : 'var(--bg4)' ?>; transition: .4s; border-radius: 24px;">
                  <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; transform: <?= $only2024 === '1' ? 'translateX(20px)' : 'translateX(0)' ?>;"></span>
                </span>
              </label>
            </div>
            
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px; border-radius: 8px; font-weight: 600; border: none; background: var(--accent); color: white; cursor: pointer;">
              Save Settings
            </button>
            <script>
            // Add a little bit of interactivity to the fake toggles on click
            document.querySelectorAll('input[type=checkbox]').forEach(el => {
                el.addEventListener('change', function() {
                    let slider = this.nextElementSibling;
                    let knob = slider.firstElementChild;
                    if(this.checked) {
                        slider.style.backgroundColor = 'var(--accent)';
                        knob.style.transform = 'translateX(20px)';
                    } else {
                        slider.style.backgroundColor = 'var(--bg4)';
                        knob.style.transform = 'translateX(0)';
                    }
                    
                    // Cross-update the other toggle visually if it was unchecked
                    document.querySelectorAll('input[type=checkbox]').forEach(other => {
                        if (other !== this && !other.checked) {
                            let otherSlider = other.nextElementSibling;
                            otherSlider.style.backgroundColor = 'var(--bg4)';
                            otherSlider.firstElementChild.style.transform = 'translateX(0)';
                        }
                    });
                });
            });
            </script>
          </form>
        </div>
      </div><!-- /settings -->

    </div><!-- .content -->
  </div><!-- .main -->
</div><!-- .layout -->


<script>
// ── Mobile Sidebar Toggle ─────────────────────────────────────────────────
(function(){
  const btn = document.getElementById('mob-menu-btn');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if(btn && sidebar && overlay){
    function openSidebar(){sidebar.classList.add('open');overlay.classList.add('open');document.body.style.overflow='hidden';}
    function closeSidebar(){sidebar.classList.remove('open');overlay.classList.remove('open');document.body.style.overflow='';}
    btn.addEventListener('click',openSidebar);
    overlay.addEventListener('click',closeSidebar);
    sidebar.querySelectorAll('.sidebar-nav a').forEach(a=>a.addEventListener('click',closeSidebar));
  }
})();

// ── Section switching ─────────────────────────────────────────────────────
const sectionTitles = {
  overview: ['Platform Overview',    'Real-time data'],
  users:    ['All Users',            'Manage and inspect student accounts'],
  activity: ['Activity Log',         'Last 50 practice sessions across all users'],
  subjects: ['Subject Analytics',    'Engagement and scores by subject'],
  alerts:   ['At-Risk Users',        'Students who may need support'],
  reports:  ['User Reports',         'Per-user session history, subjects & topic mastery — printable'],
  reports2024: ['2024 Results',      'View and download user reports for 2024 entries only'],
  topics:   ['Topic Mastery',        'All students × topic performance data'],
  evaluation: ['System Evaluation',  'Final year project data'],
  settings: ['Global Settings',      'Configure system-wide parameters'],
};

function showSection(id, el) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
  document.getElementById('section-' + id).classList.add('active');
  if (el) el.classList.add('active');
  const [title, sub] = sectionTitles[id] || ['Dashboard', ''];
  document.getElementById('topbar-title').textContent = title;
  document.getElementById('topbar-sub').textContent   = sub;
  return false;
}

// ── PHP data for reports/topics ──────────────────────────────────────────
const SESSION_BY_USER = <?= $sessionsByUserJson ?>;
const TOPICS_BY_USER  = <?= $topicsByUserJson ?>;
const SUBJECTS_BY_USER= <?= $subjectsByUserJson ?>;
const SESSION_BY_USER_2024 = <?= $sessionsByUser2024Json ?>;
const ANSWERS_BY_USER_2024 = <?= $answersByUser2024Json ?>;

// ── Report user picker filter ─────────────────────────────────────────────
function filterReportUsers() {
  const q = document.getElementById('reportSearch').value.toLowerCase();
  document.querySelectorAll('.report-user-row').forEach(row => {
    const match = row.dataset.username.toLowerCase().includes(q) ||
                  row.dataset.email.toLowerCase().includes(q);
    row.style.display = match ? '' : 'none';
  });
}

// ── Load full user report ─────────────────────────────────────────────────
let currentReportUid = null;
function loadUserReport(uid, username) {
  currentReportUid = uid;
  document.querySelectorAll('.report-user-row').forEach(r => r.classList.remove('selected'));
  const row = document.querySelector(`.report-user-row[data-uid="${uid}"]`);
  if (row) row.classList.add('selected');

  const sessions  = SESSION_BY_USER[uid]  || [];
  const topics    = TOPICS_BY_USER[uid]   || [];
  const subjects  = SUBJECTS_BY_USER[uid] || [];

  const totalQ    = sessions.reduce((s, r) => s + (+r.total_q), 0);
  const totalC    = sessions.reduce((s, r) => s + (+r.correct), 0);
  const avgScore  = sessions.length ? Math.round(sessions.reduce((s,r) => s + (+r.score_pct), 0) / sessions.length) : 0;
  const scoreColor= avgScore >= 70 ? '#22c55e' : avgScore >= 50 ? '#f59e0b' : '#ef4444';

  const email     = row ? row.dataset.email : '';
  const today     = new Date().toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'});

  const weak   = topics.filter(t => +t.mastery_score < 60).sort((a,b) => +a.mastery_score - +b.mastery_score);
  const medium = topics.filter(t => +t.mastery_score >= 60 && +t.mastery_score < 80);
  const strong = topics.filter(t => +t.mastery_score >= 80);

  function topicBadge(pct) {
    if (pct >= 80) return `<span style="color:#22c55e;font-weight:700">${pct}%</span>`;
    if (pct >= 60) return `<span style="color:#f59e0b;font-weight:700">${pct}%</span>`;
    return `<span style="color:#ef4444;font-weight:700">${pct}%</span>`;
  }

  let html = `
    <div class="print-header" style="display:none;margin-bottom:1.5rem;border-bottom:2px solid #111;padding-bottom:.75rem">
      <div style="font-size:18px;font-weight:700">RBAPS — Student Performance Report</div>
      <div style="font-size:12px;margin-top:4px;color:#555">Generated: ${today} · System: Rule-Based Adaptive Practice System</div>
    </div>

    <div class="card" style="margin-bottom:1rem">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem">
        <div>
          <div style="font-size:1.15rem;font-weight:700;color:var(--text)">${esc(username)}</div>
          <div style="font-size:12px;color:var(--text3);margin-top:2px">${esc(email)}</div>
          <div style="margin-top:.6rem;display:flex;gap:.5rem;flex-wrap:wrap">
            ${subjects.map(s => `<span style="font-size:11px;padding:2px 8px;border-radius:4px;background:rgba(91,106,245,0.15);color:#7c89ff">${esc(s)}</span>`).join('')}
          </div>
        </div>
        <div style="display:flex;gap:1.5rem;text-align:center">
          <div><div style="font-size:1.6rem;font-weight:700;color:${scoreColor}">${avgScore}%</div><div style="font-size:11px;color:var(--text3)">Avg Score</div></div>
          <div><div style="font-size:1.6rem;font-weight:700;color:var(--text)">${sessions.length}</div><div style="font-size:11px;color:var(--text3)">Sessions</div></div>
          <div><div style="font-size:1.6rem;font-weight:700;color:var(--text)">${totalQ}</div><div style="font-size:11px;color:var(--text3)">Questions</div></div>
          <div><div style="font-size:1.6rem;font-weight:700;color:var(--text)">${totalC}</div><div style="font-size:11px;color:var(--text3)">Correct</div></div>
        </div>
      </div>
    </div>

    <!-- Subjects taken -->
    <div class="card" style="margin-bottom:1rem">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:.75rem"><i class="fa-solid fa-book" style="color:var(--accent)"></i> Subjects Practised</div>
      ${subjects.length
        ? `<div style="display:flex;flex-wrap:wrap;gap:.5rem">${subjects.map(s=>`<span class="badge badge-blue">${esc(s)}</span>`).join('')}</div>`
        : `<div style="color:var(--text3);font-size:13px">No subjects yet</div>`}
    </div>

    <!-- Weak topics highlight -->
    ${weak.length ? `
    <div class="card" style="margin-bottom:1rem;background:rgba(239,68,68,0.05);border-color:rgba(239,68,68,0.2)">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:.75rem;color:#ef4444">
        <i class="fa-solid fa-triangle-exclamation"></i> Topics Needing Work (below 60%)
      </div>
      <div style="display:flex;flex-direction:column;gap:.35rem">
        ${weak.map(t => {
          const pct = Math.round(+t.mastery_score);
          return `<div style="display:flex;align-items:center;gap:.75rem;padding:.4rem .6rem;border-radius:8px;background:rgba(255,255,255,0.03)">
            <span style="min-width:40px;font-weight:700;color:#ef4444;font-size:.82rem">${pct}%</span>
            <div style="flex:1">
              <div style="font-size:.82rem;font-weight:600">${esc(t.topic)}</div>
              <div style="font-size:.72rem;color:var(--text3)">${esc(t.subject_name)} · ${t.total_correct}/${t.total_attempted} correct</div>
            </div>
          </div>`;
        }).join('')}
      </div>
    </div>` : ''}

    <!-- All topic mastery table -->
    <div class="card" style="margin-bottom:1rem">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:.75rem"><i class="fa-solid fa-brain" style="color:var(--accent)"></i> Full Topic Mastery</div>
      ${topics.length ? `
      <div class="table-wrap" style="margin:0">
        <table>
          <thead><tr><th>Subject</th><th>Topic</th><th>Attempted</th><th>Correct</th><th>Mastery</th><th>Last Practised</th></tr></thead>
          <tbody>
            ${topics.sort((a,b) => +a.mastery_score - +b.mastery_score).map(t => {
              const pct = Math.round(+t.mastery_score);
              const lu  = t.last_updated ? new Date(t.last_updated).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '—';
              return `<tr>
                <td>${esc(t.subject_name)}</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(t.topic)}">${esc(t.topic)}</td>
                <td>${t.total_attempted}</td>
                <td>${t.total_correct}</td>
                <td>${topicBadge(pct)}</td>
                <td style="font-size:12px;color:var(--text3)">${lu}</td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>` : `<div style="color:var(--text3);font-size:13px">No topic mastery data yet.</div>`}
    </div>

    <!-- Session history table -->
    <div class="card" style="margin-bottom:1rem">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:.75rem"><i class="fa-solid fa-clock-rotate-left" style="color:var(--accent)"></i> All Sessions</div>
      ${sessions.length ? `
      <div class="table-wrap" style="margin:0">
        <table>
          <thead><tr><th>#</th><th>Subject</th><th>Category</th><th>Year</th><th>Questions</th><th>Correct</th><th>Score</th><th>Date</th></tr></thead>
          <tbody>
            ${sessions.map((s, i) => {
              const pct   = Math.round(+s.score_pct);
              const col   = pct >= 70 ? '#22c55e' : pct >= 50 ? '#f59e0b' : '#ef4444';
              const dt    = s.sess_date ? new Date(s.sess_date).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';
              return `<tr>
                <td style="color:var(--text3);font-size:11px">${i+1}</td>
                <td>${esc(s.subject_name)}</td>
                <td>${esc((s.category||'').toUpperCase())}</td>
                <td>${esc(s.year||'—')}</td>
                <td>${s.total_q}</td>
                <td>${s.correct}</td>
                <td style="font-weight:700;color:${col}">${pct}%</td>
                <td style="font-size:11px;color:var(--text3)">${dt}</td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>` : `<div style="color:var(--text3);font-size:13px">No sessions recorded yet.</div>`}
    </div>
  `;

  document.getElementById('reportContent').innerHTML = html;
  document.getElementById('reportPanel').style.display = 'block';
  document.getElementById('printBtn').style.display = 'inline-flex';
  document.getElementById('reportPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function esc(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function printCurrentReport() {
  // Show print header only for printing
  document.querySelectorAll('.print-header').forEach(el => el.style.display = 'block');
  window.print();
  setTimeout(() => {
    document.querySelectorAll('.print-header').forEach(el => el.style.display = 'none');
  }, 500);
}

// ── Report 2024 user picker filter ────────────────────────────────────────
function filterReportUsers2024() {
  const q = document.getElementById('reportSearch2024').value.toLowerCase();
  document.querySelectorAll('.report-user-row2024').forEach(row => {
    const match = row.dataset.username.toLowerCase().includes(q) ||
                  row.dataset.email.toLowerCase().includes(q);
    row.style.display = match ? '' : 'none';
  });
}

// ── Load 2024 user report ─────────────────────────────────────────────────
function loadUserReport2024(uid, username) {
  document.querySelectorAll('.report-user-row2024').forEach(r => r.classList.remove('selected'));
  const row = document.querySelector(`.report-user-row2024[data-uid="${uid}"]`);
  if (row) row.classList.add('selected');

  const sessions  = SESSION_BY_USER_2024[uid] || [];
  const answers   = ANSWERS_BY_USER_2024[uid] || [];
  const email     = row ? row.dataset.email : '';
  const today     = new Date().toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'});

  const totalQ    = sessions.reduce((s, r) => s + (+r.total_q), 0);
  const totalC    = sessions.reduce((s, r) => s + (+r.correct), 0);
  const avgScore  = sessions.length ? Math.round(sessions.reduce((s,r) => s + (+r.score_pct), 0) / sessions.length) : 0;
  const scoreColor= avgScore >= 70 ? '#22c55e' : avgScore >= 50 ? '#f59e0b' : '#ef4444';

  // Subjects from sessions
  const subjectsSet = new Set();
  sessions.forEach(s => subjectsSet.add(s.subject_name));
  const subjects = Array.from(subjectsSet);

  // Per-subject stats from answers
  const subjStats = {};
  answers.forEach(a => {
    if (!subjStats[a.subject_name]) subjStats[a.subject_name] = { correct: 0, attempted: 0 };
    subjStats[a.subject_name].attempted++;
    if (+a.is_correct) subjStats[a.subject_name].correct++;
  });

  // Per-topic stats from answers
  const topicStats = {};
  answers.forEach(a => {
    const key = a.subject_name + '|||' + a.topic;
    if (!topicStats[key]) topicStats[key] = { subject: a.subject_name, topic: a.topic, correct: 0, attempted: 0 };
    topicStats[key].attempted++;
    if (+a.is_correct) topicStats[key].correct++;
  });
  const topicArr = Object.values(topicStats).map(t => ({
    ...t,
    pct: Math.round((t.correct / t.attempted) * 100)
  })).sort((a,b) => a.pct - b.pct);

  const weakTopics   = topicArr.filter(t => t.pct < 60);
  const mediumTopics = topicArr.filter(t => t.pct >= 60 && t.pct < 80);
  const strongTopics = topicArr.filter(t => t.pct >= 80);

  // Missed questions
  const missed = answers.filter(a => !+a.is_correct);

  function colorFor(pct) {
    return pct >= 80 ? '#22c55e' : pct >= 60 ? '#f59e0b' : '#ef4444';
  }

  // ── Build HTML ──────────────────────────────────────────────────────────
  let html = '';

  // Print header
  html += '<div class="print-header" style="display:none;margin-bottom:1.5rem;border-bottom:2px solid #111;padding-bottom:.75rem">' +
    '<div style="font-size:18px;font-weight:700">RBAPS — 2024 Student Performance Report</div>' +
    '<div style="font-size:12px;margin-top:4px;color:#555">Generated: ' + today + ' · System: Rule-Based Adaptive Practice System</div>' +
  '</div>';

  // Summary card
  html += '<div class="card" style="margin-bottom:1rem">' +
    '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem">' +
      '<div>' +
        '<div style="font-size:1.15rem;font-weight:700;color:var(--text)">' + esc(username) + '</div>' +
        '<div style="font-size:12px;color:var(--text3);margin-top:2px">' + esc(email) + '</div>' +
        '<div style="margin-top:.6rem;display:flex;gap:.5rem;flex-wrap:wrap">' +
          subjects.map(s => '<span style="font-size:11px;padding:2px 8px;border-radius:4px;background:rgba(91,106,245,0.15);color:#7c89ff">' + esc(s) + '</span>').join('') +
        '</div>' +
      '</div>' +
      '<div style="display:flex;gap:1.5rem;text-align:center">' +
        '<div><div style="font-size:1.6rem;font-weight:700;color:' + scoreColor + '">' + avgScore + '%</div><div style="font-size:11px;color:var(--text3)">Avg Score</div></div>' +
        '<div><div style="font-size:1.6rem;font-weight:700;color:var(--text)">' + sessions.length + '</div><div style="font-size:11px;color:var(--text3)">Sessions</div></div>' +
        '<div><div style="font-size:1.6rem;font-weight:700;color:var(--text)">' + totalQ + '</div><div style="font-size:11px;color:var(--text3)">Questions</div></div>' +
        '<div><div style="font-size:1.6rem;font-weight:700;color:var(--text)">' + totalC + '</div><div style="font-size:11px;color:var(--text3)">Correct</div></div>' +
      '</div>' +
    '</div>' +
  '</div>';

  // Per-subject breakdown
  if (Object.keys(subjStats).length) {
    html += '<div class="card" style="margin-bottom:1rem">' +
      '<div style="font-weight:700;font-size:.9rem;margin-bottom:.75rem"><i class="fa-solid fa-book" style="color:var(--accent)"></i> Subject Performance (2024 Questions Only)</div>' +
      '<div class="table-wrap" style="margin:0"><table>' +
      '<thead><tr><th>Subject</th><th>Attempted</th><th>Correct</th><th>Score</th><th>Progress</th></tr></thead><tbody>';
    Object.entries(subjStats).sort((a,b) => {
      const pa = Math.round(a[1].correct/a[1].attempted*100);
      const pb = Math.round(b[1].correct/b[1].attempted*100);
      return pa - pb;
    }).forEach(([subj, s]) => {
      const pct = Math.round(s.correct / s.attempted * 100);
      const col = colorFor(pct);
      html += '<tr>' +
        '<td style="font-weight:600">' + esc(subj) + '</td>' +
        '<td>' + s.attempted + '</td>' +
        '<td>' + s.correct + '</td>' +
        '<td style="font-weight:700;color:' + col + '">' + pct + '%</td>' +
        '<td><div style="width:100px;height:5px;background:var(--bg4);border-radius:99px;overflow:hidden"><div style="height:100%;width:' + pct + '%;background:' + col + ';border-radius:99px"></div></div></td>' +
      '</tr>';
    });
    html += '</tbody></table></div></div>';
  }

  // Weak topics panel
  if (weakTopics.length) {
    html += '<div class="card" style="margin-bottom:1rem;background:rgba(239,68,68,0.05);border-color:rgba(239,68,68,0.2)">' +
      '<div style="font-weight:700;font-size:.9rem;margin-bottom:.5rem;color:#ef4444">' +
        '<i class="fa-solid fa-triangle-exclamation"></i> Weak Topics — Below 60% Mastery (' + weakTopics.length + ' topic' + (weakTopics.length !== 1 ? 's' : '') + ')' +
      '</div>' +
      '<div style="font-size:.78rem;color:var(--text2);margin-bottom:.75rem">These 2024 topics need the most attention. Consider recommending additional practice.</div>' +
      '<div style="display:flex;flex-direction:column;gap:.35rem">';
    weakTopics.forEach(t => {
      const col = t.pct < 30 ? '#ef4444' : t.pct < 50 ? '#f59e0b' : '#eab308';
      html += '<div style="display:flex;align-items:center;gap:.75rem;padding:.45rem .75rem;border-radius:8px;background:rgba(255,255,255,0.03)">' +
        '<span style="min-width:40px;font-weight:700;color:' + col + ';font-size:.82rem">' + t.pct + '%</span>' +
        '<div style="flex:1">' +
          '<div style="font-size:.82rem;font-weight:600">' + esc(t.topic) + '</div>' +
          '<div style="font-size:.72rem;color:var(--text3)">' + esc(t.subject) + ' · ' + t.correct + '/' + t.attempted + ' correct</div>' +
          '<div style="height:3px;background:var(--bg4);border-radius:999px;margin-top:3px;overflow:hidden"><div style="height:100%;width:' + t.pct + '%;background:' + col + ';border-radius:999px"></div></div>' +
        '</div>' +
      '</div>';
    });
    html += '</div></div>';
  }

  // Medium topics
  if (mediumTopics.length) {
    html += '<div class="card" style="margin-bottom:1rem;background:rgba(245,158,11,0.04);border-color:rgba(245,158,11,0.18)">' +
      '<div style="font-weight:700;font-size:.9rem;margin-bottom:.5rem;color:#f59e0b">' +
        '<i class="fa-solid fa-chart-line"></i> Topics to Reinforce — 60–79% Mastery (' + mediumTopics.length + ')' +
      '</div>' +
      '<div style="display:flex;flex-direction:column;gap:.35rem">';
    mediumTopics.forEach(t => {
      html += '<div style="display:flex;align-items:center;gap:.75rem;padding:.4rem .6rem;border-radius:8px;background:rgba(255,255,255,0.03)">' +
        '<span style="min-width:40px;font-weight:700;color:#f59e0b;font-size:.82rem">' + t.pct + '%</span>' +
        '<div style="flex:1">' +
          '<div style="font-size:.82rem;font-weight:600">' + esc(t.topic) + '</div>' +
          '<div style="font-size:.72rem;color:var(--text3)">' + esc(t.subject) + ' · ' + t.correct + '/' + t.attempted + '</div>' +
        '</div>' +
      '</div>';
    });
    html += '</div></div>';
  }

  // Strong topics
  if (strongTopics.length) {
    html += '<div class="card" style="margin-bottom:1rem;background:rgba(34,197,94,0.04);border-color:rgba(34,197,94,0.18)">' +
      '<div style="font-weight:700;font-size:.9rem;margin-bottom:.5rem;color:#22c55e">' +
        '<i class="fa-solid fa-circle-check"></i> Strong Topics — 80%+ Mastery (' + strongTopics.length + ')' +
      '</div>' +
      '<div style="display:flex;flex-wrap:wrap;gap:.4rem">';
    strongTopics.forEach(t => {
      html += '<span style="font-size:11px;padding:3px 10px;border-radius:6px;background:rgba(34,197,94,0.12);color:#22c55e;font-weight:600">' +
        esc(t.topic) + ' · ' + t.pct + '%</span>';
    });
    html += '</div></div>';
  }

  // Full topic mastery table
  if (topicArr.length) {
    html += '<div class="card" style="margin-bottom:1rem">' +
      '<div style="font-weight:700;font-size:.9rem;margin-bottom:.75rem"><i class="fa-solid fa-brain" style="color:var(--accent)"></i> Full Topic Breakdown (2024)</div>' +
      '<div class="table-wrap" style="margin:0"><table>' +
      '<thead><tr><th>Subject</th><th>Topic</th><th>Attempted</th><th>Correct</th><th>Mastery</th></tr></thead><tbody>';
    topicArr.forEach(t => {
      const col = colorFor(t.pct);
      html += '<tr>' +
        '<td>' + esc(t.subject) + '</td>' +
        '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(t.topic) + '">' + esc(t.topic) + '</td>' +
        '<td>' + t.attempted + '</td>' +
        '<td>' + t.correct + '</td>' +
        '<td><div style="display:flex;align-items:center;gap:8px"><span style="font-weight:700;color:' + col + ';min-width:38px">' + t.pct + '%</span>' +
          '<div style="flex:1;height:4px;background:var(--bg4);border-radius:999px;min-width:60px;overflow:hidden"><div style="height:100%;width:' + t.pct + '%;background:' + col + ';border-radius:999px"></div></div></div></td>' +
      '</tr>';
    });
    html += '</tbody></table></div></div>';
  }

  // Session history
  html += '<div class="card" style="margin-bottom:1rem">' +
    '<div style="font-weight:700;font-size:.9rem;margin-bottom:.75rem"><i class="fa-solid fa-clock-rotate-left" style="color:var(--accent)"></i> Session History (2024)</div>';
  if (sessions.length) {
    html += '<div class="table-wrap" style="margin:0"><table>' +
      '<thead><tr><th>#</th><th>Subject</th><th>Category</th><th>Questions</th><th>Correct</th><th>Score</th><th>Date</th></tr></thead><tbody>';
    sessions.forEach((s, i) => {
      const pct = Math.round(+s.score_pct);
      const col = colorFor(pct);
      const dt  = s.sess_date ? new Date(s.sess_date).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';
      html += '<tr>' +
        '<td style="color:var(--text3);font-size:11px">' + (i+1) + '</td>' +
        '<td>' + esc(s.subject_name) + '</td>' +
        '<td>' + esc((s.category||'').toUpperCase()) + '</td>' +
        '<td>' + s.total_q + '</td>' +
        '<td>' + s.correct + '</td>' +
        '<td style="font-weight:700;color:' + col + '">' + pct + '%</td>' +
        '<td style="font-size:11px;color:var(--text3)">' + dt + '</td>' +
      '</tr>';
    });
    html += '</tbody></table></div>';
  } else {
    html += '<div style="color:var(--text3);font-size:13px">No 2024 sessions recorded.</div>';
  }
  html += '</div>';

  // Missed questions review
  if (missed.length) {
    html += '<div class="card" style="margin-bottom:1rem">' +
      '<div style="font-weight:700;font-size:.9rem;margin-bottom:.75rem;color:#ef4444"><i class="fa-solid fa-circle-xmark"></i> Questions Answered Incorrectly (' + missed.length + ')</div>';
    missed.forEach((q, i) => {
      const correctLetter = q.correct_option;
      const correctText   = q['option_' + correctLetter.toLowerCase()] || correctLetter;
      const chosenText    = q.chosen ? (q['option_' + q.chosen.toLowerCase()] || q.chosen) : 'No answer';
      html += '<div style="padding:.75rem 1rem;border-radius:10px;margin-bottom:.6rem;background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.12)">' +
        '<div style="font-size:.72rem;color:var(--text3);margin-bottom:.2rem">' + esc(q.subject_name) + ' · Topic: ' + esc(q.topic) + '</div>' +
        '<div style="font-size:.85rem;font-weight:600;margin-bottom:.4rem;color:var(--text)">' +
          '<i class="fa-solid fa-circle-xmark" style="color:#ef4444"></i> Q' + (i+1) + ': ' + esc((q.question||'').slice(0, 150)) + (q.question && q.question.length > 150 ? '…' : '') +
        '</div>' +
        '<div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.8rem">' +
          '<div style="color:#ef4444"><strong>Your answer:</strong> ' + esc(q.chosen || '—') + '. ' + esc(chosenText) + '</div>' +
          '<div style="color:#22c55e"><strong>Correct:</strong> ' + esc(correctLetter) + '. ' + esc(correctText) + '</div>' +
        '</div>' +
      '</div>';
    });
    html += '</div>';
  }

  document.getElementById('reportContent2024').innerHTML = html;
  document.getElementById('reportPanel2024').style.display = 'block';
  document.getElementById('printBtn2024').style.display = 'inline-flex';
  document.getElementById('reportPanel2024').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function printCurrentReport2024() {
  document.querySelectorAll('.print-header').forEach(el => el.style.display = 'block');
  window.print();
  setTimeout(() => {
    document.querySelectorAll('.print-header').forEach(el => el.style.display = 'none');
  }, 500);
}

// ── Topic mastery filter ──────────────────────────────────────────────────
function filterTopics() {
  const q      = document.getElementById('topicSearch').value.toLowerCase();
  const subj   = document.getElementById('topicSubjectFilter').value.toLowerCase();
  const level  = document.getElementById('topicMasteryFilter').value;
  const rows   = Array.from(document.querySelectorAll('#topicTable tbody tr'));
  let cnt = 0;
  rows.forEach(r => {
    const mUser  = r.dataset.user    && r.dataset.user.includes(q);
    const mTopic = r.dataset.topic   && r.dataset.topic.includes(q);
    const mSubj  = r.dataset.subject && r.dataset.subject.includes(q);
    const matchQ = mUser || mTopic || mSubj;
    const matchS = !subj  || r.dataset.subject === subj;
    const matchL = !level || r.dataset.level   === level;
    const show   = matchQ && matchS && matchL;
    r.style.display = show ? '' : 'none';
    if (show) cnt++;
  });
  document.getElementById('topicCount').textContent = cnt + ' rows';
}
filterTopics();

// ── User table filter ─────────────────────────────────────────────────────
function filterUsers() {
  const q     = document.getElementById('userSearch').value.toLowerCase();
  const sort  = document.getElementById('userSortBy').value;
  const tbody = document.querySelector('#userTable tbody');
  const rows  = Array.from(tbody.rows);

  rows.forEach(r => {
    const match = r.dataset.username.toLowerCase().includes(q) ||
                  r.dataset.email.toLowerCase().includes(q);
    r.style.display = match ? '' : 'none';
  });

  const visible = rows.filter(r => r.style.display !== 'none');
  visible.sort((a,b) => {
    switch(sort) {
      case 'sessions':    return +b.dataset.sessions  - +a.dataset.sessions;
      case 'avg_score':   return +b.dataset.avg       - +a.dataset.avg;
      case 'questions':   return +b.dataset.questions - +a.dataset.questions;
      case 'created':     return new Date(b.dataset.created) - new Date(a.dataset.created);
      default:            return new Date(b.dataset.last||0) - new Date(a.dataset.last||0);
    }
  });
  visible.forEach(r => tbody.appendChild(r));
  document.getElementById('userCount').textContent = visible.length + ' users';
}

// ── Activity filter ───────────────────────────────────────────────────────
function filterActivity() {
  const q   = document.getElementById('actSearch').value.toLowerCase();
  const sub = document.getElementById('actSubject').value.toLowerCase();
  const rows = Array.from(document.querySelectorAll('#actTable tbody tr'));
  let cnt = 0;
  rows.forEach(r => {
    const matchQ = r.dataset.user.toLowerCase().includes(q) ||
                   r.dataset.subject.toLowerCase().includes(q);
    const matchS = !sub || r.dataset.subject.toLowerCase() === sub;
    const show = matchQ && matchS;
    r.style.display = show ? '' : 'none';
    if (show) cnt++;
  });
  document.getElementById('actCount').textContent = cnt + ' sessions';
}

// Init counts
filterUsers();
filterActivity();

// ── Charts ────────────────────────────────────────────────────────────────
const dailyLabels   = <?= $dailyLabels ?>;
const dailyCounts   = <?= $dailyCounts ?>;
const dailyAvgScore = <?= $dailyAvgScore ?>;
const subjLabels    = <?= $subjLabels ?>;
const subjCounts    = <?= $subjCounts ?>;
const subjScores    = <?= $subjScores ?>;
const regLabels     = <?= $regLabels ?>;
const regCounts     = <?= $regCounts ?>;
const catLabels     = <?= $catLabels ?>;
const catCounts     = <?= $catCounts ?>;

Chart.defaults.color = '#8b90a8';
Chart.defaults.borderColor = 'rgba(255,255,255,0.07)';

// Daily sessions
new Chart(document.getElementById('dailyChart'), {
  data: {
    labels: dailyLabels,
    datasets: [
      {
        type: 'bar',
        label: 'Sessions',
        data: dailyCounts,
        backgroundColor: 'rgba(91,106,245,0.6)',
        borderColor: '#5b6af5',
        borderWidth: 1,
        borderRadius: 4,
        yAxisID: 'y',
      },
      {
        type: 'line',
        label: 'Avg Score %',
        data: dailyAvgScore,
        borderColor: '#22c55e',
        backgroundColor: 'rgba(34,197,94,0.08)',
        tension: 0.4,
        pointRadius: 3,
        pointBackgroundColor: '#22c55e',
        fill: true,
        yAxisID: 'y2',
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { maxRotation: 30 } },
      y:  { position: 'left',  grid: { color: 'rgba(255,255,255,0.05)' }, title: { display: true, text: 'Sessions', color: '#5b6af5' } },
      y2: { position: 'right', grid: { display: false }, min: 0, max: 100, title: { display: true, text: 'Score %', color: '#22c55e' } },
    }
  }
});

// Category pie
const catColors = ['#5b6af5','#22c55e','#f59e0b','#ef4444','#14b8a6','#a855f7'];
new Chart(document.getElementById('catChart'), {
  type: 'doughnut',
  data: {
    labels: catLabels,
    datasets: [{
      data: catCounts,
      backgroundColor: catColors.slice(0, catLabels.length),
      borderWidth: 2,
      borderColor: '#141720',
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    cutout: '65%',
  }
});
// Build custom legend
const catLeg = document.getElementById('catLegend');
catLabels.forEach((lbl, i) => {
  const total = catCounts.reduce((a,b)=>a+b,0);
  const pct = total ? Math.round(catCounts[i]/total*100) : 0;
  catLeg.innerHTML += `<span><span class="legend-dot" style="background:${catColors[i]}"></span>${lbl} ${pct}%</span>`;
});

// Registration trend
new Chart(document.getElementById('regChart'), {
  type: 'bar',
  data: {
    labels: regLabels,
    datasets: [{
      label: 'Registrations',
      data: regCounts,
      backgroundColor: 'rgba(20,184,166,0.5)',
      borderColor: '#14b8a6',
      borderWidth: 1,
      borderRadius: 3,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true } },
      y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { stepSize: 1 } }
    }
  }
});

// Subject chart (horizontal)
new Chart(document.getElementById('subjChart'), {
  data: {
    labels: subjLabels,
    datasets: [
      {
        type: 'bar',
        label: 'Sessions',
        data: subjCounts,
        backgroundColor: 'rgba(91,106,245,0.55)',
        borderColor: '#5b6af5',
        borderWidth: 1,
        borderRadius: 3,
        yAxisID: 'y',
      },
      {
        type: 'line',
        label: 'Avg Score %',
        data: subjScores,
        borderColor: '#f59e0b',
        backgroundColor: 'transparent',
        tension: 0.3,
        pointRadius: 4,
        pointBackgroundColor: '#f59e0b',
        yAxisID: 'y2',
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { maxRotation: 30, autoSkip: false } },
      y:  { position: 'left',  grid: { color: 'rgba(255,255,255,0.05)' }, title: { display: true, text: 'Sessions', color: '#5b6af5' } },
      y2: { position: 'right', grid: { display: false }, min: 0, max: 100, title: { display: true, text: 'Score %', color: '#f59e0b' } },
    }
  }
});
</script>

</body>
</html>
