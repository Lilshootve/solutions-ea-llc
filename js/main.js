(() => {
  const yearTargets = document.querySelectorAll('[data-year]');
  if (yearTargets.length) {
    const currentYear = String(new Date().getFullYear());
    yearTargets.forEach((node) => {
      node.textContent = currentYear;
    });
  }

  const prefersReducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
  ).matches;

  const toggle = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  const submenuToggle = document.querySelector('[data-submenu-toggle]');
  const submenu = document.querySelector('[data-submenu]');

  const closeSubmenu = () => {
    if (!submenuToggle || !submenu) return;
    submenuToggle.setAttribute('aria-expanded', 'false');
    submenu.classList.remove('open');
    submenuToggle.parentElement?.classList.remove('open');
  };

  if (toggle && menu) {
    toggle.addEventListener('click', () => {
      const isOpen = menu.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(isOpen));
      if (!isOpen) {
        closeSubmenu();
      }
    });
  }

  if (submenuToggle && submenu) {
    submenuToggle.addEventListener('click', () => {
      const isOpen = submenu.classList.toggle('open');
      submenuToggle.setAttribute('aria-expanded', String(isOpen));
      submenuToggle.parentElement?.classList.toggle('open', isOpen);
    });

    document.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Node)) return;
      if (!submenu.contains(target) && !submenuToggle.contains(target)) {
        closeSubmenu();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeSubmenu();
      }
    });
  }

  const revealTargets = document.querySelectorAll(
    '.section-title, .card, .feature-grid, .service-block, .cta-band .container, .media-card, .project-card'
  );
  revealTargets.forEach((target) => target.classList.add('reveal'));
  if (revealTargets.length && !prefersReducedMotion) {
    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('reveal--visible');
            obs.unobserve(entry.target);
          }
        });
      },
      {
        threshold: 0.15,
        rootMargin: '0px 0px -10% 0px',
      }
    );
    revealTargets.forEach((target) => observer.observe(target));
  } else {
    revealTargets.forEach((target) => target.classList.add('reveal--visible'));
  }

  const header = document.querySelector('.site-header');
  if (header) {
    let ticking = false;
    const updateHeader = () => {
      header.classList.toggle('scrolled', window.scrollY > 16);
      ticking = false;
    };
    const onScroll = () => {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(updateHeader);
      }
    };
    updateHeader();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  const anchorLinks = document.querySelectorAll('a[href^="#"]');
  anchorLinks.forEach((link) => {
    const hash = link.getAttribute('href');
    if (!hash || hash.length < 2) return;
    const target = document.querySelector(hash);
    if (!target) return;
    link.addEventListener('click', (event) => {
      if (prefersReducedMotion) return;
      event.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      history.pushState(null, '', hash);
    });
  });

  const parallaxSections = document.querySelectorAll(
    '.section-themed, .cta-band, .legal-hero'
  );
  if (parallaxSections.length) {
    parallaxSections.forEach((section) => section.classList.add('parallax-bg'));
  }
  if (!prefersReducedMotion && parallaxSections.length) {
    let ticking = false;
    const updateParallax = () => {
      const offset = Math.round(window.scrollY * 0.08);
      parallaxSections.forEach((section) => {
        section.style.backgroundPosition = `center ${-offset}px`;
      });
      ticking = false;
    };
    const onScroll = () => {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(updateParallax);
      }
    };
    updateParallax();
    window.addEventListener('scroll', onScroll, { passive: true });
  }
})();
