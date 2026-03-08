/* ============================================================
   REPORTS PAGE SCRIPTS — Minmi Restaurent Admin
   Place in: dashboard/assets/js/reports.js
   ============================================================ */

/**
 * Export the full page as PDF via browser print dialog.
 */
function exportPDF() {
    const btn = document.querySelector('button[onclick="exportPDF()"]');
    if (btn) { btn.textContent = '⏳ Preparing…'; btn.disabled = true; }
    setTimeout(() => {
        window.print();
        if (btn) { btn.textContent = '🖨️ Export PDF'; btn.disabled = false; }
    }, 400);
}

/**
 * Print a single report section in a clean iframe.
 * Charts (canvas) are captured as PNG images before printing.
 *
 * @param {string} sectionId  - ID of the section element to print
 * @param {string} title      - Section title shown in the PDF header
 */
function printSection(sectionId, title) {
    const section = document.getElementById(sectionId);
    if (!section) return;

    /* ── Capture canvas charts as base64 PNG images ── */
    const canvases    = section.querySelectorAll('canvas');
    const chartImages = {};
    canvases.forEach(c => { chartImages[c.id] = c.toDataURL('image/png'); });

    /* ── Replace <canvas> tags with <img> tags in the HTML clone ── */
    let sectionHTML = section.innerHTML;
    canvases.forEach(c => {
        sectionHTML = sectionHTML.replace(
            new RegExp(`<canvas id="${c.id}"[^>]*></canvas>`, 'g'),
            `<img src="${chartImages[c.id]}" style="max-width:100%;height:220px;object-fit:contain">`
        );
    });

    /* ── Build the printable HTML document ── */
    const generated = window.REPORTS_GENERATED_AT || '';
    const printHTML = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>${title} — Minmi Restaurent</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0 }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #111; background: #fff; padding: 32px }

        /* Header */
        .pdf-top { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e8622a; padding-bottom: 14px; margin-bottom: 20px }
        .pdf-brand { font-size: 1.3rem; font-weight: 700; color: #e8622a }
        .pdf-meta  { text-align: right; font-size: .75rem; color: #888 }
        .pdf-section-title { font-size: 1rem; font-weight: 700; color: #e8622a; margin-bottom: 16px }

        /* Calc box */
        .calc-box   { display: block !important; background: #fff8f5; border-left: 3px solid #e8622a; border-radius: 0 6px 6px 0; padding: 12px 14px; margin: 12px 0 16px }
        .calc-title { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #e8622a; margin-bottom: 8px }
        .calc-steps { display: flex; flex-direction: column; gap: 7px }
        .calc-step  { display: flex; align-items: flex-start; gap: 10px; font-size: .8rem; color: #444; line-height: 1.5 }
        .calc-num   { background: #e8622a; color: #fff; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: .65rem; font-weight: 700; flex-shrink: 0; margin-top: 1px }
        code        { background: #f0f0f0; padding: 1px 5px; border-radius: 3px; font-size: .76rem; color: #c0392b }

        /* AI metric */
        .ai-metric { padding: 12px 14px; border-radius: 6px; background: #f5f5f5; border: 1px solid #ddd; margin-bottom: 8px }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-top: 12px }
        th    { background: #f5f5f5; padding: 8px 10px; font-size: .73rem; text-align: left; color: #333; border-bottom: 2px solid #ddd }
        td    { padding: 8px 10px; font-size: .78rem; border-bottom: 1px solid #eee; color: #333 }

        /* Badges */
        .badge       { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: .65rem; font-weight: 600; border: 1px solid #ccc; background: #f5f5f5; color: #333 }
        .badge-red   { background: #fef2f2; color: #dc2626; border-color: #fca5a5 }
        .badge-green { background: #f0fdf4; color: #16a34a; border-color: #86efac }

        /* Hide buttons in print */
        .no-print, button { display: none !important }

        /* Footer */
        .pdf-footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #ddd; display: flex; justify-content: space-between; font-size: .72rem; color: #aaa }
    </style>
</head>
<body>
    <div class="pdf-top">
        <div>
            <div class="pdf-brand">🔥 Minmi Restaurent</div>
            <div style="font-size:.8rem;color:#888;margin-top:4px">${title}</div>
        </div>
        <div class="pdf-meta">Generated: ${generated}<br>All time data</div>
    </div>
    <div class="pdf-section-title">${title}</div>
    ${sectionHTML}
    <div class="pdf-footer">
        <span>🔥 Minmi Restaurent — Confidential</span>
        <span>Generated: ${generated}</span>
    </div>
</body>
</html>`;

    /* ── Inject into hidden iframe and trigger print ── */
    const iframe = document.getElementById('printFrame');
    iframe.style.display = 'none';
    iframe.srcdoc = printHTML;
    iframe.onload = function () {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    };
}