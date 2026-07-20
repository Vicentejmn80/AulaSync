import './bootstrap';
import Alpine from 'alpinejs';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

marked.setOptions({ gfm: true, breaks: true });

// ── Lesson Template Definitions ──────────────────────────────────────────────
const LESSON_TEMPLATES = {
    clasica: {
        sections: ['INICIO', 'DESARROLLO', 'CIERRE'],
        colors:   ['#7C3AED', '#06B6D4', '#22C55E'],
    },
    directa: {
        sections: ['MOTIVACIÓN', 'PRESENTACIÓN', 'PRÁCTICA GUIADA', 'CIERRE REFLEXIVO'],
        colors:   ['#F59E0B', '#7C3AED', '#06B6D4', '#22C55E'],
    },
    constructivista: {
        sections: ['ACTIVACIÓN', 'EXPLORACIÓN', 'EXPLICACIÓN', 'APLICACIÓN', 'EVALUACIÓN'],
        colors:   ['#EF4444', '#F59E0B', '#7C3AED', '#06B6D4', '#22C55E'],
    },
};

// All possible section names across all templates (for auto-detection)
const ALL_SECTIONS = Object.values(LESSON_TEMPLATES).flatMap(t => t.sections);

/**
 * Splits a markdown string into named sections using bold headers (e.g. **INICIO**).
 * Returns an array of { name, content, color } objects in document order.
 */
function parseLessonSections(text, templateDef) {
    const result = [];
    const { sections, colors } = templateDef;

    for (let i = 0; i < sections.length; i++) {
        const header = '**' + sections[i] + '**';
        const start  = text.indexOf(header);
        if (start === -1) continue;

        const contentStart = start + header.length;

        // Find where the next known section begins (to know where this one ends)
        let contentEnd = text.length;
        for (let j = i + 1; j < sections.length; j++) {
            const nextIdx = text.indexOf('**' + sections[j] + '**', contentStart);
            if (nextIdx !== -1 && nextIdx < contentEnd) {
                contentEnd = nextIdx;
                break;
            }
        }

        const content = text.slice(contentStart, contentEnd).trim();
        result.push({ name: sections[i], content, color: colors[i] || '#7C3AED' });
    }

    return result;
}

/**
 * Renders a markdown string as structured lesson-section cards when the text
 * contains pedagogical section headers (**INICIO**, **MOTIVACIÓN**, etc.).
 * Falls back to standard marked parsing when no sections are detected.
 */
window.renderMarkdown = function renderMarkdown(md) {
    if (md == null || String(md).trim() === '') return '';
    const text = String(md);

    // Detect whether the text has any template section header
    const hasSections = ALL_SECTIONS.some(s => text.includes('**' + s + '**'));

    if (!hasSections) {
        const raw = marked.parse(text);
        return DOMPurify.sanitize(raw, { USE_PROFILES: { html: true } });
    }

    // Determine which template's sections to use (prefer the teacher's saved choice,
    // then auto-detect by whichever template has the most matching headers).
    const chosenTemplate = window.novaLessonTemplate || 'clasica';
    let templateDef = LESSON_TEMPLATES[chosenTemplate];

    // If the chosen template's sections don't appear in the text, auto-detect
    const chosenHasMatch = templateDef.sections.some(s => text.includes('**' + s + '**'));
    if (!chosenHasMatch) {
        let bestCount = 0;
        for (const [key, tpl] of Object.entries(LESSON_TEMPLATES)) {
            const count = tpl.sections.filter(s => text.includes('**' + s + '**')).length;
            if (count > bestCount) { bestCount = count; templateDef = tpl; }
        }
    }

    const sections = parseLessonSections(text, templateDef);

    if (sections.length === 0) {
        const raw = marked.parse(text);
        return DOMPurify.sanitize(raw, { USE_PROFILES: { html: true } });
    }

    const sectionsHtml = sections.map(({ name, content, color }) => {
        const inner = DOMPurify.sanitize(marked.parse(content), { USE_PROFILES: { html: true } });
        return `<div class="lesson-section" style="border-left:3px solid ${color};background:${color}12">
            <div class="lesson-section-title" style="color:${color}">${name}</div>
            <div class="lesson-section-content">${inner}</div>
        </div>`;
    }).join('');

    return `<div class="lesson-sections">${sectionsHtml}</div>`;
};

window.Alpine = Alpine;

Alpine.start();
