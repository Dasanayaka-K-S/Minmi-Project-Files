
/**
 * Set the daily target progress bar width and colour class.
 * Called once on page load with the PHP-computed percentage.
 *
 * @param {number} pct  - Percentage 0–100
 */
function initTargetBar(pct) {
    const fill = document.getElementById('targetBarFill');
    if (!fill) return;
    fill.style.width = pct + '%';
    if      (pct >= 100) fill.classList.add('green');
    else if (pct >= 60)  fill.classList.add('yellow');
    else                 fill.classList.add('orange');
}