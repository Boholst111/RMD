<script>
// Copied interactive matrix JS from create.blade.php to reuse in edit view.
// Embedded Price Matrix Data from DB
let priceMatrix = @json($priceMatrices);
const categoriesFromServer = @json($categories ?? []);
let categoryList = (categoriesFromServer && categoriesFromServer.length) ? categoriesFromServer : [...new Set(priceMatrix.map(item => item.category.toUpperCase()))].sort();
const defaultCategory = categoryList.length ? categoryList[0] : 'FALCATA';

// Official volume lookup table for standard diameters and lengths (m³)
const volumeTable = {
    16: { '2.6': 0.052, '1.3': 0.026, '1.0': 0.020 },
    18: { '2.6': 0.066, '1.3': 0.033, '1.0': 0.025 },
    20: { '2.6': 0.081, '1.3': 0.040, '1.0': 0.031 },
    22: { '2.6': 0.098, '1.3': 0.049, '1.0': 0.038 },
    24: { '2.6': 0.117, '1.3': 0.058, '1.0': 0.045 },
    26: { '2.6': 0.138, '1.3': 0.069, '1.0': 0.053 },
    28: { '2.6': 0.160, '1.3': 0.080, '1.0': 0.061 },
    30: { '2.6': 0.183, '1.3': 0.091, '1.0': 0.070 },
    32: { '2.6': 0.209, '1.3': 0.104, '1.0': 0.080 },
    34: { '2.6': 0.236, '1.3': 0.118, '1.0': 0.091 },
    36: { '2.6': 0.264, '1.3': 0.132, '1.0': 0.101 },
    38: { '2.6': 0.294, '1.3': 0.147, '1.0': 0.113 },
    40: { '2.6': 0.326, '1.3': 0.163, '1.0': 0.125 },
    42: { '2.6': 0.360, '1.3': 0.180, '1.0': 0.138 },
    44: { '2.6': 0.395, '1.3': 0.197, '1.0': 0.152 },
    46: { '2.6': 0.432, '1.3': 0.216, '1.0': 0.166 },
    48: { '2.6': 0.470, '1.3': 0.235, '1.0': 0.181 },
    50: { '2.6': 0.510, '1.3': 0.255, '1.0': 0.196 },
    52: { '2.6': 0.552, '1.3': 0.276, '1.0': 0.212 },
    54: { '2.6': 0.595, '1.3': 0.297, '1.0': 0.229 },
    56: { '2.6': 0.640, '1.3': 0.320, '1.0': 0.246 },
    58: { '2.6': 0.686, '1.3': 0.343, '1.0': 0.264 },
    60: { '2.6': 0.735, '1.3': 0.367, '1.0': 0.283 },
    62: { '2.6': 0.784, '1.3': 0.392, '1.0': 0.301 },
    64: { '2.6': 0.836, '1.3': 0.418, '1.0': 0.322 },
    66: { '2.6': 0.889, '1.3': 0.444, '1.0': 0.342 },
    68: { '2.6': 0.944, '1.3': 0.472, '1.0': 0.363 },
    70: { '2.6': 1.000, '1.3': 0.500, '1.0': 0.385 },
    72: { '2.6': 1.058, '1.3': 0.529, '1.0': 0.407 },
    74: { '2.6': 1.118, '1.3': 0.559, '1.0': 0.430 },
    76: { '2.6': 1.179, '1.3': 0.589, '1.0': 0.453 },
    78: { '2.6': 1.242, '1.3': 0.621, '1.0': 0.478 },
    80: { '2.6': 1.306, '1.3': 0.653, '1.0': 0.502 }
};

function getLogVolume(diameterCm, lengthM) {
    const diameter = Number(diameterCm) || 0;
    const length = Number(lengthM) || 0;

    if (Math.abs(length - 1.0) < 0.01) {
        const radiusMeters = (diameter / 100) / 2;
        const thousandths = Math.floor(Math.PI * radiusMeters ** 2 * length * 1000);

        return {
            value: thousandths / 1000,
            thousandths,
            formatted: `${Math.floor(thousandths / 1000)}.${String(thousandths % 1000).padStart(3, '0')}`,
        };
    }

    const lengthKey = String(length);
    const value = volumeTable[diameter]?.[lengthKey] !== undefined
        ? Number(volumeTable[diameter][lengthKey])
        : (0.7854 * Math.pow(diameter, 2) * length) / 10000;

    return { value, thousandths: null, formatted: value.toFixed(3) };
}

function formatLogVolume(volume, quantity = 1) {
    if (volume.thousandths !== null) {
        const totalThousandths = volume.thousandths * quantity;

        return `${Math.floor(totalThousandths / 1000)}.${String(totalThousandths % 1000).padStart(3, '0')}`;
    }

    return (volume.value * quantity).toFixed(3);
}

// Initial default template rows: even diameters from 16 to 80 (Length strictly 1.3m or 2.6m)
const initialRows = Array.from({ length: 33 }, (_, index) => {
    const diameter = 16 + (index * 2);
    return {
        category: defaultCategory,
        grade: 'Good',
        is_split: false,
        split_group_id: '',
        length: '2.6',
        diameter,
        quantity: 0,
    };
});

let rowIndex = 0;

// Rate cache to avoid repeated API calls for same spec
const rateCache = {};

