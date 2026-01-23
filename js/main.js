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

  const wizard = document.querySelector('[data-quote-wizard]');
  if (wizard) {
    const steps = Array.from(wizard.querySelectorAll('.wizard-step'));
    const progressSteps = Array.from(
      document.querySelectorAll('.wizard-progress-step')
    );
    const nextButton = wizard.querySelector('[data-wizard-next]');
    const backButton = wizard.querySelector('[data-wizard-back]');
    const submitButton = wizard.querySelector('[data-wizard-submit]');
    const successPanel = document.querySelector('[data-wizard-success]');
    let currentStep = 0;

    const updateOptionStates = () => {
      const inputs = wizard.querySelectorAll(
        '.wizard-option input[type=\"radio\"], .wizard-option input[type=\"checkbox\"]'
      );
      inputs.forEach((input) => {
        const option = input.closest('.wizard-option');
        if (!option) return;
        if (input.type === 'radio') {
          const group = wizard.querySelectorAll(
            `input[name=\"${input.name}\"]`
          );
          group.forEach((radio) => {
            radio.closest('.wizard-option')?.classList.toggle(
              'is-selected',
              radio.checked
            );
          });
        } else {
          option.classList.toggle('is-selected', input.checked);
        }
      });
    };

    const isStepValid = () => {
      const step = steps[currentStep];
      if (!step) return false;
      const requiredInputs = Array.from(step.querySelectorAll('[required]'));
      const hasMissingRequired = requiredInputs.some((input) => {
        if (input.type === 'radio') {
          return !step.querySelector(`input[name=\"${input.name}\"]:checked`);
        }
        return !input.value.trim();
      });

      if (step.dataset.step === '2') {
        const checked = step.querySelector(
          'input[name=\"servicesNeeded\"]:checked'
        );
        return !hasMissingRequired && Boolean(checked);
      }

      if (step.dataset.step === '4') {
        const checked = step.querySelector(
          'input[name=\"serviceReasons\"]:checked'
        );
        return !hasMissingRequired && Boolean(checked);
      }

      return !hasMissingRequired;
    };

    const updateWizard = () => {
      steps.forEach((step, index) => {
        step.classList.toggle('is-active', index === currentStep);
      });
      progressSteps.forEach((step, index) => {
        step.classList.toggle('is-active', index === currentStep);
        step.classList.toggle('is-complete', index < currentStep);
      });
      if (backButton) {
        backButton.disabled = currentStep === 0;
      }
      if (nextButton) {
        nextButton.style.display =
          currentStep === steps.length - 1 ? 'none' : 'inline-flex';
        nextButton.disabled = !isStepValid();
      }
      if (submitButton) {
        submitButton.style.display =
          currentStep === steps.length - 1 ? 'inline-flex' : 'none';
        submitButton.disabled = !isStepValid();
      }
    };

    wizard.addEventListener('input', () => {
      updateOptionStates();
      updateWizard();
    });

    wizard.addEventListener('change', () => {
      updateOptionStates();
      updateWizard();
    });

    if (nextButton) {
      nextButton.addEventListener('click', () => {
        if (!isStepValid()) return;
        currentStep = Math.min(currentStep + 1, steps.length - 1);
        updateWizard();
        const behavior = prefersReducedMotion ? 'auto' : 'smooth';
        steps[currentStep]?.scrollIntoView({ behavior, block: 'start' });
      });
    }

    if (backButton) {
      backButton.addEventListener('click', () => {
        currentStep = Math.max(currentStep - 1, 0);
        updateWizard();
        const behavior = prefersReducedMotion ? 'auto' : 'smooth';
        steps[currentStep]?.scrollIntoView({ behavior, block: 'start' });
      });
    }

    wizard.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!isStepValid()) return;
      const data = new FormData(wizard);
      const lines = [
        `Facility Type: ${data.get('facilityType') || ''}`,
        `Services Needed: ${(data.getAll('servicesNeeded') || []).join(', ')}`,
        `Square Footage: ${data.get('squareFootage') || ''}`,
        `City/State: ${data.get('cityState') || ''}`,
        `Timeframe: ${data.get('timeframe') || ''}`,
        `Scope Description: ${data.get('scopeDescription') || ''}`,
        `Service Reasons: ${(data.getAll('serviceReasons') || []).join(', ')}`,
        `Full Name: ${data.get('fullName') || ''}`,
        `Company: ${data.get('company') || ''}`,
        `Email: ${data.get('email') || ''}`,
        `Phone: ${data.get('phone') || ''}`,
        `Preferred Contact: ${data.get('preferredContact') || ''}`,
        `Best Time: ${data.get('bestTime') || ''}`,
      ];
      const subject = encodeURIComponent('Quote Request - SOLUTIONS EA LLC');
      const body = encodeURIComponent(lines.join('\n'));
      window.location.href = `mailto:sales@solutionseallc.com?subject=${subject}&body=${body}`;
      wizard.classList.add('is-hidden');
      if (successPanel) {
        successPanel.classList.add('is-visible');
      }
    });

    updateOptionStates();
    updateWizard();
  }
})();
