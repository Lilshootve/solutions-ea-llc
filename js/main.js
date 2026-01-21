(() => {
  const toggle = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  const submenuToggle = document.querySelector('[data-submenu-toggle]');
  const submenu = document.querySelector('[data-submenu]');

  if (!toggle || !menu) return;

  const closeSubmenu = () => {
    if (!submenuToggle || !submenu) return;
    submenuToggle.setAttribute('aria-expanded', 'false');
    submenu.classList.remove('open');
    submenuToggle.parentElement?.classList.remove('open');
  };

  toggle.addEventListener('click', () => {
    const isOpen = menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(isOpen));
    if (!isOpen) {
      closeSubmenu();
    }
  });

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
})();
