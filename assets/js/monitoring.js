'use strict';
(() => {
    const $ = id => document.getElementById(id);
    const form = $('filters'), modal = $('gasSlipModal');
    const filterModal = $('filter-dialog');
    const defaults = {year: document.body.dataset.year, from: '', to: '', department: '0', area: '0', status: ''};
    let applied = {...defaults}, connectionIssue = false;
    const state = {page: 1, size: 25, sort: 'date', direction: 'desc'};
    let chart, graph = [], options, selectedBar = null, timer, debounce, controller, detailController;
    let version = 0, busy = false, pages = 1;
    let detailReturn;
    const interval = Number(document.body.dataset.refresh);
    const value = name => form.elements.namedItem(name).value;
    const set = (name, v) => { form.elements.namedItem(name).value = String(v); };
    const requestFilters = () => ({...applied, search: value('search')});
    function refreshStatus() {
        $('refresh-status').textContent = busy ? 'Updating' : connectionIssue ? 'Connection issue — automatic refresh will retry' : modal.classList.contains('show') ? 'Auto-refresh paused while viewing details' : document.hidden ? 'Auto-refresh paused while tab is hidden' : 'Auto-refresh active';
        $('refresh-status').dataset.state = connectionIssue ? 'error' : busy ? 'updating' : 'active';
    }
    function filterIndicator() {
        const count = Object.keys(defaults).filter(key => String(applied[key]) !== defaults[key]).length;
        $('filter-count').hidden = !count;
        $('filter-count').textContent = count ? ` ${count} active` : '';
    }
    function restoreApplied() {
        const oldArea = [...$('area').options].find(option => option.value === String(applied.area));
        for (const key of Object.keys(defaults)) if (key !== 'area') set(key, applied[key]);
        updateAreas();
        if (oldArea && ![...$('area').options].some(option => option.value === String(applied.area))) $('area').add(new Option(oldArea.text, applied.area));
        set('area', applied.area);
        $('filter-error').textContent = '';
    }
    const text = (tag, content, className) => {
        const node = document.createElement(tag);
        node.textContent = content ?? '—';
        if (className) node.className = className;
        return node;
    };
    async function api(params, signal) {
        const response = await fetch('monitoring.php?' + new URLSearchParams(params), {cache: 'no-store', credentials: 'same-origin', signal});
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Unable to load monitoring data.');
        return data;
    }
    function schedule() {
        clearTimeout(timer);
        if (!document.hidden && !modal.classList.contains('show')) timer = setTimeout(() => load(false), interval);
    }
    function updateAreas() {
        if (!options) return;
        const previous = value('area'), department = Number(value('department'));
        const areas = new Map(options.areas.filter(a => !department || Number(a.department_id) === department).map(a => [a.id, a]));
        $('area').replaceChildren(new Option('All Areas', '0'), ...Array.from(areas.values(), a => new Option(a.name, a.id)));
        if ([...$('area').options].some(o => o.value === previous)) set('area', previous);
    }
    function render(data) {
        if (data.options && !filterModal.open) {
            options = data.options;
            const department = value('department'), area = value('area'), year = value('year');
            const oldDepartment = form.elements.department.selectedOptions[0];
            const departments = options.departments.map(d => new Option(d.name, d.id));
            // Retain the active filter even if its last matching record was removed.
            if (department !== '0' && !departments.some(d => d.value === department)) departments.push(new Option(oldDepartment.text, department));
            $('department').replaceChildren(new Option('All Departments', '0'), ...departments); set('department', department);
            const oldArea = form.elements.area.selectedOptions[0]; updateAreas();
            if (![...$('area').options].some(o => o.value === area)) $('area').add(new Option(oldArea.text, area));
            set('area', area);
            const years = [...new Set([Number(document.body.dataset.year), Number(year), ...options.years.map(y => Number(y.year))])].sort((a,b) => b-a);
            $('year').replaceChildren(...years.map(y => new Option(y, y))); set('year', year);
        }
        state.page = data.page; pages = data.pages;
        for (const key of ['total', 'approved', 'printed']) $(key).textContent = data.counts[key].toLocaleString();
        const body = $('records'); body.replaceChildren();
        data.rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'monitoring-slip-row';
            tr.dataset.id = row.id;
            tr.tabIndex = 0;
            tr.setAttribute('aria-label', `View gas slip ${row.gas_slip_id}`);
            const number = document.createElement('td');
            const link = text('button', row.gas_slip_id, 'slip-link');
            link.type = 'button'; link.dataset.id = row.id; number.append(link); tr.append(number);
            for (const key of ['date_display', 'department', 'area', 'requested_by', 'purpose', 'routes']) tr.append(text('td', (row.display?.[key] ?? row[key]) || '—'));
            const status = document.createElement('td');
            status.append(text('span', row.status === 'printed' ? 'Printed' : 'Approved', 'badge ' + row.status));
            tr.append(status); body.append(tr);
        });
        if (!data.rows.length) {
            const cell = text('td', 'No Approved or Printed gas slips match these filters.'); cell.colSpan = 8;
            const row = document.createElement('tr'); row.append(cell); body.append(row);
        }
        const total = data.counts.total;
        $('range').textContent = `Showing ${total ? (data.page - 1) * data.size + 1 : 0}–${Math.min(data.page * data.size, total)} of ${total.toLocaleString()} records`;
        $('page-label').textContent = `Page ${data.page} of ${data.pages}`;
        $('previous').disabled = data.page <= 1; $('next').disabled = data.page >= pages;
        document.querySelectorAll('th[data-column]').forEach(th => {
            const active = th.dataset.column === state.sort;
            th.setAttribute('aria-sort', active ? (state.direction === 'asc' ? 'ascending' : 'descending') : 'none');
            th.querySelector('span').textContent = active ? (state.direction === 'asc' ? '▲' : '▼') : '↕';
        });
        graph = data.graph;
        const colors = ['#198754','#198754','#198754','#198754','#198754','#0d6efd','#0d6efd','#0d6efd','#0d6efd','#0d6efd','#0d6efd','#0d6efd','#0d6efd','#ffc107','#dc3545','#6f42c1','#fd7e14','#20c997','#6610f2','#0dcaf0','#adb5bd','#343a40','#6c757d'];
        const dataset = {label: 'Approved and Printed Gas Slips', data: graph.map(g => Number(g.total)), backgroundColor: graph.map((g,i) => colors[(g.color_index ?? i) % colors.length]), borderWidth: 1, borderRadius: 5};
        if (!chart) {
            chart = new Chart($('distribution').getContext('2d'), {
                type: 'bar', data: {labels: graph.map(g => g.label), datasets: [dataset]},
                options: {
                    responsive: true, maintainAspectRatio: false, legend: {display: false},
                    tooltips: {callbacks: {label: (item, data) => data.datasets[0].data[item.index] + ' approved/printed slip(s)'}},
                    scales: {xAxes: [{ticks: {beginAtZero: true, precision: 0}, scaleLabel: {display: true}}], yAxes: [{ticks: {autoSkip: false}}]},
                    layout: {padding: 20},
                    onClick: (event, elements) => {
                        if (!elements.length) return;
                        const bar = graph[elements[0]._index];
                        const key = `${bar.department_id}:${bar.area_id || 0}`;
                        if (selectedBar === key) { set('department', 0); updateAreas(); set('area', 0); selectedBar = null; }
                        else { set('department', bar.department_id); updateAreas(); set('area', bar.area_id || 0); selectedBar = key; }
                        applied.department = value('department'); applied.area = value('area'); filterIndicator();
                        state.page = 1; load();
                    }
                }
            });
        } else {
            chart.data.labels = graph.map(g => g.label); chart.data.datasets[0] = dataset; chart.update(0);
        }
        $('distribution').parentElement.style.minWidth = `${Math.max(500, graph.length * 55)}px`;
        if (!$('overview-body').hidden) chart.resize();
        $('chart-empty').hidden = graph.some(g => Number(g.total) > 0);
        $('updated').textContent = 'Last Updated: ' + data.updated;
    }
    async function load(interrupt = true) {
        if (modal.classList.contains('show') || document.hidden || (!interrupt && busy)) { schedule(); return; }
        if (controller) controller.abort();
        const current = ++version; busy = false;
        clearTimeout(debounce); clearTimeout(timer);
        controller = new AbortController(); busy = true; refreshStatus();
        $('records').setAttribute('aria-busy', 'true');
        try {
            const params = requestFilters();
            const data = await api({...params, ...state, action: 'data'}, controller.signal);
            if (current !== version || modal.classList.contains('show')) return;
            render(data); connectionIssue = false; $('message').textContent = '';
        } catch (error) {
            if (error.name !== 'AbortError' && current === version) { connectionIssue = true; $('message').textContent = error.message + ' Displayed results are retained; automatic refresh will retry.'; }
        } finally {
            if (current === version) { busy = false; $('records').removeAttribute('aria-busy'); refreshStatus(); schedule(); }
        }
    }
    async function openDetail(id) {
        clearTimeout(timer); clearTimeout(debounce); ++version;
        if (controller) controller.abort(); busy = false;
        if (detailController) detailController.abort();
        detailController = new AbortController();
        openGasSlipDetails(id, {signal:detailController.signal});
        refreshStatus();
    }
    $('overview-toggle').addEventListener('click', () => {
        const body = $('overview-body'), button = $('overview-toggle');
        body.hidden = !body.hidden;
        button.setAttribute('aria-expanded', String(!body.hidden));
        button.querySelector('span').textContent = body.hidden ? 'Expand' : 'Minimize';
        if (!body.hidden) requestAnimationFrame(() => {
            if (chart && !body.hidden) { chart.resize(); chart.update(0); }
        });
    });
    // Reuse the same instance as the shared sidebar's width transition runs.
    // ResizeObserver also covers restored sidebar preferences and breakpoints.
    if ('ResizeObserver' in window) {
        let resizeFrame;
        const chartResizeObserver = new ResizeObserver(() => {
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(() => {
                if (chart && !$('overview-body').hidden) { chart.resize(); chart.update(0); }
            });
        });
        chartResizeObserver.observe($('distribution').parentElement);
    }
    $('open-filters').addEventListener('click', () => { restoreApplied(); filterModal.showModal(); });
    for (const id of ['cancel-filters', 'close-filters']) $(id).addEventListener('click', () => filterModal.close());
    filterModal.addEventListener('close', () => { restoreApplied(); $('open-filters').focus(); });
    form.addEventListener('submit', e => {
        e.preventDefault();
        if (filterModal.open) {
            if (!form.reportValidity()) return;
            if (value('from') && value('to') && value('from') > value('to')) {
                $('filter-error').textContent = 'From Date must not be later than To Date.'; return;
            }
            applied = Object.fromEntries(Object.keys(defaults).map(key => [key, value(key)]));
            selectedBar = null; state.page = 1; filterIndicator(); filterModal.close();
        }
        load();
    });
    form.addEventListener('change', e => {
        if (e.target.name === 'department') { set('area', 0); updateAreas(); }
        // The existing controls edit a draft until Apply Filters is submitted.
        if (!filterModal.open && e.target.name === 'search') { state.page = 1; load(); }
    });
    form.elements.search.addEventListener('input', () => { clearTimeout(debounce); state.page = 1; ++version; if (controller) controller.abort(); busy = false; debounce = setTimeout(load, 350); });
    $('reset').addEventListener('click', () => {
        HTMLFormElement.prototype.reset.call(form); applied = {...defaults}; restoreApplied(); filterIndicator();
        Object.assign(state, {page: 1, size: 25, sort: 'date', direction: 'desc'}); $('size').value = '25'; selectedBar = null; load();
    });
    $('size').addEventListener('change', e => { state.size = Number(e.target.value); state.page = 1; load(); });
    $('export').addEventListener('click', async () => {
        const button = $('export'); button.disabled = true; button.setAttribute('aria-busy', 'true');
        // Capture filters and sort at click time without changing polling or page state.
        const params = new URLSearchParams({...requestFilters(), sort: state.sort, direction: state.direction, action: 'export'});
        try {
            const response = await fetch('monitoring.php?' + params, {cache: 'no-store', credentials: 'same-origin'});
            if (!response.ok) {
                const error = await response.json(); throw new Error(error.error || 'Unable to export report.');
            }
            if (!response.headers.get('Content-Type')?.startsWith('text/csv')) throw new Error('Unable to export report. Please sign in again and retry.');
            const blob = await response.blob(), url = URL.createObjectURL(blob);
            const link = document.createElement('a'); link.href = url;
            link.download = response.headers.get('Content-Disposition')?.match(/filename="(gas_slips_\d{8}_\d{6}\.csv)"/)?.[1] || 'gas_slips.csv';
            document.body.append(link); link.click(); link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (error) { $('message').textContent = error.message; }
        finally { button.disabled = false; button.removeAttribute('aria-busy'); }
    });
    document.querySelector('thead').addEventListener('click', e => {
        const button = e.target.closest('[data-sort]'); if (!button) return;
        state.direction = state.sort === button.dataset.sort && state.direction === 'asc' ? 'desc' : 'asc';
        state.sort = button.dataset.sort; state.page = 1; load();
    });
    $('previous').addEventListener('click', () => { if (state.page > 1) { state.page--; load(); } });
    $('next').addEventListener('click', () => { if (state.page < pages) { state.page++; load(); } });
    function openRow(row, target) {
        const scroller = $('records').closest('.table-scroll');
        detailReturn = {id:row.dataset.id, number:!!target.closest('.slip-link'),
            x:window.scrollX, y:window.scrollY, left:scroller.scrollLeft};
        openDetail(row.dataset.id);
    }
    $('records').addEventListener('click', e => {
        const row = e.target.closest('.monitoring-slip-row');
        if (!row) return;
        // The number button and row share one delegated path, so bubbling opens once.
        if (!e.target.closest('.slip-link') && e.target.closest('a,button,input,select,textarea,label,summary,[role="button"],[role="link"],[contenteditable]:not([contenteditable="false"])')) return;
        openRow(row, e.target);
    });
    $('records').addEventListener('keydown', e => {
        const row = e.target.closest('.monitoring-slip-row');
        if (!row || e.target !== row || !['Enter',' '].includes(e.key)) return;
        e.preventDefault(); // Space opens details instead of scrolling the table.
        if (!e.repeat) openRow(row, row);
    });
    modal.addEventListener('hidden.bs.modal', () => {
        if (detailController) detailController.abort();
        const returning = detailReturn; detailReturn = null;
        load().finally(() => {
            if (!returning || document.hidden) return;
            const row = [...$('records').querySelectorAll('.monitoring-slip-row')].find(r => r.dataset.id === returning.id);
            (returning.number ? row?.querySelector('.slip-link') : row)?.focus({preventScroll:true});
            $('records').closest('.table-scroll').scrollLeft = returning.left;
            window.scrollTo({left:returning.x, top:returning.y, behavior:'instant'});
        });
    });
    modal.addEventListener('shown.bs.modal', refreshStatus);
    // Match the dashboard's 60-second idle timer, activity reset, modal pause
    // and visibility behavior; replace location.reload() with a data request.
    ['mousemove','mousedown','keydown','scroll','touchstart'].forEach(event => document.addEventListener(event, schedule, {capture: true, passive: true}));
    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (document.hidden) { ++version; if (controller) controller.abort(); busy = false; }
        else if (!modal.classList.contains('show')) load();
        refreshStatus();
    });
    async function initialize() {
        try {
            options = await api({action: 'options'});
            $('department').replaceChildren(new Option('All Departments', '0'), ...options.departments.map(d => new Option(d.name, d.id)));
            const years = [...new Set([Number(document.body.dataset.year), ...options.years.map(y => Number(y.year))])].sort((a,b) => b-a);
            $('year').replaceChildren(...years.map(y => new Option(y, y))); set('year', document.body.dataset.year); updateAreas();
            await load();
        } catch (error) { connectionIssue = true; refreshStatus(); $('message').textContent = error.message; clearTimeout(timer); timer = setTimeout(initialize, interval); }
    }
    initialize();
})();
