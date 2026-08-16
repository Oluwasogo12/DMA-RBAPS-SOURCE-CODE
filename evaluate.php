<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();

$pageTitle = 'Evaluate System — RBAPS';
$currentPage = 'evaluate';
$db = getDB();
$uid = $_SESSION['user_id'];

// Create table for final year project evaluations
$db->exec("CREATE TABLE IF NOT EXISTS system_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    usability_score INT NOT NULL,
    adaptive_accuracy INT NOT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_eval (user_id)
)");

// Add new columns if they don't exist yet
$newCols = [
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
foreach ($newCols as $col => $type) {
    try {
        $db->exec("ALTER TABLE system_evaluations ADD COLUMN $col $type");
    } catch (Exception $e) {
        // Column already exists — ignore
    }
}

$success = '';
$error = '';

// Define all rating questions in order
$ratingQuestions = [
    ['name' => 'usability',              'label' => 'System Usability',              'desc' => 'How easy was it to navigate and use the platform?',                               'low' => 'Very Hard',         'high' => 'Very Easy',           'dbcol' => 'usability_score'],
    ['name' => 'accuracy',               'label' => 'Adaptive Accuracy',             'desc' => 'Did the system accurately adapt the difficulty of questions based on your performance?', 'low' => 'Not at all',        'high' => 'Very Accurately',     'dbcol' => 'adaptive_accuracy'],
    ['name' => 'learning_effectiveness', 'label' => 'Learning Effectiveness',        'desc' => 'Do you think this platform will help improve your understanding on the subjects/topics?', 'low' => 'Not Helpful',      'high' => 'Very Helpful',        'dbcol' => 'learning_effectiveness'],
    ['name' => 'system_speed',           'label' => 'System Response Speed',         'desc' => 'How satisfied were you with the speed and performance of the website?',              'low' => 'Very Slow',         'high' => 'Very Fast',           'dbcol' => 'system_speed'],
    ['name' => 'personalized_learning',  'label' => 'Personalized Learning Experience','desc' => 'Did the system provide a personalized learning experience based on your performance?', 'low' => 'Not Personalized', 'high' => 'Highly Personalized', 'dbcol' => 'personalized_learning'],
    ['name' => 'ui_design',              'label' => 'User Interface Design',         'desc' => 'How appealing and organized was the website interface?',                            'low' => 'Poor',              'high' => 'Excellent',           'dbcol' => 'ui_design'],
    ['name' => 'recommendation_accuracy','label' => 'Recommendation Accuracy',       'desc' => 'Did the system recommend appropriate topics for you?',                              'low' => 'Not Accurate',      'high' => 'Very Accurate',       'dbcol' => 'recommendation_accuracy'],
    ['name' => 'overall_satisfaction',   'label' => 'Overall Satisfaction',          'desc' => 'Overall, how satisfied are you with the adaptive learning system?',                  'low' => 'Very Dissatisfied', 'high' => 'Very Satisfied',      'dbcol' => 'overall_satisfaction'],
    ['name' => 'future_usage',           'label' => 'Future Usage Intention',        'desc' => 'Would you continue using this platform for UTME/SSCE preparation?',                 'low' => 'Definitely No',     'high' => 'Definitely Yes',      'dbcol' => 'future_usage'],
    ['name' => 'system_reliability',     'label' => 'System Reliability',            'desc' => 'Did the website function properly without crashes or major errors?',                 'low' => 'Very Unreliable',   'high' => 'Very Reliable',       'dbcol' => 'system_reliability'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate all rating scores
    $scores = [];
    $valid = true;
    foreach ($ratingQuestions as $q) {
        $val = (int)($_POST[$q['name']] ?? 0);
        if ($val < 1 || $val > 5) {
            $valid = false;
            break;
        }
        $scores[$q['name']] = $val;
    }

    $feedback    = trim($_POST['feedback'] ?? '');
    $liked_most  = trim($_POST['liked_most'] ?? '');
    $suggestions = trim($_POST['suggestions'] ?? '');

    if (!$valid) {
        $error = 'Please provide valid 1-5 ratings for all questions.';
    } else {
        $stmt = $db->prepare("
            INSERT INTO system_evaluations 
                (user_id, usability_score, adaptive_accuracy, learning_effectiveness, system_speed, 
                 personalized_learning, ui_design, recommendation_accuracy, overall_satisfaction, 
                 future_usage, system_reliability, feedback, liked_most, suggestions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                usability_score=VALUES(usability_score), 
                adaptive_accuracy=VALUES(adaptive_accuracy),
                learning_effectiveness=VALUES(learning_effectiveness),
                system_speed=VALUES(system_speed),
                personalized_learning=VALUES(personalized_learning),
                ui_design=VALUES(ui_design),
                recommendation_accuracy=VALUES(recommendation_accuracy),
                overall_satisfaction=VALUES(overall_satisfaction),
                future_usage=VALUES(future_usage),
                system_reliability=VALUES(system_reliability),
                feedback=VALUES(feedback),
                liked_most=VALUES(liked_most),
                suggestions=VALUES(suggestions)
        ");
        $stmt->execute([
            $uid,
            $scores['usability'],
            $scores['accuracy'],
            $scores['learning_effectiveness'],
            $scores['system_speed'],
            $scores['personalized_learning'],
            $scores['ui_design'],
            $scores['recommendation_accuracy'],
            $scores['overall_satisfaction'],
            $scores['future_usage'],
            $scores['system_reliability'],
            $feedback,
            $liked_most,
            $suggestions
        ]);
        $success = 'Thank you! Your evaluation has been recorded. This greatly helps with the Final Year Project analysis.';
    }
}

// Check if already evaluated to pre-fill
$check = $db->prepare("SELECT * FROM system_evaluations WHERE user_id=?");
$check->execute([$uid]);
$existing = $check->fetch(PDO::FETCH_ASSOC);

// Map existing data back to form names for pre-fill
$existingMap = [];
if ($existing) {
    foreach ($ratingQuestions as $q) {
        $existingMap[$q['name']] = $existing[$q['dbcol']] ?? null;
    }
}

include 'includes/header.php';
?>

<style>
  .eval-container {
    max-width: 720px;
    margin: 2rem auto;
    padding: 0 1rem;
  }

  .eval-card {
    background: var(--card);
    border-radius: var(--radius);
    border: 1px solid var(--border-soft);
    box-shadow: var(--shadow);
    overflow: hidden;
  }

  .eval-header {
    background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
    padding: 2rem 2rem 1.5rem;
    text-align: center;
    color: #fff;
  }

  .eval-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.35rem;
  }

  .eval-header p {
    font-size: 0.9rem;
    opacity: 0.85;
  }

  .eval-progress {
    display: flex;
    align-items: center;
    gap: 0;
    padding: 0 2rem;
    margin-top: 1.25rem;
  }

  .eval-progress .step {
    flex: 1;
    height: 4px;
    border-radius: 2px;
    background: rgba(255,255,255,0.25);
    transition: background 0.3s;
  }

  .eval-progress .step + .step {
    margin-left: 4px;
  }

  .eval-progress .step.filled {
    background: rgba(255,255,255,0.85);
  }

  .eval-body {
    padding: 2rem;
  }

  .eval-question {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-soft);
  }

  .eval-question:last-of-type {
    border-bottom: none;
    margin-bottom: 1rem;
  }

  .eq-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--accent);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    margin-right: 0.5rem;
    flex-shrink: 0;
  }

  .eq-label {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--heading);
    display: flex;
    align-items: center;
    margin-bottom: 0.3rem;
  }

  .eq-desc {
    font-size: 0.85rem;
    color: var(--text2);
    margin-bottom: 0.75rem;
    padding-left: 2.35rem;
  }

  .eq-options {
    display: flex;
    gap: 0.5rem;
    padding-left: 2.35rem;
    flex-wrap: wrap;
  }

  .eq-options label {
    position: relative;
    cursor: pointer;
  }

  .eq-options input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
  }

  .eq-options .option-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: 12px;
    border: 2px solid var(--border-soft);
    background: var(--bg);
    color: var(--text2);
    font-weight: 700;
    font-size: 1.1rem;
    transition: all 0.2s;
  }

  .eq-options input[type="radio"]:checked + .option-btn {
    border-color: var(--accent);
    background: rgba(79, 70, 229, 0.1);
    color: var(--accent);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    transform: scale(1.05);
  }

  .eq-options label:hover .option-btn {
    border-color: var(--accent);
    background: rgba(79, 70, 229, 0.05);
  }

  .eq-scale-labels {
    display: flex;
    justify-content: space-between;
    padding-left: 2.35rem;
    margin-top: 0.35rem;
    max-width: 320px;
  }

  .eq-scale-labels span {
    font-size: 0.7rem;
    color: var(--text2);
    opacity: 0.7;
  }

  .eval-section-divider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 1.5rem 0;
    color: var(--text2);
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .eval-section-divider::before,
  .eval-section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-soft);
  }

  .eval-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-soft);
    border-radius: 10px;
    background: var(--bg);
    color: var(--heading);
    font-family: inherit;
    font-size: 0.9rem;
    resize: vertical;
    transition: border-color 0.2s;
    min-height: 80px;
  }

  .eval-textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
  }

  .eval-textarea::placeholder {
    color: var(--text2);
    opacity: 0.6;
  }

  .eval-submit {
    width: 100%;
    padding: 0.9rem;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
    color: #fff;
    font-family: inherit;
    font-size: 1.05rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 1rem;
  }

  .eval-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.35);
  }

  .eval-submit:active {
    transform: translateY(0);
  }

  @media (max-width: 600px) {
    .eval-body { padding: 1.25rem; }
    .eval-header { padding: 1.5rem 1.25rem 1.25rem; }
    .eq-options { gap: 0.4rem; padding-left: 0; }
    .eq-options .option-btn { width: 48px; height: 48px; font-size: 1rem; }
    .eq-desc, .eq-scale-labels { padding-left: 0; }
    .eq-label { font-size: 0.95rem; }
  }
