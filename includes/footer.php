<footer role="contentinfo">
  <div class="footer-inner" style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:2rem;">
    <div style="flex: 1; min-width:280px; max-width: 400px;">
      <div class="footer-brand" style="margin-bottom:0.5rem;">
        <div class="footer-logo"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></div>
        <span>RBAPS</span>
      </div>
      <p style="font-size:0.82rem; color:var(--text2); line-height:1.6; margin-bottom:0.5rem;">Dynamic Mastery Assessment &mdash; Rule-Based Adaptive Personalised Practice for UTME &amp; SSCE.</p>
      <p style="font-size:0.75rem; color:var(--text3);">Redeemer's University, Ede, Osun State &bull; Faculty of Computing</p>
    </div>
    
    <div class="footer-links" style="display:flex; gap:4rem; flex-wrap:wrap;">
      <div style="display:flex; flex-direction:column; gap:0.5rem;">
        <h4 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text); margin-bottom:0.25rem;">Platform</h4>
        <a href="index.php" style="color:var(--text2); font-size:0.85rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--text2)'">Home</a>
        <a href="practice.php" style="color:var(--text2); font-size:0.85rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--text2)'">Adaptive Practice</a>
        <a href="syllabus.php" style="color:var(--text2); font-size:0.85rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--text2)'">Syllabus</a>
      </div>
      <div style="display:flex; flex-direction:column; gap:0.5rem;">
        <h4 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text); margin-bottom:0.25rem;">Support</h4>
        <a href="#" style="color:var(--text2); font-size:0.85rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--text2)'">How it Works</a>
        <a href="evaluate.php" style="color:var(--text2); font-size:0.85rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--text2)'">Project Evaluation</a>
        <a href="admin/dashboard.php" style="color:var(--text2); font-size:0.85rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--text2)'">Admin Panel</a>
      </div>
    </div>
  </div>
  <div style="text-align:center; padding-top:1.5rem; margin-top:2rem; border-top:1px solid var(--border-soft); font-size:0.75rem; color:var(--text3); font-weight:500;">
    &copy; <?= date('Y') ?> RBAPS Project. All rights reserved.
  </div>
</footer>
<script src="js/main.js?v=<?= time() ?>"></script>
</body>
</html>
