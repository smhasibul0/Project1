{{-- Type-ahead over the customs tariff, shared by the quotation and order item rows.

     Include once per page, then call window.attachHsSearch(block) for each item block.
     The block must hold an .hs-search input, an .hs-results container, a hidden
     [data-name="hs_code_id"] and a description field; picking a result fills the
     description and the six duty rates from the tariff line. --}}
<style>
    .hs-search-wrap { position:relative; }
    .hs-results { position:absolute; z-index:1055; top:100%; left:0; right:0; background:#fff; border:1px solid #cbd5e1;
                  border-radius:6px; box-shadow:0 8px 20px rgba(15,23,42,.12); max-height:300px; overflow-y:auto; display:none; }
    .hs-results.show { display:block; }
    .hs-results .hs-item { padding:.45rem .7rem; cursor:pointer; border-bottom:1px solid #f1f5f9; font-size:.78rem; }
    .hs-results .hs-item:last-child { border-bottom:none; }
    .hs-results .hs-item:hover { background:rgba(var(--brand-rgb,83,122,239),.1); }
    .hs-results .hs-code { font-weight:700; color:var(--brand,#537AEF); }
    .hs-results .hs-desc { color:#475569; display:block; }
    .hs-results .hs-rates { color:#94a3b8; font-size:.72rem; }
    .hs-results .hs-empty { padding:.6rem .7rem; color:#94a3b8; font-size:.78rem; }
</style>

<script>
(function () {
    const SEARCH_URL = '{{ route('hs.code.search') }}';
    const RATES = ['cd_rate', 'sd_rate', 'vat_rate', 'ait_rate', 'rd_rate', 'at_rate'];
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    /**
     * Wire the HS code lookup on one item block. `onPick` runs after a tariff
     * line is chosen so the caller can recalculate its own totals.
     */
    window.attachHsSearch = function (block, onPick) {
        const input = block.querySelector('.hs-search');
        const results = block.querySelector('.hs-results');
        const idField = block.querySelector('[data-name="hs_code_id"]');
        if (!input || !results) { return; }
        let timer = null;

        function close() { results.classList.remove('show'); results.innerHTML = ''; }

        function choose(row) {
            if (idField) { idField.value = row.id; }
            input.value = row.code;

            const description = block.querySelector('[data-name="description"]')
                || block.querySelector('[data-name="item_description"]');
            if (description) { description.value = row.description; }

            RATES.forEach(function (rate) {
                const field = block.querySelector('[data-name="' + rate + '"]');
                if (field) { field.value = parseFloat(row[rate]) || 0; }
            });

            // The reference declared value from Rates, where the row keeps one (quotations):
            // the highest to start with, and the most common and lowest to choose from.
            const reference = row.reference || null;
            const priceField = block.querySelector('[data-name="reference_unit_price"]');
            const dateField = block.querySelector('[data-name="reference_rate_date"]');
            const basisField = block.querySelector('[data-name="reference_basis"]');
            const optionsField = block.querySelector('[data-name="reference_options"]');
            if (priceField) { priceField.value = reference ? reference.unit_price : ''; }
            if (dateField) { dateField.value = reference ? reference.rate_date : ''; }
            if (basisField) { basisField.value = reference ? 'highest' : ''; }
            if (optionsField) { optionsField.value = reference && reference.options ? JSON.stringify(reference.options) : ''; }

            close();
            if (typeof onPick === 'function') { onPick(block); }
        }

        input.addEventListener('input', function () {
            // Typing over the code detaches the row from the tariff line it had.
            if (idField) { idField.value = ''; }
            const term = input.value.trim();
            clearTimeout(timer);

            if (term.length < 2) { close(); return; }

            timer = setTimeout(async function () {
                try {
                    const response = await fetch(SEARCH_URL + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } });
                    const rows = await response.json();

                    results.innerHTML = rows.length
                        ? rows.map(r =>
                            '<div class="hs-item" data-row="' + encodeURIComponent(JSON.stringify(r)) + '">' +
                            '<span class="hs-code">' + escape(r.code) + '</span>' +
                            '<span class="hs-desc">' + escape(r.description) + '</span>' +
                            '<span class="hs-rates">CD ' + (+r.cd_rate) + ' · SD ' + (+r.sd_rate) + ' · VAT ' + (+r.vat_rate) +
                            ' · AIT ' + (+r.ait_rate) + ' · RD ' + (+r.rd_rate) + ' · AT ' + (+r.at_rate) + '</span></div>').join('')
                        : '<div class="hs-empty">No HS code matches — check the tariff under HS Codes.</div>';

                    results.classList.add('show');
                    results.querySelectorAll('.hs-item').forEach(el =>
                        el.addEventListener('mousedown', () => choose(JSON.parse(decodeURIComponent(el.dataset.row)))));
                } catch (e) {
                    close();
                }
            }, 250);
        });

        input.addEventListener('blur', () => setTimeout(close, 150));
    };
})();
</script>