</style>

<div class="eval-container">

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom: 1.5rem">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="eval-card">
    <div class="eval-header">
      <h2><i class="fa-solid fa-clipboard-check" style="margin-right:0.5rem"></i> Project Evaluation Form</h2>
      <p>Please rate your experience using the RBAPS Adaptive Learning System</p>
      <div class="eval-progress" id="evalProgress">
        <?php for ($i = 0; $i < count($ratingQuestions); $i++): ?>
          <div class="step <?= ($existing && !empty($existingMap[$ratingQuestions[$i]['name']])) ? 'filled' : '' ?>" data-step="<?= $i ?>"></div>
        <?php endfor; ?>
      </div>
    </div>

    <div class="eval-body">
      <form method="POST" id="evalForm">

        <?php foreach ($ratingQuestions as $idx => $q): ?>
          <div class="eval-question" data-question="<?= $idx ?>">
            <div class="eq-label">
              <span class="eq-number"><?= $idx + 1 ?></span>
              <?= htmlspecialchars($q['label']) ?>
            </div>
            <div class="eq-desc"><?= htmlspecialchars($q['desc']) ?></div>
            <div class="eq-options">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <label>
                  <input type="radio" name="<?= $q['name'] ?>" value="<?= $i ?>" required
                    <?= (isset($existingMap[$q['name']]) && (int)$existingMap[$q['name']] === $i) ? 'checked' : '' ?>
                    onchange="updateProgress()">
                  <div class="option-btn"><?= $i ?></div>
                </label>
              <?php endfor; ?>
            </div>
            <div class="eq-scale-labels">
              <span>1 = <?= htmlspecialchars($q['low']) ?></span>
              <span>5 = <?= htmlspecialchars($q['high']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="eval-section-divider">
          <i class="fa-solid fa-comment-dots"></i> Open-Ended Questions
        </div>

        <div class="eval-question">
          <div class="eq-label">
            <span class="eq-number" style="background:#7c3aed"><i class="fa-solid fa-heart" style="font-size:0.7rem"></i></span>
            What did you like most about the system?
          </div>
          <div class="eq-desc">Share what stood out to you — features, ease of use, content quality, etc.</div>
          <textarea class="eval-textarea" name="liked_most" rows="3" placeholder="I really liked..."><?= htmlspecialchars($existing['liked_most'] ?? '') ?></textarea>
        </div>

        <div class="eval-question">
          <div class="eq-label">
            <span class="eq-number" style="background:#f59e0b"><i class="fa-solid fa-lightbulb" style="font-size:0.7rem"></i></span>
            What improvements would you suggest?
          </div>
          <div class="eq-desc">Help us make the platform better — anything from UI, content, features, or performance.</div>
          <textarea class="eval-textarea" name="suggestions" rows="3" placeholder="I would suggest..."><?= htmlspecialchars($existing['suggestions'] ?? '') ?></textarea>
        </div>

        <div class="eval-question" style="border-bottom:none; margin-bottom:0;">
          <div class="eq-label">
            <span class="eq-number" style="background:#14b8a6"><i class="fa-solid fa-message" style="font-size:0.7rem"></i></span>
            Additional Feedback (Optional)
          </div>
          <div class="eq-desc">Any other suggestions or comments about the platform?</div>
          <textarea class="eval-textarea" name="feedback" rows="3" placeholder="Your feedback here..."><?= htmlspecialchars($existing['feedback'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="eval-submit">
          <i class="fa-solid fa-paper-plane" style="margin-right:0.5rem"></i>
          <?= $existing ? 'Update Evaluation' : 'Submit Evaluation' ?>
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function updateProgress() {
  const questions = document.querySelectorAll('.eval-question[data-question]');
  const steps = document.querySelectorAll('#evalProgress .step');
  questions.forEach((q, i) => {
    const checked = q.querySelector('input[type="radio"]:checked');
    if (steps[i]) {
      steps[i].classList.toggle('filled', !!checked);
    }
  });
}
// Initialize on load
document.addEventListener('DOMContentLoaded', updateProgress);
</script>

<?php include 'includes/footer.php'; ?>
