@php
    $ins = $insights ?? [];
    $isLegacyTheme = ($dashboardTheme ?? 'revamp') === 'legacy';
    $chartPrimary = $isLegacyTheme ? '#d04a02' : '#26a69a';
    $chartSecondary = $isLegacyTheme ? '#eb8c00' : '#42a5f5';
    $chartFill = $isLegacyTheme ? 'rgba(208, 74, 2, 0.24)' : 'rgba(38, 166, 154, 0.12)';
    $districtPalette = $isLegacyTheme
        ? ['#d04a02', '#eb8c00', '#ffb600', '#2d2d2d', '#464646', '#a63d02', '#6b6b6b', '#22c55e', '#c75b12', '#b83d02', '#8b8b8b', '#d97706', '#16a34a']
        : ['#26a69a', '#42a5f5', '#ff8a65', '#ffca28', '#f06292', '#66bb6a', '#ab47bc', '#78909c', '#4db6ac', '#64b5f6', '#ffb74d', '#81c784', '#ce93d8'];
@endphp
@php
    $dtComp = $insights['districtTargetComparison'] ?? ['labels' => [], 'achieved' => [], 'targets' => [], 'periods' => [], 'default_key' => 'fy'];
    $dtPeriodsForChart = $dtComp['periods'] ?? [];
    if ($dtPeriodsForChart === [] && ($dtComp['labels'] ?? []) !== []) {
        $dtPeriodsForChart = [[
            'key' => 'fy',
            'label' => 'Full FY',
            'subtitle' => 'Cumulative CFA achieved vs annual district target',
            'achieved' => $dtComp['achieved'] ?? [],
            'targets' => $dtComp['targets'] ?? [],
        ]];
    }
    $dtChartLabels = $dtComp['labels'] ?? [];
    $dtChartDefaultKey = (string) ($dtComp['default_key'] ?? 'fy');
