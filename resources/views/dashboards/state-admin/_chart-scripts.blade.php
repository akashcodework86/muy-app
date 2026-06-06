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
    const stEl = document.getElementById('stateTrendCurveChart');
    if (stEl && trendLabels.length) {
        const cx = stEl.getContext('2d');
        const dh = stEl.parentElement?.clientHeight || 168;
        const dFill = cx.createLinearGradient(0, 0, 0, dh);
        dFill.addColorStop(0, chartFill);
        dFill.addColorStop(1, chartFill.replace(/[\d.]+\)$/, '0.02)'));
        new Chart(stEl, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'State CFA',
                    data: trendValues,
                    borderColor: chartPrimary,
                    backgroundColor: dFill,
                    fill: true,
                    tension: 0.42,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#64748b', maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { stepSize: 1, font: { size: 9 } } }
                }
            }
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

    const dtComp = @json($insights['districtTargetComparison'] ?? ['labels' => [], 'achieved' => [], 'targets' => []]);
    const dtEl = document.getElementById('chartDistrictTarget');
    if (dtEl && (dtComp.labels || []).length) {
        new Chart(dtEl, {
            type: 'bar',
            data: {
                labels: dtComp.labels,
                datasets: [
                    { label: 'Achieved', data: dtComp.achieved, backgroundColor: chartPrimary, borderRadius: 4 },
                    { label: 'Target', data: dtComp.targets, backgroundColor: '#cbd5e1', borderRadius: 4 }
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
