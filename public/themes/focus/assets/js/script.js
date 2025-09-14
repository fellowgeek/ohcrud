// Progress bar
const bar = document.querySelector('.progress .bar');
const onScroll = () => {
    const el = document.querySelector('.post');
    if (!el) return;
    const top = el.getBoundingClientRect().top + window.scrollY;
    const height = el.scrollHeight - window.innerHeight;
    const y = Math.min(Math.max(window.scrollY - top, 0), height);
    const pct = height > 0 ? (y / height) * 100 : 0;
    bar.style.width = `${pct}%`;
};
document.addEventListener('scroll', onScroll, { passive: true });
window.addEventListener('resize', onScroll);
onScroll();

// Add copy buttons to code blocks
document.querySelectorAll('pre > code').forEach(code => {
    const pre = code.parentElement;
    const btn = document.createElement('button');
    btn.className = 'copy-btn';
    btn.type = 'button';
    btn.innerHTML = '<img width="24" height="24" src="/themes/focus/assets/img/stack.svg" />';
    // btn.textContent = 'Copy';

    btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(code.innerText);
            const old = btn.innerHTML;
            btn.innerHTML = '<img width="24" height="24" src="/themes/focus/assets/img/check.svg" />';
            setTimeout(() => (btn.innerHTML = old), 1200);
        } catch {
            btn.innerHTML = '<img width="24" height="24" src="/themes/focus/assets/img/stack_off.svg" />';
            setTimeout(() => (btn.innerHTML = '<img width="24" height="24" src="/themes/focus/assets/img/stack.svg" />'), 1200);
        }
    });
    pre.appendChild(btn);
});

// Smooth scroll for internal links
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const id = a.getAttribute('href');
        const target = document.querySelector(id);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

// Theme toggle
const root = document.documentElement;
const KEY = 'app:dark-mode';

// 1. Determine the initial theme
const savedTheme = localStorage.getItem(KEY);
let currentTheme;

if (savedTheme) {
    currentTheme = savedTheme === 'Y' ? 'dark' : 'light';
} else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
    currentTheme = 'dark';
} else {
    currentTheme = 'light';
}

// 2. Function to apply the theme
function applyTheme(themeToApply) {
    root.dataset.theme = themeToApply;

    const themeToggleBtn = document.querySelector('.theme-toggle');

    if (themeToggleBtn) {
        // Set the appropriate SVG icon and aria-label
        if (themeToApply === 'dark') {
            themeToggleBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 2v2m0 16v2M4 12H2m20 0h-2M5.64 5.64 4.22 4.22m15.56 15.56-1.42-1.42M18.36 5.64l1.42-1.42M5.64 18.36 4.22 19.78" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
            themeToggleBtn.setAttribute('aria-label', 'Activate light mode');
        } else {
            themeToggleBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>';
            themeToggleBtn.setAttribute('aria-label', 'Activate dark mode');
        }
    }

    const metaColorScheme = document.querySelector('meta[name="color-scheme"]');
    if (metaColorScheme) {
        metaColorScheme.setAttribute('content', themeToApply);
    }
}

// Apply the initial theme
applyTheme(currentTheme);

// 3. Add an event listener for clicks
document.addEventListener('click', (event) => {
    const clickedButton = event.target.closest('.theme-toggle');

    // Exit the function if the clicked element isn't the theme toggle button
    if (!clickedButton) {
        return;
    }

    // Toggle the theme and apply it
    const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem(KEY, nextTheme === 'dark' ? 'Y' : 'N');
    applyTheme(nextTheme);
});
document.addEventListener("DOMContentLoaded", function () {
  // Select all tables
  const tables = document.querySelectorAll("table");

  tables.forEach(table => {
    // Create wrapper div
    const wrapper = document.createElement("div");
    wrapper.className = "table-wrap";

    // Insert wrapper before the table
    table.parentNode.insertBefore(wrapper, table);

    // Move the table inside the wrapper
    wrapper.appendChild(table);
  });
});