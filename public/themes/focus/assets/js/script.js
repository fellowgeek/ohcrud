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
    btn.textContent = 'Copy';
    btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(code.innerText);
            const old = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => (btn.textContent = old), 1200);
        } catch {
            btn.textContent = 'Failed';
            setTimeout(() => (btn.textContent = 'Copy'), 1200);
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

const root = document.documentElement, KEY = 'theme';
const saved = localStorage.getItem(KEY);
const theme = saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
const applyTheme = t => {
    root.dataset.theme = t;
    const b = document.querySelector('.theme-toggle');
    if (b) { b.innerHTML = t === 'dark' ? '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 2v2m0 16v2M4 12H2m20 0h-2M5.64 5.64 4.22 4.22m15.56 15.56-1.42-1.42M18.36 5.64l1.42-1.42M5.64 18.36 4.22 19.78" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>' : '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>'; b.setAttribute('aria-label', `Activate ${t === 'dark' ? 'light' : 'dark'} mode`); } const m = document.querySelector('meta[name="color-scheme"]'); if (m) m.setAttribute('content', t);
};
applyTheme(theme);

document.addEventListener('click', e => {
    const btn = e.target.closest('.theme-toggle'); if (!btn) return;
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem(KEY, next); applyTheme(next);
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