/** Resolve rates from the fresh matrix embedded in this server response. */
function getMatchingRate(category, length, diameter, grade) {
    const normCategory = String(category || '').trim().toUpperCase();
    const normGrade = String(grade || '').trim().toUpperCase();
    const len = parseFloat(length) || 2.6;
    const dia = parseInt(diameter, 10) || 0;

    // Create cache key
    const cacheKey = `${normCategory}|${len}|${dia}|${normGrade}`;
    if (rateCache[cacheKey] !== undefined) {
        return rateCache[cacheKey];
    }

    let rate = 0;

    // Sawmill Grade check
    if (normGrade === 'SAWMILL' || normGrade === 'SAWMILL (SM)') {
        const sawmillDb = priceMatrix.find(r => {
            const cat = String(r.category || '').toUpperCase();
            return (cat === 'SAWMILL' || cat === normCategory) &&
                   (parseInt(r.dia_min, 10) === 0 && parseInt(r.dia_max, 10) === 0);
        });
        if (sawmillDb && parseFloat(sawmillDb.price_per_cu_m) > 0) {
            rate = parseFloat(sawmillDb.price_per_cu_m);
            rateCache[cacheKey] = rate;
            return rate;
        }
        const sawmillCatDb = priceMatrix.find(r => String(r.category || '').toUpperCase() === 'SAWMILL');
        if (sawmillCatDb && parseFloat(sawmillCatDb.price_per_cu_m) > 0) {
            rate = parseFloat(sawmillCatDb.price_per_cu_m);
            rateCache[cacheKey] = rate;
            return rate;
        }
        // NO HARDCODED FALLBACK - return 0 to indicate missing price
        console.warn(`No sawmill price found for: ${normCategory} ${dia}cm ${len}m`);
        rateCache[cacheKey] = 0.00;
        return 0.00;
    }

    // Exact match in priceMatrix by category, length, and diameter range
    const dbMatch = priceMatrix.find(r => {
        const catMatch = String(r.category || '').toUpperCase() === normCategory || normCategory === 'FALCATA' || String(r.category || '').toUpperCase() === 'FALCATA';
        const lenMatch = Math.abs(parseFloat(r.length) - len) < 0.05;
        const diaMin = parseInt(r.dia_min, 10);
        const diaMax = parseInt(r.dia_max, 10);
        const diaMatch = (dia >= diaMin && (diaMax >= 999 ? true : dia <= diaMax));
        return catMatch && lenMatch && diaMatch;
    });

    if (dbMatch && parseFloat(dbMatch.price_per_cu_m) > 0) {
        rate = parseFloat(dbMatch.price_per_cu_m);
        rateCache[cacheKey] = rate;
        return rate;
    }

    // Fallback match without strict length constraint
    const dbMatchAnyLength = priceMatrix.find(r => {
        const catMatch = String(r.category || '').toUpperCase() === normCategory || normCategory === 'FALCATA' || String(r.category || '').toUpperCase() === 'FALCATA';
        const diaMin = parseInt(r.dia_min, 10);
        const diaMax = parseInt(r.dia_max, 10);
        const diaMatch = (dia >= diaMin && (diaMax >= 999 ? true : dia <= diaMax));
        return catMatch && diaMatch;
    });

    if (dbMatchAnyLength && parseFloat(dbMatchAnyLength.price_per_cu_m) > 0) {
        rate = parseFloat(dbMatchAnyLength.price_per_cu_m);
        rateCache[cacheKey] = rate;
        return rate;
    }

    // No DB match — return 0 so frontend treats it as "no rate set" and avoids hardcoding.
    rateCache[cacheKey] = 0.00;
    return 0.00;
}

function getRowRate(row, savedRateKey, category, length, diameter, grade) {
    const savedRate = row.dataset[savedRateKey];
    if (row.dataset.historicalRatesDirty !== 'true' && savedRate !== undefined && Number.isFinite(Number(savedRate))) {
        return Number(savedRate);
    }

    return getMatchingRate(category, length, diameter, grade);
}

