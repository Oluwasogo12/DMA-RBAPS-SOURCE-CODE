// RBAPS — Main JS v3

document.addEventListener('DOMContentLoaded', () => {

  /* ── Navbar scroll shadow ── */
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    const onScroll = () => navbar.classList.toggle('scrolled', window.scrollY > 10);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ── Mobile hamburger ── */
  const ham = document.getElementById('nav-hamburger');
  const navLinks = document.querySelector('.nav-links');
  const navOverlay = document.getElementById('nav-overlay');
  const navCloseBtn = document.getElementById('nav-close-btn');
  
  if (ham && navLinks) {
    const toggleMenu = (forceClose = false) => {
      const open = forceClose ? false : !ham.classList.contains('open');
      ham.classList.toggle('open', open);
      navLinks.classList.toggle('open', open);
      if (navOverlay) navOverlay.classList.toggle('open', open);
      ham.setAttribute('aria-expanded', open);
      document.body.style.overflow = open ? 'hidden' : '';
    };

    ham.addEventListener('click', () => toggleMenu());
    if (navOverlay) navOverlay.addEventListener('click', () => toggleMenu(true));
    if (navCloseBtn) navCloseBtn.addEventListener('click', () => toggleMenu(true));

    navLinks.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => toggleMenu(true));
    });
  }

  /* ── Auto-dismiss alerts ── */
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s ease, transform .5s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(() => el.remove(), 500);
    }, 6000);
  });

  /* ── Scroll reveal ── */
  const revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('visible');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.07 });
    revealEls.forEach(el => io.observe(el));
  }

  /* ── Stagger card animations ── */
  const cardObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        cardObserver.unobserve(e.target);
      }
    });
  }, { threshold: 0.05 });

  document.querySelectorAll('.card-grid > *, .dashboard-grid > *').forEach((el, i) => {
    el.style.transitionDelay = `${i * 55}ms`;
    el.classList.add('reveal');
    const rect = el.getBoundingClientRect();
    if (rect.top < window.innerHeight) {
      // Already in viewport — make visible after stagger delay
      setTimeout(() => el.classList.add('visible'), i * 55 + 50);
    } else {
      // Below fold — use observer so it animates on scroll
      cardObserver.observe(el);
    }
  });

  /* ── Count-up animation ── */
  document.querySelectorAll('.stat-item .val, .metric-val').forEach(el => {
    const raw = el.textContent.replace(/[^0-9.]/g, '');
    const target = parseFloat(raw);
    if (!target || target < 2) return;
    const suffix = el.textContent.replace(raw, '');
    const duration = 1100;
    let start = null;
    const step = (ts) => {
      if (!start) start = ts;
      const prog = Math.min((ts - start) / duration, 1);
      const ease = 1 - Math.pow(1 - prog, 3);
      const val = target * ease;
      el.textContent = (val >= 100 ? Math.round(val).toLocaleString() : val.toFixed(val < 10 ? 1 : 0)) + suffix;
      if (prog < 1) requestAnimationFrame(step);
    };
    const obs = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) { requestAnimationFrame(step); obs.disconnect(); }
    });
    obs.observe(el);
  });

  /* ── Smooth mastery bar widths on load ── */
  const masteryFills = document.querySelectorAll('.mastery-bar-fill');
  if (masteryFills.length) {
    const io2 = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          const targetW = e.target.dataset.width || e.target.style.width;
          e.target.style.width = '0';
          requestAnimationFrame(() => {
            requestAnimationFrame(() => { e.target.style.width = targetW; });
          });
          io2.unobserve(e.target);
        }
      });
    }, { threshold: 0.2 });
    masteryFills.forEach(el => {
      const w = el.style.width;
      el.dataset.width = w;
      el.style.width = '0';
      io2.observe(el);
    });
  }

  /* ── Theme toggle ── */
  const themeToggle = document.getElementById('theme-toggle');
  if (themeToggle) {
    const updateIcon = () => {
      if (document.documentElement.getAttribute('data-theme') === 'dark') {
        themeToggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
      } else {
        themeToggle.innerHTML = '<i class="fa-solid fa-moon"></i>';
      }
    };
    updateIcon(); // init

    themeToggle.addEventListener('click', (e) => {
      e.preventDefault();
      if (document.documentElement.getAttribute('data-theme') === 'dark') {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('rbaps_theme', 'light');
      } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('rbaps_theme', 'dark');
      }
      updateIcon();
    });
  }

});
