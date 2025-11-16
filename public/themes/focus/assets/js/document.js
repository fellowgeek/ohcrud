// Generate Table of Contents
function generateTOC() {
    const content = document.querySelector('.doc-content');
    const tocList = document.getElementById('toc-list');
    const headings = content.querySelectorAll('h2, h3');

    let currentSection = null;

    headings.forEach((heading, index) => {
        // Generate ID if not present
        if (!heading.id) {
            heading.id = heading.textContent
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
        }

        if (heading.tagName === 'H2') {
            // Create new section
            const sectionLi = document.createElement('li');
            sectionLi.className = 'toc-section';

            const link = document.createElement('a');
            link.href = `#${heading.id}`;
            link.innerHTML = `
                <span class="toc-section-text">${heading.textContent}</span>
                <span class="toc-toggle" aria-hidden="true">▼</span>
            `;

            const subsectionUl = document.createElement('ul');
            subsectionUl.className = 'toc-subsections';

            sectionLi.appendChild(link);
            sectionLi.appendChild(subsectionUl);
            tocList.appendChild(sectionLi);

            currentSection = { li: sectionLi, ul: subsectionUl, link: link };
        } else if (heading.tagName === 'H3' && currentSection) {
            // Add subsection to current section
            const subsectionLi = document.createElement('li');
            const link = document.createElement('a');
            link.href = `#${heading.id}`;
            link.textContent = heading.textContent;

            subsectionLi.appendChild(link);
            currentSection.ul.appendChild(subsectionLi);
        }
    });

    // Hide toggle arrows for sections without subsections
    document.querySelectorAll('.toc-section').forEach(section => {
        const subsections = section.querySelector('.toc-subsections');
        const toggle = section.querySelector('.toc-toggle');
        if (subsections && subsections.children.length === 0 && toggle) {
            toggle.style.display = 'none';
        }
    });
}

// Toggle sections
function initializeToggles() {
    const tocList = document.getElementById('toc-list');

    tocList.addEventListener('click', (e) => {
        const toggleIcon = e.target.closest('.toc-toggle');
        const sectionLink = e.target.closest('.toc-section > a');

        if (!sectionLink) return;

        const section = sectionLink.parentElement;
        const hasSubsections = section.querySelector('.toc-subsections').children.length > 0;

        // If clicking the toggle icon, collapse/expand
        if (toggleIcon && hasSubsections) {
            e.preventDefault();
            section.classList.toggle('collapsed');
        }
        // If clicking the text and has subsections, navigate but don't prevent default
        // If no subsections, just navigate normally
    });
}

// Highlight active section on scroll
function highlightActiveSection() {
    const headings = document.querySelectorAll('.doc-content h2, .doc-content h3');
    const tocLinks = document.querySelectorAll('.toc-list a');

    function updateActiveLinks() {
        let activeHeading = null;

        // Find the heading that is currently at the top of the viewport (within 200px)
        headings.forEach((heading) => {
            const rect = heading.getBoundingClientRect();
            // Check if heading is in the top 200px of the viewport
            if (rect.top >= 0 && rect.top <= 200) {
                activeHeading = heading;
            }
        });

        // If no heading in top 200px, find the last one that passed the top
        if (!activeHeading) {
            for (let i = headings.length - 1; i >= 0; i--) {
                const rect = headings[i].getBoundingClientRect();
                if (rect.top < 200) {
                    activeHeading = headings[i];
                    break;
                }
            }
        }

        // Update active states
        tocLinks.forEach((link) => link.classList.remove('active'));

        if (activeHeading) {
            const id = activeHeading.id;
            tocLinks.forEach((link) => {
                if (link.getAttribute('href') === `#${id}`) {
                    link.classList.add('active');
                }
            });
        }
    }

    // Update on scroll and initially
    document.addEventListener('scroll', updateActiveLinks, { passive: true });
    updateActiveLinks();
}

// Initialize everything
document.addEventListener('DOMContentLoaded', () => {
    generateTOC();
    initializeToggles();
    highlightActiveSection();
});