function addRow(data = { category: defaultCategory, grade: 'Good', is_split: false, split_group_id: '', length: '2.6', diameter: 20, quantity: 1, isPreset: false }) {
    rowIndex++;
    const isSplit = data.is_split || false;

    if (isSplit) {
        // Render Split Pair Row (Box 2: Dual Diameters for Part A & Part B, 1 pair = 1 PC)
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-800/30 transition-all row-item row-split-pair border-l-4 border-amber-500 bg-amber-950/20';
        tr.id = `row-${rowIndex}`;
        tr.dataset.isSplit = 'true';

        const defaultGradeA = data.gradeA || 'Good';
        const defaultLengthA = data.lengthA || '1.3';
        const defaultDiaA = data.diameterA || data.diameter || 20;

        const defaultGradeB = data.gradeB || 'Sawmill';
        const defaultLengthB = data.lengthB || '1.3';
        const defaultDiaB = data.diameterB || data.diameter || 20;

        tr.innerHTML = `
            <td hidden>
            <!-- Part A hidden inputs -->
            <input type="hidden" name="items[${rowIndex}_A][is_split]" value="1">
            <input type="hidden" name="items[${rowIndex}_A][split_group_id]" value="split_${rowIndex}">
            <input type="hidden" name="items[${rowIndex}_A][split_side]" value="A">
            <input type="hidden" name="items[${rowIndex}_A][category]" class="row-cat-hidden-a" value="${data.category}">
            <input type="hidden" name="items[${rowIndex}_A][grade]" class="row-grade-hidden-a" value="${defaultGradeA}">
            <input type="hidden" name="items[${rowIndex}_A][length]" class="row-len-hidden-a" value="${defaultLengthA}">
            <input type="hidden" name="items[${rowIndex}_A][diameter]" class="row-dia-hidden-a" value="${defaultDiaA}">
            <input type="hidden" name="items[${rowIndex}_A][quantity]" class="row-qty-hidden-a" value="${data.quantity}">
            <input type="hidden" class="row-volume-hidden-a" value="0.000">
            <input type="hidden" class="row-total-volume-hidden-a" value="0.000">
            <input type="hidden" name="items[${rowIndex}_A][subtotal]" class="row-subtotal-hidden-a" value="0.00">

            <!-- Part B hidden inputs -->
            <input type="hidden" name="items[${rowIndex}_B][is_split]" value="1">
            <input type="hidden" name="items[${rowIndex}_B][split_group_id]" value="split_${rowIndex}">
            <input type="hidden" name="items[${rowIndex}_B][split_side]" value="B">
            <input type="hidden" name="items[${rowIndex}_B][category]" class="row-cat-hidden-b" value="${data.category}">
            <input type="hidden" name="items[${rowIndex}_B][grade]" class="row-grade-hidden-b" value="${defaultGradeB}">
            <input type="hidden" name="items[${rowIndex}_B][length]" class="row-len-hidden-b" value="${defaultLengthB}">
            <input type="hidden" name="items[${rowIndex}_B][diameter]" class="row-dia-hidden-b" value="${defaultDiaB}">
            <input type="hidden" name="items[${rowIndex}_B][quantity]" class="row-qty-hidden-b" value="${data.quantity}">
            <input type="hidden" class="row-volume-hidden-b" value="0.000">
            <input type="hidden" class="row-total-volume-hidden-b" value="0.000">
            <input type="hidden" name="items[${rowIndex}_B][subtotal]" class="row-subtotal-hidden-b" value="0.00">
            </td>

            <td class="px-3 py-3 text-center text-xs text-slate-500 font-mono row-num">1</td>
            
            <td class="px-3 py-3">
                <select class="row-cat-select w-full bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none">
                    ${categoryList.map(cat => `<option value="${cat}" ${data.category === cat ? 'selected' : ''}>${cat}</option>`).join('')}
                </select>
            </td>

            <!-- Part A Selectors: Grade / Length / Independent Diameter A -->
            <td class="px-3 py-3">
                <div class="flex items-center gap-1.5">
                    <select class="row-grade-a bg-slate-900 border border-slate-700 text-emerald-400 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-semibold">
                        <option value="Good" ${defaultGradeA === 'Good' ? 'selected' : ''}>Good</option>
                        <option value="Sawmill" ${defaultGradeA === 'Sawmill' ? 'selected' : ''}>Sawmill (SM)</option>
                    </select>
                    <select class="row-len-a bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-mono" disabled>
                        <option value="1.3" selected>1.3m</option>
                    </select>
                    <div class="flex items-center gap-1">
                        <input type="number" step="1" min="1" value="${defaultDiaA}" class="row-dia-a w-14 bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-mono text-center">
                        <span class="text-[10px] text-slate-500 font-mono">cm</span>
                    </div>
                </div>
            </td>

            <!-- Part B Selectors: Grade / Length / Independent Diameter B -->
            <td class="px-3 py-3">
                <div class="flex items-center gap-1.5">
                    <select class="row-grade-b bg-slate-900 border border-amber-500/50 text-amber-400 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-bold">
                        <option value="Good" ${defaultGradeB === 'Good' ? 'selected' : ''}>Good</option>
                        <option value="Sawmill" ${defaultGradeB === 'Sawmill' ? 'selected' : ''}>Sawmill (SM)</option>
                    </select>
                    <select class="row-len-b bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-mono" disabled>
                        <option value="1.3" selected>1.3m</option>
                    </select>
                    <div class="flex items-center gap-1">
                        <input type="number" step="1" min="1" value="${defaultDiaB}" class="row-dia-b w-14 bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-mono text-center">
                        <span class="text-[10px] text-slate-500 font-mono">cm</span>
                    </div>
                </div>
            </td>

            <td class="px-3 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                    <button type="button" class="qty-decrement h-8 w-8 rounded-lg border border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800 hover:text-amber-400 transition-all" data-action="decrement">-</button>
                    <input type="number" step="1" min="0" value="${data.quantity}" class="row-qty-input w-16 bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none text-center font-bold font-mono">
                    <button type="button" class="qty-increment h-8 w-8 rounded-lg border border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800 hover:text-amber-400 transition-all" data-action="increment">+</button>
                </div>
            </td>

            <td class="px-3 py-3 text-right font-mono text-xs text-slate-400 row-vol-single">0.0000</td>
            <td class="px-3 py-3 text-right font-mono text-xs font-semibold text-sky-400 row-vol-tot">0.0000</td>

            <!-- Clean Vertically Stacked Rates with Diameters -->
            <td class="px-3 py-3 text-right row-rates-display">
                <div class="flex flex-col text-xs font-mono gap-0.5 text-right whitespace-nowrap">
                    <span><strong class="text-amber-400">A:</strong> ₱ 0.00</span>
                    <span><strong class="text-sky-400">B:</strong> ₱ 0.00</span>
                </div>
            </td>

            <td class="px-3 py-3 text-right font-mono text-sm font-bold text-amber-400 row-subtotal">₱ 0.00</td>

            <td class="px-3 py-3 text-center">
                <button type="button" onclick="removeRow(${rowIndex})" class="text-slate-500 hover:text-rose-400 p-1.5 transition-colors" title="Delete Row">
                    <i class="fa-solid fa-trash-can text-sm"></i>
                </button>
            </td>
        `;

        document.getElementById('splitMatrixBody').appendChild(tr);
        tr.querySelectorAll('[name]').forEach(control => control.setAttribute('form', 'scaleForm'));

        const syncInputs = () => {
            const cat = tr.querySelector('.row-cat-select').value;

            const gradeA = tr.querySelector('.row-grade-a').value;
            const lenA = tr.querySelector('.row-len-a').value;
            const diaA = parseInt(tr.querySelector('.row-dia-a').value) || 20;

            const gradeB = tr.querySelector('.row-grade-b').value;
            const lenB = tr.querySelector('.row-len-b').value;
            const diaB = parseInt(tr.querySelector('.row-dia-b').value) || 20;

            const qty = parseInt(tr.querySelector('.row-qty-input').value) || 0;

            tr.querySelector('.row-cat-hidden-a').value = cat;
            tr.querySelector('.row-cat-hidden-b').value = cat;

            tr.querySelector('.row-grade-hidden-a').value = gradeA;
            tr.querySelector('.row-grade-hidden-b').value = gradeB;

            tr.querySelector('.row-len-hidden-a').value = lenA;
            tr.querySelector('.row-len-hidden-b').value = lenB;

            tr.querySelector('.row-dia-hidden-a').value = diaA;
            tr.querySelector('.row-dia-hidden-b').value = diaB;

            tr.querySelector('.row-qty-hidden-a').value = qty;
            tr.querySelector('.row-qty-hidden-b').value = qty;

            let volA = 0;
            let volB = 0;
            if (diaA > 0 && lenA > 0) {
                const keyA = String(lenA);
                if (volumeTable[diaA] && volumeTable[diaA][keyA] !== undefined) {
                    volA = Number(volumeTable[diaA][keyA]);
                } else {
                    volA = (0.7854 * Math.pow(diaA, 2) * lenA) / 10000;
                }
            }
            if (diaB > 0 && lenB > 0) {
                const keyB = String(lenB);
                if (volumeTable[diaB] && volumeTable[diaB][keyB] !== undefined) {
                    volB = Number(volumeTable[diaB][keyB]);
                } else {
                    volB = (0.7854 * Math.pow(diaB, 2) * lenB) / 10000;
                }
            }
            const totVolA = qty * volA;
            const totVolB = qty * volB;
            const rateA = getMatchingRate(cat, lenA, diaA, gradeA);
            const rateB = getMatchingRate(cat, lenB, diaB, gradeB);

            const hiddenVolA = tr.querySelector('.row-volume-hidden-a');
            const hiddenTotVolA = tr.querySelector('.row-total-volume-hidden-a');
            const hiddenSubtotalA = tr.querySelector('.row-subtotal-hidden-a');
            const hiddenVolB = tr.querySelector('.row-volume-hidden-b');
            const hiddenTotVolB = tr.querySelector('.row-total-volume-hidden-b');
            const hiddenSubtotalB = tr.querySelector('.row-subtotal-hidden-b');

            if (hiddenVolA) hiddenVolA.value = volA.toFixed(3);
            if (hiddenTotVolA) hiddenTotVolA.value = totVolA.toFixed(3);
            if (hiddenSubtotalA) hiddenSubtotalA.value = (totVolA * rateA).toFixed(2);
            if (hiddenVolB) hiddenVolB.value = volB.toFixed(3);
            if (hiddenTotVolB) hiddenTotVolB.value = totVolB.toFixed(3);
            if (hiddenSubtotalB) hiddenSubtotalB.value = (totVolB * rateB).toFixed(2);

            // Style grade dropdowns dynamically
            const selectA = tr.querySelector('.row-grade-a');
            selectA.className = selectA.value === 'Sawmill' 
                ? 'row-grade-a bg-slate-900 border border-amber-500/50 text-amber-400 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-bold'
                : 'row-grade-a bg-slate-900 border border-slate-700 text-emerald-400 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-semibold';

            const selectB = tr.querySelector('.row-grade-b');
            selectB.className = selectB.value === 'Sawmill' 
                ? 'row-grade-b bg-slate-900 border border-amber-500/50 text-amber-400 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-bold'
                : 'row-grade-b bg-slate-900 border border-slate-700 text-emerald-400 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-semibold';
        };

        tr.querySelector('.row-cat-select').addEventListener('change', () => { syncInputs(); recalculateAll(); });
        tr.querySelector('.row-grade-a').addEventListener('change', () => { syncInputs(); recalculateAll(); });
        tr.querySelector('.row-len-a').addEventListener('change', () => { syncInputs(); recalculateAll(); });
        tr.querySelector('.row-dia-a').addEventListener('input', () => { syncInputs(); recalculateAll(); });

        tr.querySelector('.row-grade-b').addEventListener('change', () => { syncInputs(); recalculateAll(); });
        tr.querySelector('.row-len-b').addEventListener('change', () => { syncInputs(); recalculateAll(); });
        tr.querySelector('.row-dia-b').addEventListener('input', () => { syncInputs(); recalculateAll(); });

        tr.querySelector('.row-qty-input').addEventListener('input', () => { syncInputs(); recalculateAll(); });

        tr.querySelector('.qty-decrement').addEventListener('click', () => {
            const qtyInput = tr.querySelector('.row-qty-input');
            const nextValue = Math.max(0, parseInt(qtyInput.value || '0', 10) - 1);
            qtyInput.value = nextValue;
            syncInputs();
            recalculateAll();
        });

        tr.querySelector('.qty-increment').addEventListener('click', () => {
            const qtyInput = tr.querySelector('.row-qty-input');
            const nextValue = parseInt(qtyInput.value || '0', 10) + 1;
            qtyInput.value = nextValue;
            syncInputs();
            recalculateAll();
        });

    } else {
        // Render Standard Row (Box 1, length options strictly 1.3m and 2.6m)
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-800/30 transition-all row-item row-standard';
        tr.id = `row-${rowIndex}`;
        tr.dataset.isSplit = 'false';

        const defaultLen = data.length || '2.6';

        tr.innerHTML = `
            <td hidden>
            <input type="hidden" class="row-vol-hidden" value="0.000">
            <input type="hidden" class="row-total-vol-hidden" value="0.000">
            <input type="hidden" name="items[${rowIndex}][subtotal]" class="row-subtotal-hidden" value="0.00">
            </td>
            <td class="px-3 py-3 text-center text-xs text-slate-500 font-mono row-num">1</td>
            
            <td class="px-3 py-3">
                <select name="items[${rowIndex}][category]" class="row-cat w-full bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none">
                    ${categoryList.map(cat => `<option value="${cat}" ${data.category === cat ? 'selected' : ''}>${cat}</option>`).join('')}
                </select>
            </td>

            <td class="px-3 py-3">
                <select name="items[${rowIndex}][grade]" class="row-grade w-full bg-slate-900 border border-slate-700 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-semibold text-emerald-400">
                    <option value="Good" ${data.grade === 'Good' ? 'selected' : ''}>Good</option>
                    <option value="Sawmill" ${data.grade === 'Sawmill' ? 'selected' : ''}>Sawmill (SM)</option>
                </select>
            </td>

            <td class="px-3 py-3">
                <select name="items[${rowIndex}][length]" class="row-len w-full bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-mono">
                    <option value="1.0" ${String(defaultLen).includes('1.0') ? 'selected' : ''}>1.0m</option>
                    <option value="1.3" ${String(defaultLen).includes('1.3') ? 'selected' : ''}>1.3m</option>
                    <option value="2.6" ${String(defaultLen).includes('2.6') ? 'selected' : ''}>2.6m</option>
                </select>
            </td>

            <td class="px-3 py-3">
                <input type="number" step="1" min="1" name="items[${rowIndex}][diameter]" value="${data.diameter || 20}" class="row-dia w-full bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-mono font-bold" ${data.isPreset ? 'readonly' : ''}>
            </td>

            <td class="px-3 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                    <button type="button" class="qty-decrement h-8 w-8 rounded-lg border border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800 hover:text-amber-400 transition-all" data-action="decrement">-</button>
                    <input type="number" step="1" min="0" name="items[${rowIndex}][quantity]" value="${data.quantity}" class="row-qty w-16 bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none text-center font-bold font-mono">
                    <button type="button" class="qty-increment h-8 w-8 rounded-lg border border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800 hover:text-amber-400 transition-all" data-action="increment">+</button>
                </div>
            </td>

            <td class="px-3 py-3 text-right font-mono text-xs text-slate-400 row-vol-single">0.0000</td>
            <td class="px-3 py-3 text-right font-mono text-xs font-semibold text-sky-400 row-vol-tot">0.0000</td>
            <td class="px-3 py-3 text-right font-mono text-xs text-amber-300 row-rate">₱ 0.00</td>
            <td class="px-3 py-3 text-right font-mono text-sm font-bold text-amber-400 row-subtotal">₱ 0.00</td>

            <td class="px-3 py-3 text-center">
                <button type="button" onclick="removeRow(${rowIndex})" class="text-slate-500 hover:text-rose-400 p-1.5 transition-colors" title="Delete Row">
                    <i class="fa-solid fa-trash-can text-sm"></i>
                </button>
            </td>
        `;

        document.getElementById('standardMatrixBody').appendChild(tr);
        tr.querySelectorAll('[name]').forEach(control => control.setAttribute('form', 'scaleForm'));

        ['row-cat', 'row-grade', 'row-len', 'row-dia', 'row-qty'].forEach(cls => {
            tr.querySelector(`.${cls}`).addEventListener('change', recalculateAll);
            tr.querySelector(`.${cls}`).addEventListener('input', recalculateAll);
        });

        tr.querySelector('.row-grade').addEventListener('change', (e) => {
            const select = e.target;
            if (select.value === 'Sawmill') {
                select.className = 'row-grade w-full bg-slate-900 border border-amber-500/50 text-amber-400 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-bold';
            } else {
                select.className = 'row-grade w-full bg-slate-900 border border-slate-700 text-emerald-400 text-xs rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-amber-500 outline-none font-semibold';
            }
        });

        tr.querySelector('.qty-decrement').addEventListener('click', () => {
            const qtyInput = tr.querySelector('.row-qty');
            const nextValue = Math.max(0, parseInt(qtyInput.value || '0', 10) - 1);
            qtyInput.value = nextValue;
            recalculateAll();
        });

        tr.querySelector('.qty-increment').addEventListener('click', () => {
            const qtyInput = tr.querySelector('.row-qty');
            const nextValue = parseInt(qtyInput.value || '0', 10) + 1;
            qtyInput.value = nextValue;
            recalculateAll();
        });
    }

    updateRowNumbers();
    recalculateAll();
}

