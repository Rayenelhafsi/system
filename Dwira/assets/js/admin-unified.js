(function () {
    function normalizePath(pathname) {
        return (pathname || '').toLowerCase().replace(/\/+/g, '/').replace(/\/$/, '');
    }

    function getTheme() {
        var saved = localStorage.getItem('dwira-theme');
        return saved === 'light' ? 'light' : 'dark';
    }

    function iconForHref(href) {
        var h = (href || '').toLowerCase();
        if (h.indexOf('dashboard.php') !== -1) return 'bi-speedometer2';
        if (h.indexOf('/biens/') !== -1) return 'bi-house';
        if (h.indexOf('/demandes/') !== -1) return 'bi-people';
        if (h.indexOf('/matches/') !== -1) return 'bi-link-45deg';
        if (h.indexOf('/visites/') !== -1) return 'bi-calendar-event';
        if (h.indexOf('/suivi_commercial/') !== -1) return 'bi-telephone';
        if (h.indexOf('/caracteristiques/') !== -1) return 'bi-star';
        if (h.indexOf('logout.php') !== -1) return 'bi-box-arrow-right';
        return '';
    }

    function cleanLabelText(text) {
        return (text || '')
            .replace(/[\u{1F300}-\u{1FAFF}\u2600-\u27BF]/gu, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function removeDecorativeEmoji(root) {
        if (!root) {
            return;
        }

        var targets = root.querySelectorAll('h1, h2, h3, .brand, .navbar-brand, .section-title');
        targets.forEach(function (el) {
            var cleaned = cleanLabelText(el.textContent);
            if (cleaned) {
                el.textContent = cleaned;
            }
        });
    }

    function getTitleIconClass(text) {
        var t = (text || '').toLowerCase();
        if (t.indexOf('dashboard') !== -1) return 'bi-speedometer2';
        if (t.indexOf('visite') !== -1) return 'bi-calendar-event';
        if (t.indexOf('match') !== -1) return 'bi-link-45deg';
        if (t.indexOf('demande') !== -1) return 'bi-people';
        if (t.indexOf('suivi commercial') !== -1) return 'bi-telephone';
        if (t.indexOf('notification') !== -1) return 'bi-bell';
        if (t.indexOf('biens') !== -1 || t.indexOf('bien') !== -1) return 'bi-house';
        if (t.indexOf('caract') !== -1) return 'bi-star';
        if (t.indexOf('dwira') !== -1) return 'bi-buildings';
        return '';
    }

    function decorateTitlesWithBootstrapIcons(root) {
        if (!root) {
            return;
        }

        var selectors = 'h1, h2, h3, .brand, .navbar-brand, .section-title';
        var targets = root.querySelectorAll(selectors);

        targets.forEach(function (el) {
            if (el.querySelector('.admin-title-icon')) {
                return;
            }

            var text = cleanLabelText(el.textContent);
            if (!text) {
                return;
            }

            var iconClass = getTitleIconClass(text);
            if (!iconClass) {
                return;
            }

            el.textContent = text;

            var icon = document.createElement('i');
            icon.className = 'bi ' + iconClass + ' admin-title-icon';
            icon.setAttribute('aria-hidden', 'true');
            el.insertBefore(icon, el.firstChild);
            el.insertBefore(document.createTextNode(' '), icon.nextSibling);
        });
    }

    function normalizeSidebarIcons(sidebar) {
        var navLinks = sidebar.querySelectorAll('a[href]');
        navLinks.forEach(function (link) {
            if (link.hasAttribute('data-admin-theme-toggle')) {
                return;
            }

            var href = link.getAttribute('href') || '';
            var iconClass = iconForHref(href);
            if (!iconClass) {
                return;
            }

            var icon = link.querySelector('i');
            if (!icon) {
                icon = document.createElement('i');
                link.insertBefore(icon, link.firstChild);
            }
            icon.className = 'bi ' + iconClass;

            if (!link.querySelector('.admin-nav-label')) {
                var badges = Array.prototype.slice.call(link.querySelectorAll('.badge'));
                var labelText = cleanLabelText(link.textContent);

                link.textContent = '';
                link.appendChild(icon);

                var label = document.createElement('span');
                label.className = 'admin-nav-label';
                label.textContent = labelText;
                link.appendChild(label);

                badges.forEach(function (badge) {
                    link.appendChild(document.createTextNode(' '));
                    link.appendChild(badge);
                });
            }
        });
    }

    function applyTheme(theme) {
        var body = document.body;
        if (!body) {
            return;
        }

        body.classList.toggle('theme-light', theme === 'light');
        localStorage.setItem('dwira-theme', theme);

        var switchInput = document.getElementById('admin-theme-switch-input');
        if (switchInput) {
            switchInput.checked = theme === 'light';
        }

        var label = document.getElementById('admin-theme-switch-label');
        if (label) {
            label.textContent = theme === 'light' ? 'Clair' : 'Sombre';
        }

        var sidebarToggles = document.querySelectorAll('[data-admin-theme-toggle]');
        sidebarToggles.forEach(function (toggle) {
            var txt = toggle.querySelector('span');
            var icon = toggle.querySelector('i');
            if (txt) {
                txt.textContent = theme === 'light' ? 'Mode sombre' : 'Mode clair';
            }
            if (icon) {
                icon.className = theme === 'light' ? 'bi bi-moon-stars' : 'bi bi-sun';
            }
        });
    }

    function toggleTheme() {
        var next = document.body.classList.contains('theme-light') ? 'dark' : 'light';
        applyTheme(next);
    }

    window.toggleTheme = toggleTheme;

    document.addEventListener('DOMContentLoaded', function () {
        var body = document.body;
        var sidebar = document.querySelector('.sidebar');

        if (!body || !sidebar) {
            return;
        }

        body.classList.add('admin-shell-ready');
        applyTheme(getTheme());
        removeDecorativeEmoji(document);
        decorateTitlesWithBootstrapIcons(document);

        var main = document.getElementById('main') || document.querySelector('.main') || document.querySelector('.content');
        if (main) {
            main.classList.add('admin-main');
            main.classList.remove('expanded');
        }

        var topbar = document.getElementById('admin-topbar');
        if (!topbar && main) {
            topbar = document.createElement('div');
            topbar.id = 'admin-topbar';

            var titleSource = main.querySelector('h1, h2, h3');
            var titleText = titleSource ? cleanLabelText(titleSource.textContent.trim()) : 'DWIRA Admin';

            topbar.innerHTML = '' +
                '<div class="admin-topbar__title">' + titleText + '</div>' +
                '<label class="admin-theme-switch" for="admin-theme-switch-input">' +
                '<input id="admin-theme-switch-input" type="checkbox" aria-label="Changer le mode clair/sombre">' +
                '<span class="admin-theme-switch__track"><span class="admin-theme-switch__thumb"></span></span>' +
                '<span id="admin-theme-switch-label" class="admin-theme-switch__label">Sombre</span>' +
                '</label>';

            main.insertBefore(topbar, main.firstChild);
        }

        var themeSwitchInput = document.getElementById('admin-theme-switch-input');
        if (themeSwitchInput) {
            themeSwitchInput.checked = getTheme() === 'light';
            themeSwitchInput.addEventListener('change', function () {
                applyTheme(themeSwitchInput.checked ? 'light' : 'dark');
            });
        }

        function ensureSidebarThemeToggle() {
            var existing = sidebar.querySelector('[data-admin-theme-toggle], .theme-toggle, [onclick*="toggleTheme"]');
            if (!existing) {
                var link = document.createElement('a');
                link.href = '#';
                link.setAttribute('data-admin-theme-toggle', '1');
                link.innerHTML = '<i class="bi bi-moon-stars"></i> <span>Mode sombre</span>';

                var logoutLink = sidebar.querySelector('a[href*="logout.php"]');
                if (logoutLink) {
                    sidebar.insertBefore(link, logoutLink);
                } else {
                    sidebar.appendChild(link);
                }
            }

            var toggles = sidebar.querySelectorAll('[data-admin-theme-toggle], .theme-toggle, [onclick*="toggleTheme"]');
            toggles.forEach(function (el) {
                el.setAttribute('data-admin-theme-toggle', '1');
                el.removeAttribute('onclick');
            });

            return toggles;
        }

        var themeLinks = ensureSidebarThemeToggle();
        normalizeSidebarIcons(sidebar);
        themeLinks.forEach(function (el) {
            el.addEventListener('click', function (ev) {
                ev.preventDefault();
                toggleTheme();
            });
        });

        applyTheme(getTheme());

        var overlay = document.getElementById('admin-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'admin-overlay';
            body.appendChild(overlay);
        }

        var toggleBtn = document.getElementById('admin-menu-toggle');
        if (!toggleBtn) {
            toggleBtn = document.createElement('button');
            toggleBtn.id = 'admin-menu-toggle';
            toggleBtn.type = 'button';
            toggleBtn.setAttribute('aria-label', 'Ouvrir le menu');
            toggleBtn.innerHTML = '<i class="bi bi-list" aria-hidden="true"></i>';
            body.appendChild(toggleBtn);
        }

        function isMobile() {
            return window.matchMedia('(max-width: 991.98px)').matches;
        }

        function setOpenState(opened) {
            if (!isMobile()) {
                sidebar.classList.remove('is-open');
                body.classList.remove('sidebar-open');
                if (main) {
                    main.classList.remove('expanded');
                }
                toggleBtn.setAttribute('aria-expanded', 'false');
                return;
            }

            sidebar.classList.toggle('is-open', opened);
            body.classList.toggle('sidebar-open', opened);
            if (main) {
                main.classList.remove('expanded');
            }
            toggleBtn.setAttribute('aria-expanded', opened ? 'true' : 'false');
        }

        function toggleSidebar() {
            if (!isMobile()) {
                return;
            }
            setOpenState(!sidebar.classList.contains('is-open'));
        }

        window.toggleSidebar = toggleSidebar;

        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', function () {
            setOpenState(false);
        });

        window.addEventListener('resize', function () {
            setOpenState(false);
        });

        var currentPath = normalizePath(window.location.pathname);
        var navLinks = sidebar.querySelectorAll('a[href]');

        navLinks.forEach(function (link) {
            var href = link.getAttribute('href');
            if (!href || href.indexOf('javascript:') === 0 || href === '#') {
                return;
            }

            var resolvedPath = normalizePath(new URL(href, window.location.href).pathname);
            var isActive = false;

            if (currentPath === resolvedPath) {
                isActive = true;
            } else {
                var sectionMatch = resolvedPath.match(/\/admin\/([^\/]+)\//);
                if (sectionMatch && currentPath.indexOf('/admin/' + sectionMatch[1] + '/') !== -1) {
                    isActive = true;
                }
                if (resolvedPath.endsWith('/admin/dashboard.php') && currentPath.endsWith('/admin/dashboard.php')) {
                    isActive = true;
                }
            }

            if (isActive) {
                link.classList.add('is-active');
            }

            link.addEventListener('click', function () {
                if (isMobile()) {
                    setOpenState(false);
                }
            });
        });

        setOpenState(false);
    });
})();
