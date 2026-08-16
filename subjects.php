<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin();
$pageTitle = 'Subjects — RBAPS';
$db = getDB();

// Get subjects with question counts
$subjects = $db->query("
  SELECT sn.id, sn.name,
    COUNT(DISTINCT sy.id) as year_count,
    COUNT(q.id) as q_count,
    GROUP_CONCAT(DISTINCT sy.category SEPARATOR ',') as categories
  FROM subjectname sn
  LEFT JOIN subjectyear sy ON sy.subjectnamrid = sn.id
  LEFT JOIN questions q ON q.subjectyear_id = sy.id AND q.correct_option IS NOT NULL
  GROUP BY sn.id, sn.name
  ORDER BY q_count DESC
")->fetchAll();

$icons = ['Chemistry'=>'fa-flask','Physics'=>'fa-bolt','Mathematics'=>'fa-square-root-variable','Biology'=>'fa-dna','English'=>'fa-book-open',
          'Government'=>'fa-landmark','Economics'=>'fa-chart-line','Geography'=>'fa-earth-africa','History'=>'fa-scroll','ICT'=>'fa-laptop-code',
          'civic'=>'fa-person-booth','Commerce'=>'fa-store','Technical Drawing'=>'fa-ruler-combined','Financial accounting'=>'fa-coins'];
$grads = ['Chemistry'=>'linear-gradient(135deg,#ff6b35,#f7c59f)','Physics'=>'linear-gradient(135deg,#4f8ef7,#7b5cf0)',
          'Mathematics'=>'linear-gradient(135deg,#00c896,#0097a7)','Biology'=>'linear-gradient(135deg,#56ab2f,#a8e063)',
          'English'=>'linear-gradient(135deg,#f5c842,#f09819)','Government'=>'linear-gradient(135deg,#cc2b5e,#753a88)',
          'Economics'=>'linear-gradient(135deg,#00d2ff,#3a7bd5)','Geography'=>'linear-gradient(135deg,#4ecdc4,#1a535c)',
          'History'=>'linear-gradient(135deg,#d4a017,#8b5e00)','ICT'=>'linear-gradient(135deg,#7b5cf0,#4f8ef7)',
          'civic'=>'linear-gradient(135deg,#e96c1e,#ffd700)','Commerce'=>'linear-gradient(135deg,#11998e,#38ef7d)'];

include 'includes/header.php';
?>
<div class="section">
  <div class="section-header">
    <h2>All Subjects</h2>
    <p>Choose a subject to start practising or view available questions</p>
  </div>

  <!-- Filter -->
  <div class="filter-bar">
    <button class="filter-btn active" data-filter="all">All</button>
    <button class="filter-btn" data-filter="utme">UTME</button>
    <button class="filter-btn" data-filter="ssce">SSCE</button>
  </div>

  <div class="card-grid" id="subjectGrid">
    <?php foreach($subjects as $s):
      $icon = $icons[$s['name']] ?? 'fa-book';
      $grad = $grads[$s['name']] ?? 'linear-gradient(135deg,var(--accent),var(--accent2))';
      $cats = array_filter(explode(',', $s['categories'] ?? ''));
      $hasUtme = in_array('utme',$cats);
      $hasSsce = in_array('ssce',$cats);
    ?>
    <a href="practice.php?subject=<?= urlencode($s['name']) ?>" class="subject-card" data-cats="<?= implode(',',$cats) ?>"
         style="--card-color:<?= $grad ?>;text-decoration:none;display:block">
      <div class="icon" style="background:rgba(255,255,255,0.06)"><i class="fa-solid <?= $icon ?>"></i></div>
      <h3><?= htmlspecialchars($s['name']) ?></h3>
      <p><?= number_format($s['q_count']) ?> questions &nbsp;•&nbsp; <?= $s['year_count'] ?> year sets</p>
      <div class="meta" style="margin-top:.75rem">
        <?php if($hasUtme): ?><span class="badge badge-blue">UTME</span><?php endif; ?>
        <?php if($hasSsce): ?><span class="badge badge-green">SSCE</span><?php endif; ?>
      </div>
      <div style="display:flex;gap:.5rem;margin-top:1rem">
        <span class="btn btn-primary btn-sm" style="flex:1;justify-content:center">Practice</span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<script>
document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const f = btn.dataset.filter;
    document.querySelectorAll('.subject-card').forEach(card => {
      const cats = card.dataset.cats || '';
      card.style.display = (f==='all' || cats.includes(f)) ? '' : 'none';
    });
  });
});
</script>
<?php include 'includes/footer.php'; ?>
