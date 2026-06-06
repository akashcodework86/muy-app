@php
    $ins = $insights ?? [];
    $geo = $ins['geo'] ?? ['districts' => 0, 'blocks' => 0];
@endphp

<section class="sad-panel" data-sad-panel="insights">
    <div class="sad-insights-grid sad-insights-grid--2">
        <div class="sad-card">
            <div class="sad-card__head">
                <h2 class="sad-card__title"><i class="fa-solid fa-filter" aria-hidden="true"></i> CFA pipeline funnel</h2>
                <span class="sad-card__tag">State level</span>
            </div>
            <p class="sad-card__hint">Submitted → onboarded (locked) → services delivered</p>
            <div class="sad-chart-box--md">
                <canvas id="chartFunnel" aria-label="CFA pipeline funnel"></canvas>
            </div>
        </div>
        <div class="sad-card">
            <div class="sad-card__head">
                <h2 class="sad-card__title"><i class="fa-solid fa-chart-area" aria-hidden="true"></i> Intake vs onboarding</h2>
                <span class="sad-card__tag">14 days</span>
            </div>
            <p class="sad-card__hint">Daily CFA submissions and locked onboarding</p>
            <div class="sad-chart-box--md">
                <canvas id="chartDualTrend" aria-label="CFA and onboarding trend"></canvas>
            </div>
        </div>
    </div>

    <div class="sad-insights-grid" style="margin-top:0.55rem;">
        <div class="sad-card">
            <h2 class="sad-card__title"><i class="fa-solid fa-users" aria-hidden="true"></i> Applicant category</h2>
            <div class="sad-chart-box--donut"><canvas id="chartCategoryMix"></canvas></div>
        </div>
        <div class="sad-card">
            <h2 class="sad-card__title"><i class="fa-solid fa-venus-mars" aria-hidden="true"></i> Gender split</h2>
            <div class="sad-chart-box--donut"><canvas id="chartGenderMix"></canvas></div>
        </div>
        <div class="sad-card">
            <h2 class="sad-card__title"><i class="fa-solid fa-route" aria-hidden="true"></i> CFA source</h2>
            <div class="sad-chart-box--donut"><canvas id="chartSourceMix"></canvas></div>
        </div>
    </div>

    <div class="sad-insights-grid" style="margin-top:0.55rem;">
        <div class="sad-card">
            <h2 class="sad-card__title"><i class="fa-solid fa-seedling" aria-hidden="true"></i> Business stage</h2>
            <div class="sad-chart-box--donut"><canvas id="chartStageDonut"></canvas></div>
        </div>
        <div class="sad-card">
            <h2 class="sad-card__title"><i class="fa-solid fa-certificate" aria-hidden="true"></i> Registration status</h2>
            <div class="sad-chart-box--donut"><canvas id="chartRegistrationMix"></canvas></div>
        </div>
        <div class="sad-card">
            <h2 class="sad-card__title"><i class="fa-solid fa-person-dress" aria-hidden="true"></i> Lakhpati Didi</h2>
            <div class="sad-chart-box--donut"><canvas id="chartLakhpatiMix"></canvas></div>
        </div>
    </div>

    <div class="sad-signals" style="margin-top:0.55rem;">
        <div class="sad-signal">
            <span>Districts with CFA</span>
            <strong>{{ number_format((int) ($geo['districts'] ?? 0)) }}</strong>
        </div>
        <div class="sad-signal">
            <span>Blocks with CFA</span>
            <strong>{{ number_format((int) ($geo['blocks'] ?? 0)) }}</strong>
        </div>
        <div class="sad-signal">
            <span>Total districts in MIS</span>
            <strong>{{ number_format($districtsCount ?? 0) }}</strong>
        </div>
        <div class="sad-signal">
            <span>Coverage</span>
            <strong>
                @php
                    $dc = (int) ($districtsCount ?? 0);
                    $gc = (int) ($geo['districts'] ?? 0);
                @endphp
                {{ $dc > 0 ? (int) round(($gc / $dc) * 100) : 0 }}% districts
            </strong>
        </div>
    </div>
</section>