function removeRow(id) {
    const row = document.getElementById(`row-${id}`);
    if (row) {
        row.remove();
        updateRowNumbers();
        recalculateAll();
    }
}

function updateRowNumbers() {
    const standardRows = document.querySelectorAll('#standardMatrixBody tr.row-item');
    standardRows.forEach((r, idx) => {
        const numCell = r.querySelector('.row-num');
        if (numCell) numCell.textContent = idx + 1;
    });

    const splitRows = document.querySelectorAll('#splitMatrixBody tr.row-item');
    splitRows.forEach((r, idx) => {
        const numCell = r.querySelector('.row-num');
        if (numCell) numCell.textContent = idx + 1;
    });
}

function recalculateAll() {
    const standardRows = document.querySelectorAll('#standardMatrixBody tr.row-item');
    const splitRows = document.querySelectorAll('#splitMatrixBody tr.row-item');

    let standardTotalLogs = 0;
    let standardTotalVolume = 0.0;
    let standardGrossAmount = 0.0;

    let splitTotalLogs = 0;
    let splitTotalVolume = 0.0;
    let splitGrossAmount = 0.0;

    // Process Standard Rows
    standardRows.forEach(r => {
        const cat = r.querySelector('.row-cat').value;
        const grade = r.querySelector('.row-grade').value;
        const len = parseFloat(r.querySelector('.row-len').value) || 0;
        const dia = parseInt(r.querySelector('.row-dia').value) || 0;
        const qty = parseInt(r.querySelector('.row-qty').value) || 0;

        const volume = getLogVolume(dia, len);
        const volPerLog = volume.value;
        const totVol = qty * volPerLog;
        const rate = getRowRate(r, 'savedRate', cat, len, dia, grade);
        const subtotal = totVol * rate;

        r.querySelector('.row-vol-single').textContent = volume.formatted;
        r.querySelector('.row-vol-tot').textContent = qty > 0 ? formatLogVolume(volume, qty) : '0.000';
        r.querySelector('.row-rate').textContent = `₱ ${rate.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        r.querySelector('.row-subtotal').textContent = qty > 0 ? `₱ ${subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : '₱ 0.00';

        const volHidden = r.querySelector('.row-vol-hidden');
        const totVolHidden = r.querySelector('.row-total-vol-hidden');
        const subtotalHidden = r.querySelector('.row-subtotal-hidden');
        if (volHidden) volHidden.value = volume.formatted;
        if (totVolHidden) totVolHidden.value = formatLogVolume(volume, qty);
        if (subtotalHidden) subtotalHidden.value = subtotal.toFixed(2);

        standardTotalLogs += qty;
        standardTotalVolume += totVol;
        standardGrossAmount += subtotal;
    });

    // Process Split Rows (Dual Independent Diameters for Part A & Part B)
    splitRows.forEach(r => {
        const cat = r.querySelector('.row-cat-select').value;

        const gradeA = r.querySelector('.row-grade-a').value;
        const lenA = parseFloat(r.querySelector('.row-len-a').value) || 0;
        const diaA = parseInt(r.querySelector('.row-dia-a').value) || 0;

        const gradeB = r.querySelector('.row-grade-b').value;
        const lenB = parseFloat(r.querySelector('.row-len-b').value) || 0;
        const diaB = parseInt(r.querySelector('.row-dia-b').value) || 0;

        const qty = parseInt(r.querySelector('.row-qty-input').value) || 0;

        // Volumes per part based on their distinct diameter & length using lookup
        let volA = 0;
        if (diaA > 0 && lenA > 0) {
            const keyA = String(lenA);
            volA = (volumeTable[diaA] && volumeTable[diaA][keyA] !== undefined) ? Number(volumeTable[diaA][keyA]) : (0.7854 * Math.pow(diaA, 2) * lenA) / 10000;
        }
        let volB = 0;
        if (diaB > 0 && lenB > 0) {
            const keyB = String(lenB);
            volB = (volumeTable[diaB] && volumeTable[diaB][keyB] !== undefined) ? Number(volumeTable[diaB][keyB]) : (0.7854 * Math.pow(diaB, 2) * lenB) / 10000;
        }
        const combinedVolSingle = volA + volB;
        const totVol = qty * combinedVolSingle;

        // Dynamic Rates per segment specs using independent diameter A and diameter B
        const rateA = getRowRate(r, 'savedRateA', cat, lenA, diaA, gradeA);
        const rateB = getRowRate(r, 'savedRateB', cat, lenB, diaB, gradeB);
        
        // Subtotal
        const subtotalA = qty * volA * rateA;
        const subtotalB = qty * volB * rateB;
        const combinedSubtotal = subtotalA + subtotalB;

        // Update row displays
        r.querySelector('.row-vol-single').textContent = combinedVolSingle.toFixed(3);
        r.querySelector('.row-vol-tot').textContent = qty > 0 ? totVol.toFixed(3) : '0.000';
        
        // Stacked Rates Micro-Typography showing specific diameter per part
        r.querySelector('.row-rates-display').innerHTML = `
            <div class="flex flex-col text-xs font-mono gap-0.5 text-right whitespace-nowrap">
                <span><strong class="text-amber-400">A:</strong> ₱ ${rateA.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} <span class="text-slate-400 text-[11px]">(${diaA}cm)</span></span>
                <span><strong class="text-sky-400">B:</strong> ₱ ${rateB.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} <span class="text-slate-400 text-[11px]">(${diaB}cm)</span></span>
            </div>
        `;
        
        r.querySelector('.row-subtotal').textContent = qty > 0 ? `₱ ${combinedSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : '₱ 0.00';

        const volHiddenA = r.querySelector('.row-volume-hidden-a');
        const totVolHiddenA = r.querySelector('.row-total-volume-hidden-a');
        const subtotalHiddenA = r.querySelector('.row-subtotal-hidden-a');
        const volHiddenB = r.querySelector('.row-volume-hidden-b');
        const totVolHiddenB = r.querySelector('.row-total-volume-hidden-b');
        const subtotalHiddenB = r.querySelector('.row-subtotal-hidden-b');

        if (volHiddenA) volHiddenA.value = volA.toFixed(3);
        if (totVolHiddenA) totVolHiddenA.value = (qty * volA).toFixed(3);
        if (subtotalHiddenA) subtotalHiddenA.value = (qty * volA * rateA).toFixed(2);
        if (volHiddenB) volHiddenB.value = volB.toFixed(3);
        if (totVolHiddenB) totVolHiddenB.value = (qty * volB).toFixed(3);
        if (subtotalHiddenB) subtotalHiddenB.value = (qty * volB * rateB).toFixed(2);

        // Split pieces rule: 1 pair = 1 PC
        splitTotalLogs += qty;
        splitTotalVolume += totVol;
        splitGrossAmount += combinedSubtotal;
    });

    const grandTotalLogs = standardTotalLogs + splitTotalLogs;
    const grandTotalVolume = standardTotalVolume + splitTotalVolume;
    const grandGrossAmount = standardGrossAmount + splitGrossAmount;

    // Deductions
    const driversAssistance = parseFloat(document.getElementById('drivers_assistance').value) || 0;
    const expensesDeduction = parseFloat(document.getElementById('expenses_deduction').value) || 0;
    const travelPaper = parseFloat(document.getElementById('travel_paper_deduction').value) || 0;
    const truckingDeduction = parseFloat(document.getElementById('trucking_deduction').value) || 0;
    const cashAdvance = parseFloat(document.getElementById('cash_advance').value) || 0;
    const otherDeductionAmount = parseFloat(document.getElementById('other_deduction_amount').value) || 0;

    const totalDeductions = expensesDeduction + travelPaper + truckingDeduction + cashAdvance + otherDeductionAmount;
    const netPayable = grandGrossAmount - totalDeductions + driversAssistance;

    // Update Footers
    document.getElementById('tfootTotalLogs').textContent = Number(standardTotalLogs.toFixed(2)).toString();
    document.getElementById('tfootTotalVol').textContent = standardTotalVolume.toFixed(3);
    document.getElementById('tfootGrossSubtotal').textContent = `₱ ${standardGrossAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

    document.getElementById('tfootSplitTotalLogs').textContent = Number(splitTotalLogs.toFixed(2)).toString();
    document.getElementById('tfootSplitTotalVol').textContent = splitTotalVolume.toFixed(3);
    document.getElementById('tfootSplitGrossSubtotal').textContent = `₱ ${splitGrossAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

    // Update Summary
    document.getElementById('summaryTotalLogs').textContent = `${Number(grandTotalLogs.toFixed(2)).toString()} pcs`;
    document.getElementById('summaryTotalVol').textContent = `${grandTotalVolume.toFixed(3)} m³`;
    document.getElementById('summaryGrossVal').textContent = `₱ ${grandGrossAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    document.getElementById('summaryDeductions').textContent = `- ₱ ${totalDeductions.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    document.getElementById('summaryDriverAssistance').textContent = `+ ₱ ${driversAssistance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    document.getElementById('summaryNetPayable').textContent = `₱ ${netPayable.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

document.addEventListener('DOMContentLoaded', () => {
    const scaleForm = document.getElementById('scaleForm');
    const matrixRowsSelector = '#standardMatrixBody tr.row-item, #splitMatrixBody tr.row-item';
    const restoreRowNames = row => row.querySelectorAll('[data-submit-name]').forEach(control => {
        control.name = control.dataset.submitName;
        delete control.dataset.submitName;
    });

    if (scaleForm) {
        ['input', 'change', 'click'].forEach(eventName => {
            scaleForm.addEventListener(eventName, event => {
                const row = event.target.closest(matrixRowsSelector);
                if (row) restoreRowNames(row);
            });
        });

        scaleForm.addEventListener('submit', () => {
            scaleForm.querySelectorAll(matrixRowsSelector).forEach(row => {
                const quantity = parseInt(row.querySelector('.row-qty, .row-qty-input')?.value || '0', 10) || 0;
                if (quantity > 0) {
                    restoreRowNames(row);
                    return;
                }

                row.querySelectorAll('[name^="items["]').forEach(control => {
                    control.dataset.submitName = control.name;
                    control.removeAttribute('name');
                });
            });
        });
    }

    // AUTO-REFRESH PRICES ON PAGE LOAD (ensures fresh rates from superadmin updates)
    const autoRefreshPrices = async () => {
        try {
            const res = await fetch('{{ route('api.price-matrix') }}', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('Failed to fetch price matrix');
            const data = await res.json();

            // Replace price matrix and clear rate cache for fresh fetches
            priceMatrix = data;
            Object.keys(rateCache).forEach(k => delete rateCache[k]); // Clear cache
            categoryList = Array.from(new Set(data.map(i => (i.category || '').toUpperCase()))).sort();

            // Update selects in existing rows
            document.querySelectorAll('select.row-cat, select.row-cat-select').forEach(sel => {
                const current = sel.value;
                sel.innerHTML = categoryList.map(c => `<option value="${c}">${c}</option>`).join('');
                if (categoryList.includes(current)) sel.value = current;
            });

            const refreshedAtSpan = document.getElementById('pricesRefreshedAt');
            if (refreshedAtSpan) {
                refreshedAtSpan.textContent = new Date().toLocaleString();
            }
            recalculateAll();
        } catch (err) {
            console.error('Auto-refresh prices failed (will use embedded data):', err);
        }
    };

    // Run auto-refresh on page load
    autoRefreshPrices();
    
    // Set auto-refresh timer (refresh every 5 minutes to catch superadmin updates)
    setInterval(autoRefreshPrices, 5 * 60 * 1000);

    // Edit pages restore only saved rows in their own DOMContentLoaded handler.
    if (!document.getElementById('items_json')) {
        initialRows.forEach(row => addRow({
            category: row.category,
            grade: row.grade,
            is_split: false,
            length: '2.6',
            diameter: row.diameter,
            quantity: 0,
            isPreset: true
        }));

        initialRows.forEach(row => addRow({
            category: row.category,
            gradeA: 'Good',
            lengthA: '1.3',
            diameterA: row.diameter,
            gradeB: 'Sawmill',
            lengthB: '1.3',
            diameterB: row.diameter,
            is_split: true,
            quantity: 0,
            isPreset: false
        }));
    }

    document.getElementById('addRowBtn').addEventListener('click', () => addRow({
        category: defaultCategory,
        grade: 'Good',
        is_split: false,
        length: '2.6',
        diameter: 20,
        quantity: 1,
        isPreset: false
    }));

    document.getElementById('addSplitRowBtn').addEventListener('click', () => addRow({
        category: defaultCategory,
        gradeA: 'Good',
        lengthA: '1.3',
        diameterA: 24,
        gradeB: 'Sawmill',
        lengthB: '1.3',
        diameterB: 22,
        is_split: true,
        quantity: 1,
        isPreset: false
    }));

    document.querySelectorAll('.deduction-input').forEach(input => {
        input.addEventListener('input', recalculateAll);
    });

    const otherDeductionLabelInput = document.getElementById('other_deduction_label');
    if (otherDeductionLabelInput) {
        otherDeductionLabelInput.addEventListener('input', recalculateAll);
        otherDeductionLabelInput.addEventListener('change', recalculateAll);
    }

    // Refresh Prices Button (Manual AJAX) - fetch latest price matrix and clear cache
    const refreshBtn = document.getElementById('refreshPricesBtn');
    const refreshedAtSpan = document.getElementById('pricesRefreshedAt');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async () => {
            refreshBtn.disabled = true;
            refreshBtn.textContent = 'Refreshing...';
            try {
                const res = await fetch('{{ route('api.price-matrix') }}', { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Failed to fetch price matrix');
                const data = await res.json();

                // Replace price matrix data and CLEAR RATE CACHE for fresh fetches
                priceMatrix = data;
                Object.keys(rateCache).forEach(k => delete rateCache[k]); // Clear cache to force fresh DB queries
                categoryList = Array.from(new Set(data.map(i => (i.category || '').toUpperCase()))).sort();

                // Update selects in existing rows
                document.querySelectorAll('select.row-cat, select.row-cat-select').forEach(sel => {
                    const current = sel.value;
                    sel.innerHTML = categoryList.map(c => `<option value="${c}">${c}</option>`).join('');
                    if (categoryList.includes(current)) sel.value = current;
                });

                refreshedAtSpan.textContent = new Date().toLocaleString();
                recalculateAll();
            } catch (err) {
                console.error(err);
                alert('Failed to refresh prices: ' + err.message);
            } finally {
                refreshBtn.disabled = false;
                refreshBtn.textContent = 'Refresh Prices';
            }
        });
    }
});
</script>
