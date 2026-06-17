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
    const formAlert = wizard.querySelector('[data-form-alert]');
    const recaptchaContainer = wizard.querySelector('[data-recaptcha-container]');
    const recaptchaWidgetTarget = wizard.querySelector('[data-recaptcha-widget]');
    const endpoint =
      wizard.getAttribute('data-quote-endpoint') || '/api/submit-quote.php';
    let currentStep = 0;
    let isSubmitting = false;
    let recaptchaSiteKey = '';
    let recaptchaWidgetId = null;

    const showAlert = (message, type = 'error') => {
      if (!formAlert) return;
      formAlert.textContent = message;
      formAlert.classList.remove('is-error', 'is-success');
      formAlert.classList.add(type === 'success' ? 'is-success' : 'is-error');
      formAlert.hidden = !message;
    };

    const clearFieldErrors = () => {
      wizard.querySelectorAll('.has-error').forEach((node) => {
        node.classList.remove('has-error');
      });
      wizard.querySelectorAll('[data-field-error]').forEach((node) => {
        node.remove();
      });
    };

    const showFieldErrors = (errors) => {
      if (!errors || typeof errors !== 'object') return;
      Object.entries(errors).forEach(([field, message]) => {
        const input = wizard.querySelector(`[name="${field}"]`);
        if (!input) return;
        const wrapper = input.closest('div') || input.parentElement;
        wrapper?.classList.add('has-error');
        const errorNode = document.createElement('p');
        errorNode.className = 'field-error';
        errorNode.dataset.fieldError = field;
        errorNode.textContent = String(message);
        wrapper?.appendChild(errorNode);
      });
    };

    const isOtherSelected = (name, value = 'Other') => {
      const selected = wizard.querySelector(`input[name="${name}"]:checked`);
      return selected?.value === value;
    };

    const isOtherChecked = (name, value = 'Other') => {
      return Boolean(
        wizard.querySelector(`input[name="${name}"][value="${value}"]:checked`)
      );
    };

    const updateConditionalFields = () => {
      const facilityOther = wizard.querySelector('[data-conditional="facilityTypeOther"]');
      if (facilityOther) {
        const show = isOtherSelected('facilityType');
        facilityOther.hidden = !show;
        const input = facilityOther.querySelector('[data-conditional-required]');
        if (input) {
          input.required = show;
          if (!show) input.value = '';
        }
      }

      wizard.querySelectorAll('[data-triggers-other]').forEach((trigger) => {
        const targetName = trigger.getAttribute('data-triggers-other');
        if (!targetName) return;
        const block = wizard.querySelector(`[data-conditional="${targetName}"]`);
        if (!block) return;
        const show = trigger.checked;
        block.hidden = !show;
        const input = block.querySelector('[data-conditional-required]');
        if (input) {
          input.required = show;
          if (!show) input.value = '';
        }
      });

      if (recaptchaContainer) {
        const onFinalStep = currentStep === steps.length - 1;
        recaptchaContainer.hidden = !onFinalStep || !recaptchaSiteKey;
      }
    };

    const updateOptionStates = () => {
      const inputs = wizard.querySelectorAll(
        '.wizard-option input[type="radio"], .wizard-option input[type="checkbox"]'
      );
      inputs.forEach((input) => {
        const option = input.closest('.wizard-option');
        if (!option) return;
        if (input.type === 'radio') {
          const group = wizard.querySelectorAll(`input[name="${input.name}"]`);
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

      updateConditionalFields();

      const requiredInputs = Array.from(
        step.querySelectorAll('[required], [data-conditional-required]')
      ).filter((input) => {
        const conditional = input.closest('[data-conditional]');
        return !conditional || !conditional.hidden;
      });

      const hasMissingRequired = requiredInputs.some((input) => {
        if (input.type === 'radio') {
          return !step.querySelector(`input[name="${input.name}"]:checked`);
        }
        if (input.type === 'checkbox') {
          return false;
        }
        return !String(input.value).trim();
      });

      if (step.dataset.step === '1' && isOtherSelected('facilityType')) {
        const otherInput = wizard.querySelector('[name="facilityTypeOther"]');
        if (!otherInput?.value.trim()) return false;
      }

      if (step.dataset.step === '2') {
        const checked = step.querySelector('input[name="servicesNeeded"]:checked');
        if (!checked) return false;
        if (isOtherChecked('servicesNeeded')) {
          const otherInput = wizard.querySelector('[name="servicesOther"]');
          if (!otherInput?.value.trim()) return false;
        }
      }

      if (step.dataset.step === '4') {
        const checked = step.querySelector('input[name="serviceReasons"]:checked');
        if (!checked) return false;
        if (isOtherChecked('serviceReasons')) {
          const otherInput = wizard.querySelector('[name="serviceReasonsOther"]');
          if (!otherInput?.value.trim()) return false;
        }
      }

      return !hasMissingRequired;
    };

    const updateWizard = () => {
      updateConditionalFields();
      steps.forEach((step, index) => {
        step.classList.toggle('is-active', index === currentStep);
      });
      progressSteps.forEach((step, index) => {
        step.classList.toggle('is-active', index === currentStep);
        step.classList.toggle('is-complete', index < currentStep);
      });
      if (backButton) {
        backButton.disabled = currentStep === 0 || isSubmitting;
      }
      if (nextButton) {
        nextButton.style.display =
          currentStep === steps.length - 1 ? 'none' : 'inline-flex';
        nextButton.disabled = !isStepValid() || isSubmitting;
      }
      if (submitButton) {
        submitButton.style.display =
          currentStep === steps.length - 1 ? 'inline-flex' : 'none';
        submitButton.disabled = !isStepValid() || isSubmitting;
        submitButton.textContent = isSubmitting ? 'Submitting…' : 'Submit Request';
        submitButton.setAttribute('aria-busy', String(isSubmitting));
      }
    };

    const formDataToObject = (formData) => {
      const payload = {};
      formData.forEach((value, key) => {
        if (payload[key] !== undefined) {
          if (!Array.isArray(payload[key])) {
            payload[key] = [payload[key]];
          }
          payload[key].push(value);
        } else {
          payload[key] = value;
        }
      });
      return payload;
    };

    const renderRecaptcha = () => {
      if (
        !recaptchaSiteKey ||
        !recaptchaWidgetTarget ||
        recaptchaWidgetId !== null ||
        typeof window.grecaptcha === 'undefined'
      ) {
        return;
      }

      recaptchaWidgetId = window.grecaptcha.render(recaptchaWidgetTarget, {
        sitekey: recaptchaSiteKey,
        theme: 'light',
      });
      updateConditionalFields();
    };

    const getRecaptchaToken = () => {
      if (!recaptchaSiteKey || recaptchaWidgetId === null) {
        return '';
      }
      if (typeof window.grecaptcha === 'undefined') {
        return '';
      }
      return window.grecaptcha.getResponse(recaptchaWidgetId);
    };

    const resetRecaptcha = () => {
      if (recaptchaWidgetId !== null && typeof window.grecaptcha !== 'undefined') {
        window.grecaptcha.reset(recaptchaWidgetId);
      }
    };

    const loadFormConfig = async () => {
      try {
        const response = await fetch('/api/site-config.php', {
          headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (data?.recaptchaSiteKey) {
          recaptchaSiteKey = data.recaptchaSiteKey;
          if (typeof window.grecaptcha !== 'undefined') {
            renderRecaptcha();
          } else {
            window.addEventListener('load', renderRecaptcha, { once: true });
          }
        }
      } catch (_error) {
        showAlert(
          'Security verification could not be loaded. Please refresh the page or call us at 407-639-2669.'
        );
      }
    };

    wizard.addEventListener('input', () => {
      clearFieldErrors();
      showAlert('');
      updateOptionStates();
      updateWizard();
    });

    wizard.addEventListener('change', () => {
      clearFieldErrors();
      showAlert('');
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
        if (currentStep === steps.length - 1) {
          renderRecaptcha();
        }
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

    wizard.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!isStepValid() || isSubmitting) return;

      if (recaptchaSiteKey && !getRecaptchaToken()) {
        showAlert('Please complete the reCAPTCHA verification before submitting.');
        recaptchaContainer?.scrollIntoView({
          behavior: prefersReducedMotion ? 'auto' : 'smooth',
          block: 'center',
        });
        return;
      }

      isSubmitting = true;
      showAlert('');
      clearFieldErrors();
      updateWizard();

      const payload = formDataToObject(new FormData(wizard));
      payload.recaptchaToken = getRecaptchaToken();

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify(payload),
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok || !result.ok) {
          if (result.errors) {
            showFieldErrors(result.errors);
            const firstErrorStep = Object.keys(result.errors).reduce((found, field) => {
              if (found !== null) return found;
              const input = wizard.querySelector(`[name="${field}"]`);
              const step = input?.closest('.wizard-step');
              if (!step) return found;
              return steps.indexOf(step);
            }, null);
            if (firstErrorStep !== null && firstErrorStep >= 0) {
              currentStep = firstErrorStep;
              updateWizard();
              steps[currentStep]?.scrollIntoView({
                behavior: prefersReducedMotion ? 'auto' : 'smooth',
                block: 'start',
              });
            }
          }
          showAlert(
            result.message ||
              'We could not submit your request. Please try again or call 407-639-2669.'
          );
          resetRecaptcha();
          return;
        }

        wizard.classList.add('is-hidden');
        if (successPanel) {
          successPanel.classList.add('is-visible');
          successPanel.scrollIntoView({
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
            block: 'start',
          });
        }
      } catch (_error) {
        showAlert(
          'A network error occurred. Please check your connection or call us at 407-639-2669.'
        );
        resetRecaptcha();
      } finally {
        isSubmitting = false;
        updateWizard();
      }
    });

    loadFormConfig();
    updateOptionStates();
    updateWizard();
  }
})();