@endphp
<script>
(function () {
    const gridColor = 'rgba(148, 163, 184, 0.22)';
    const chartPrimary = @json($chartPrimary);
    const chartSecondary = @json($chartSecondary);
    const chartFill = @json($chartFill);
    const donutOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10 } }
        }
    };

    const makeDonut = (id, labels, values, colors) => {
        const el = document.getElementById(id);
        if (!el || !labels.length) return;
        new Chart(el, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: donutOpts
        });
    };

    document.querySelectorAll('[data-sad-tab]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-sad-tab');
            document.querySelectorAll('[data-sad-tab]').forEach((b) => {
                b.classList.toggle('is-active', b === btn);
            });
            document.querySelectorAll('[data-sad-panel]').forEach((p) => {
                p.classList.toggle('is-active', p.getAttribute('data-sad-panel') === id);
            });
        });
    });

    const trendLabels = @json($stateCfaTrend['labels'] ?? []);
    const trendValues = @json($stateCfaTrend['values'] ?? []);
    const onbTrendValues = @json($insights['onboardingTrend']['values'] ?? []);
    const paceChart = @json($stateFyPaceChart ?? []);
    const paceTargetLine = 100;
    const stEl = document.getElementById('stateTrendCurveChart');
    let pulseChartInstance = null;

    const pulseLineOpts = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10, usePointStyle: true } },
            tooltip: {
                callbacks: {
                    label(ctx) {
                        const v = ctx.parsed.y;
                        if (v === null || v === undefined) return ctx.dataset.label + ': —';
                        if (ctx.dataset.yAxisID === 'yPct' || ctx.dataset.label === 'On-pace (100%)') {
                            return ctx.dataset.label + ': ' + v + '%';
                        }
                        return ctx.dataset.label + ': ' + Number(v).toLocaleString('en-IN');
                    }
                }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#64748b', maxRotation: 0 } },
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { font: { size: 9 } } }
        }
    };

    const renderPulseChart = (mode) => {
        if (!stEl) return;
        const labels = paceChart.labels || [];
        const hasMonthly = labels.length > 0;
        const dailyLabels = paceChart.daily?.labels || trendLabels;
        const hintEl = document.querySelector('[data-sad-pulse-hint]');

        document.querySelectorAll('[data-sad-pulse-tab]').forEach((btn) => {
            const active = btn.getAttribute('data-sad-pulse-tab') === mode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        if (pulseChartInstance) {
            pulseChartInstance.destroy();
            pulseChartInstance = null;
        }

        let datasets = [];
        let options = JSON.parse(JSON.stringify(pulseLineOpts));

        if (mode === 'pace' && hasMonthly) {
            if (hintEl) {
                hintEl.textContent = 'Cumulative achievement vs prorated FY target — 100% dashed line means on pace.'
                    + (paceChart.cfa_target ? ' CFA target ' + Number(paceChart.cfa_target).toLocaleString('en-IN') + '.' : '')
                    + (paceChart.onboarding_target ? ' Onboard target ' + Number(paceChart.onboarding_target).toLocaleString('en-IN') + '.' : '');
            }
            const onPaceLine = labels.map(() => paceTargetLine);
            datasets = [
                {
                    label: 'CFA pace',
                    data: paceChart.cfa_pace_pct || [],
                    borderColor: chartPrimary,
                    backgroundColor: chartFill.replace(/[\d.]+\)$/, '0.08)'),
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    spanGaps: true,
                },
                {
                    label: 'Onboarding pace',
                    data: paceChart.onboarding_pace_pct || [],
                    borderColor: chartSecondary,
                    backgroundColor: 'transparent',
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    spanGaps: true,
                },
                {
                    label: 'On-pace (100%)',
                    data: onPaceLine,
                    borderColor: '#ef4444',
                    borderDash: [6, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    fill: false,
                    tension: 0,
                },
            ];
            options.scales.y = {
                beginAtZero: true,
                suggestedMax: Math.max(120, ...onPaceLine, ...(paceChart.cfa_pace_pct || []), ...(paceChart.onboarding_pace_pct || []).filter(v => v != null)),
                grid: { color: gridColor },
                ticks: {
                    font: { size: 9 },
                    callback: (v) => v + '%',
                },
                title: { display: true, text: 'Pace of target', font: { size: 9 }, color: '#64748b' },
            };
        } else if (mode === 'cfa' && hasMonthly) {
            if (hintEl) hintEl.textContent = 'Monthly cumulative CFA vs prorated state target (straight pace line).';
            datasets = [
                {
                    label: 'CFA cumulative',
                    data: paceChart.cfa_cumulative || [],
                    borderColor: chartPrimary,
                    backgroundColor: chartFill,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 2,
                },
            ];
            if ((paceChart.cfa_pace_expected || []).some(v => v > 0)) {
                datasets.push({
                    label: 'CFA target pace',
                    data: paceChart.cfa_pace_expected || [],
                    borderColor: '#94a3b8',
                    borderDash: [5, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    fill: false,
                    tension: 0,
                });
            }
            options.scales.x = { ...options.scales.x, ticks: { ...options.scales.x.ticks, maxRotation: 45, minRotation: 0 } };
        } else if (mode === 'onboarding' && hasMonthly) {
            if (hintEl) hintEl.textContent = 'Monthly cumulative onboarding vs prorated state target (straight pace line).';
            datasets = [
                {
                    label: 'Onboarding cumulative',
                    data: paceChart.onboarding_cumulative || [],
                    borderColor: chartSecondary,
                    backgroundColor: 'rgba(66, 165, 245, 0.12)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 2,
                },
            ];
            if ((paceChart.onboarding_pace_expected || []).some(v => v > 0)) {
                datasets.push({
                    label: 'Onboarding target pace',
                    data: paceChart.onboarding_pace_expected || [],
                    borderColor: '#94a3b8',
                    borderDash: [5, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    fill: false,
                    tension: 0,
                });
            }
            options.scales.x = { ...options.scales.x, ticks: { ...options.scales.x.ticks, maxRotation: 45, minRotation: 0 } };
        } else {
            if (hintEl) hintEl.textContent = 'Daily new CFA and onboarding entries — last 14 days.';
            const cx = stEl.getContext('2d');
            const dh = stEl.parentElement?.clientHeight || 180;
            const dFill = cx.createLinearGradient(0, 0, 0, dh);
            dFill.addColorStop(0, chartFill);
            dFill.addColorStop(1, chartFill.replace(/[\d.]+\)$/, '0.02)'));
            datasets = [
                {
                    label: 'CFA per day',
                    data: paceChart.daily?.cfa || trendValues,
                    borderColor: chartPrimary,
                    backgroundColor: dFill,
                    fill: true,
                    tension: 0.42,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                },
                {
                    label: 'Onboarding per day',
                    data: paceChart.daily?.onboarding || onbTrendValues,
                    borderColor: chartSecondary,
                    backgroundColor: 'transparent',
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                },
            ];
            options.plugins.legend = { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10 } };
        }

        const chartLabels = mode === 'daily'
            ? dailyLabels
            : (paceChart.labels || []);

        if (!chartLabels.length) return;

        pulseChartInstance = new Chart(stEl, {
            type: 'line',
            data: { labels: chartLabels, datasets },
            options,
        });
    };

    if (stEl && ((paceChart.labels || []).length || trendLabels.length)) {
        renderPulseChart('pace');
        document.querySelectorAll('[data-sad-pulse-tab]').forEach((btn) => {
            btn.addEventListener('click', () => {
                renderPulseChart(btn.getAttribute('data-sad-pulse-tab'));
            });
        });
    }

    const dualEl = document.getElementById('chartDualTrend');
    if (dualEl && trendLabels.length) {
        new Chart(dualEl, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'CFA',
                        data: trendValues,
                        borderColor: chartPrimary,
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 0
                    },
                    {
                        label: 'Onboarded',
                        data: onbTrendValues,
                        borderColor: chartSecondary,
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 9 } } },
                    y: { beginAtZero: true, grid: { color: gridColor } }
                }
            }
        });
    }

    const funnel = @json($insights['funnel'] ?? ['labels' => [], 'values' => []]);
    const funnelEl = document.getElementById('chartFunnel');
    if (funnelEl && (funnel.labels || []).length) {
        new Chart(funnelEl, {
            type: 'bar',
            data: {
                labels: funnel.labels,
                datasets: [{
                    label: 'Count',
                    data: funnel.values,
                    backgroundColor: [chartPrimary, chartSecondary, '#78716c'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: gridColor } }
                }
            }
        });
    }

    const mixCharts = {
        chartCategoryMix: @json($insights['categoryMix'] ?? []),
        chartGenderMix: @json($insights['genderMix'] ?? []),
        chartSourceMix: @json($insights['sourceMix'] ?? []),
        chartStageDonut: @json($insights['stageDonut'] ?? []),
        chartRegistrationMix: @json($insights['registrationMix'] ?? []),
        chartLakhpatiMix: @json($insights['lakhpatiMix'] ?? []),
    };
    Object.entries(mixCharts).forEach(([id, data]) => {
        makeDonut(id, data.labels || [], data.values || [], data.colors || []);
    });

    const dLabels = @json($cfaByDistrict['labels']);
    const dValues = @json($cfaByDistrict['values']);
    const districtPalette = @json($districtPalette);
    const districtValueLabelsPlugin = {
        id: 'districtValueLabelsPlugin',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            const values = chart.data.datasets[0]?.data || [];
            ctx.save();
            ctx.font = '700 10px DM Sans, sans-serif';
            ctx.fillStyle = '#0f172a';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            meta.data.forEach((bar, i) => {
                const raw = Number(values[i] ?? 0);
                ctx.fillText(raw.toLocaleString('en-IN'), bar.x + 6, bar.y);
            });
            ctx.restore();
        }
    };

    const districtEl = document.getElementById('chartDistrictCfa');
    if (districtEl) {
        new Chart(districtEl, {
            type: 'bar',
            plugins: [districtValueLabelsPlugin],
            data: {
                labels: dLabels.length ? dLabels : ['No data'],
                datasets: [{
                    label: 'CFA',
                    data: dLabels.length ? dValues : [0],
                    backgroundColor: dLabels.length
                        ? dValues.map((_, i) => districtPalette[i % districtPalette.length])
                        : ['#e2e8f0'],
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 48 } },
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: gridColor } },
                    y: { grid: { display: false }, ticks: { font: { size: 9 } } }
                }
            }
        });
    }

    const dtLabels = @json($dtChartLabels);
    const dtPeriods = @json($dtPeriodsForChart);
    const dtDefaultKey = @json($dtChartDefaultKey);
    const dtEl = document.getElementById('chartDistrictTarget');
    let dtChartInstance = null;

    const renderDistrictTargetChart = (periodKey) => {
        if (!dtEl || !dtLabels.length || !dtPeriods.length) {
            return;
        }
        const period = dtPeriods.find((p) => p.key === periodKey) || dtPeriods.find((p) => p.key === dtDefaultKey) || dtPeriods[0];
        const hintEl = document.querySelector('[data-sad-dt-hint]');
        if (hintEl && period?.subtitle) {
            hintEl.textContent = period.subtitle;
        }
        document.querySelectorAll('[data-sad-dt-tab]').forEach((btn) => {
            const active = btn.getAttribute('data-sad-dt-tab') === period.key;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (dtChartInstance) {
            dtChartInstance.destroy();
        }
        dtChartInstance = new Chart(dtEl, {
            type: 'bar',
            data: {
                labels: dtLabels,
                datasets: [
                    { label: 'Achieved', data: period.achieved || [], backgroundColor: chartPrimary, borderRadius: 4 },
                    { label: 'Target', data: period.targets || [], backgroundColor: '#cbd5e1', borderRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 45, minRotation: 45 } },
                    y: { beginAtZero: true, grid: { color: gridColor } }
                }
            }
        });
    };

    if (dtEl && dtLabels.length && dtPeriods.length) {
        renderDistrictTargetChart(dtDefaultKey);
        document.querySelectorAll('[data-sad-dt-tab]').forEach((btn) => {
            btn.addEventListener('click', () => {
                renderDistrictTargetChart(btn.getAttribute('data-sad-dt-tab'));
            });
        });
    }

    const topBlocks = @json($insights['topBlocks'] ?? ['labels' => [], 'values' => [], 'colors' => []]);
    const blocksEl = document.getElementById('chartTopBlocks');
    if (blocksEl && (topBlocks.labels || []).length) {
        new Chart(blocksEl, {
            type: 'bar',
            data: {
                labels: topBlocks.labels,
                datasets: [{
                    label: 'CFA',
                    data: topBlocks.values,
                    backgroundColor: topBlocks.colors || chartPrimary,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: gridColor } },
                    y: { grid: { display: false }, ticks: { font: { size: 9 } } }
                }
            }
        });
    }

    const staffTop = @json($insights['staffTopChart'] ?? ['labels' => [], 'values' => []]);
    const staffChartEl = document.getElementById('chartStaffTop');
    if (staffChartEl && (staffTop.labels || []).length) {
        new Chart(staffChartEl, {
            type: 'bar',
            data: {
                labels: staffTop.labels,
                datasets: [{
                    label: 'CFA',
                    data: staffTop.values,
                    backgroundColor: chartSecondary,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: gridColor } },
                    y: { grid: { display: false }, ticks: { font: { size: 9 } } }
                }
            }
        });
    }

    const searchInput = document.getElementById('stateStaffCfaSearch');
    const districtSelect = document.getElementById('stateStaffCfaDistrictFilter');
    const staffRows = Array.from(document.querySelectorAll('#stateStaffCfaList .sad-staff-row'));
    const noResults = document.getElementById('stateStaffCfaNoResults');

    const applyStaffCfaFilters = () => {
        if (!staffRows.length) return;
        const q = (searchInput?.value || '').trim().toLowerCase();
        const district = (districtSelect?.value || '').trim().toLowerCase();
        let visible = 0;
        staffRows.forEach((row) => {
            const show = (q === '' || (row.dataset.name || '').includes(q))
                && (district === '' || (row.dataset.district || '') === district);
            row.style.display = show ? '' : 'none';
            if (show) visible += 1;
        });
        if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
    };
    searchInput?.addEventListener('input', applyStaffCfaFilters);
    districtSelect?.addEventListener('change', applyStaffCfaFilters);
})();
</script>
