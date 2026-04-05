@php
    $challengeLabels = [
        'Unavailability of Packaging Material' => 'Unavailability of Packaging Material',
        'Sales & Marketing' => 'Sales & Marketing',
        'Branding' => 'Branding',
        'Loan or Financial Issue' => 'Loan or Financial Issue',
        'License or Legal support' => 'License or Legal Support',
        'Lack of Government Scheme Information' => 'Lack of Government Scheme Info',
        'Lack of Technical Knowledge' => 'Lack of Technical Knowledge',
        'Lack of Training' => 'Lack of Training',
        'Unavailability of Raw material' => 'Unavailability of Raw Material',
        'Wild Animals Destroy our Crops' => 'Wild Animal Crop Damage',
        'Lack of Mentor' => 'Lack of Mentor',
        'Lack of Digital Marketing Knowledge' => 'No Digital Marketing Knowledge',
        'Networking issue to sell our Products' => 'Networking Issues for Sales',
        'Lack of teamwork' => 'Lack of Teamwork',
        'Unavailability of the Machine' => 'Machine Unavailability',
        'Connectivity Challenge for Homestay' => 'Homestay Connectivity Issues',
        'Human Resource Problem Due to Migration' => 'Workforce Migration',
        'Lack of Skills' => 'Lack of Skills',
        'Capacity Building issue' => 'Capacity Building Issue',
        'Seasonal work' => 'Seasonal Work Only',
        'Lack of Pricing and Costing' => 'Pricing & Costing Knowledge',
        'Exploitation by intermediaries' => 'Exploitation by Middlemen',
        'Machine Servicing Challenge' => 'Machine Servicing Challenge',
        'Animal attack while collecting raw Material Like Pine Leaf, Ringal, Bamboo' => 'Animal Attacks While Collecting Raw Materials',
        'Not getting enough money after selling our Products' => 'Low Returns from Sales',
        'Lack of Logistics Connectivity' => 'Logistics Connectivity Issue',
        'Insufficient Water for Farming' => 'Insufficient Farming Water',
        'Payment issue' => 'Payment Issues',
        'Diseases in Animals' => 'Animal Diseases',
        'Not getting a Trainer for Product Development' => 'No Trainer for Product Development',
        'No Update on District Level Industrial Policies' => 'No Updates on Local Policies',
        'No Idea of a Business Plan/ Road map/ vision for a business cycle.' => 'No Business Plan / Roadmap / Vision',
    ];
    $expectationLabels = [
        'Advise on ideation of the business idea' => 'Advise on ideation of the business idea',
        'Support in prototyping' => 'Support in prototyping',
        'Market testing' => 'Market testing',
        'Support for IPR and other licenses' => 'Support for IPR and other licenses',
        'Access to market' => 'Access to market',
        'Access to mentors' => 'Access to mentors',
        'Access to finance' => 'Access to finance',
        'Access to infrastructure' => 'Access to infrastructure (co-working space, makers spaces, etc.)',
        'Networking' => 'Networking',
        'Access to funders and investors' => 'Access to funders and investors',
        'Other' => 'Other',
    ];
    $oldChallenges = old('challenges', []);
    $oldExpectations = old('expectations', []);
@endphp

<div class="form-section" id="sectionB" style="display: none;">
    <h2 class="section-header">Section B — Business &amp; Expectations</h2>
    <div class="form-grid">
        <div>
            <label class="form-label" for="stageResult">Business Stage (auto-detected)</label>
            <input type="text" class="form-control" id="stageResult" name="form_stage" value="{{ old('form_stage') }}" readonly>
        </div>
        <div>
            <label class="form-label" for="business_category">Business Category <span style="color:red">*</span></label>
            <select class="form-select" name="business_category" id="business_category" required>
                <option value="">Select</option>
                @foreach (config('cfa.business_categories') as $bc)
                    <option value="{{ $bc }}" @selected(old('business_category') === $bc)>{{ $bc }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="product">Product</label>
            <select class="form-select" name="product" id="product">
                <option value="">Select</option>
            </select>
        </div>
        <div id="otherProductDiv" style="display: none;">
            <label class="form-label" for="other_product">Other product</label>
            <input type="text" class="form-control" name="other_product" id="other_product" value="{{ old('other_product') }}" maxlength="191">
        </div>

        <div>
            <label class="form-label" for="financial_support">Require financial support next year?</label>
            <select class="form-select" name="financial_support" id="financial_support">
                <option value="">Choose</option>
                <option value="Yes" @selected(old('financial_support') === 'Yes')>Yes</option>
                <option value="No" @selected(old('financial_support') === 'No')>No</option>
            </select>
        </div>
        <div id="financialAmountDiv" style="display: none;">
            <label class="form-label" for="financial_amount">Financial support amount</label>
            <input type="text" class="form-control" name="financial_amount" id="financial_amount" value="{{ old('financial_amount') }}">
            <p id="financial-amount-error" class="text-red-500 hidden">Valid amount</p>
            <p id="financial-amount-preview" class="text-gray-700"></p>
        </div>

        <div>
            <label class="form-label" for="location_type">Location type</label>
            <select class="form-select" name="location_type" id="location_type">
                <option value="">Select</option>
                @foreach (config('cfa.location_types') as $lt)
                    <option value="{{ $lt }}" @selected(old('location_type') === $lt)>{{ $lt }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group full-width" data-cfa-required-group="challenges">
            <label class="form-label">Challenges you faced <span style="color:red">*</span></label>
            <div class="checkbox-group">
                @foreach (config('cfa.challenge_values') as $ch)
                    <label>
                        <input type="checkbox" name="challenges[]" value="{{ $ch }}" @checked(in_array($ch, $oldChallenges, true))>
                        {{ $challengeLabels[$ch] ?? $ch }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="form-label" for="migrated_for_employment">Migrated out of state for employment? <span style="color:red">*</span></label>
            <select class="form-select" name="migrated_for_employment" id="migrated_for_employment" required>
                <option value="">Select</option>
                <option value="Yes" @selected(old('migrated_for_employment') === 'Yes')>Yes</option>
                <option value="No" @selected(old('migrated_for_employment') === 'No')>No</option>
            </select>
        </div>

        <div class="form-group full-width">
            <label class="form-label" for="business_vision">Business vision in 5 years</label>
            <textarea class="form-control" name="business_vision" id="business_vision" rows="4" placeholder="Describe your business vision...">{{ old('business_vision') }}</textarea>
        </div>

        <div>
            <label class="form-label" for="training_mode">Preferred training mode <span style="color:red">*</span></label>
            <select class="form-select" name="training_mode" id="training_mode" required>
                <option value="">Select</option>
                @foreach (config('cfa.training_modes') as $tm)
                    <option value="{{ $tm }}" @selected(old('training_mode') === $tm)>{{ $tm }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label" for="info_source">Source of information <span style="color:red">*</span></label>
            <select class="form-select" name="info_source" id="info_source" required>
                <option value="">Select</option>
                @foreach (config('cfa.info_sources') as $is)
                    <option value="{{ $is }}" @selected(old('info_source') === $is)>{{ $is }}</option>
                @endforeach
            </select>
        </div>
        <div id="resourceNameDiv" class="form-group full-width" style="display: none;">
            <label class="form-label" for="resource_name">Staff / resource name</label>
            <input type="text" class="form-control" name="resource_name" id="resource_name" value="{{ old('resource_name') }}" maxlength="191" placeholder="Name">
        </div>

        <div>
            <label class="form-label" for="techuse">Technology for business activity <span style="color:red">*</span></label>
            <select class="form-select" name="techuse" id="techuse" required>
                <option value="">Select</option>
                @foreach (config('cfa.techuse_options') as $tu)
                    <option value="{{ $tu }}" @selected(old('techuse') === $tu)>{{ $tu }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label" for="sustainability">Environmental sustainability contribution? <span style="color:red">*</span></label>
            <select class="form-select" name="sustainability" id="sustainability" required>
                <option value="">Select</option>
                <option value="Yes" @selected(old('sustainability') === 'Yes')>Yes</option>
                <option value="No" @selected(old('sustainability') === 'No')>No</option>
            </select>
        </div>

        <div>
            <label class="form-label" for="empwomen">Empower women / SHGs / marginalized communities? <span style="color:red">*</span></label>
            <select class="form-select" name="empwomen" id="empwomen" required>
                <option value="">Select</option>
                <option value="Yes" @selected(old('empwomen') === 'Yes')>Yes</option>
                <option value="No" @selected(old('empwomen') === 'No')>No</option>
            </select>
        </div>

        <div class="form-group full-width" data-cfa-required-group="expectations">
            <label class="form-label">Expectations from Mukhyamantri Udyamshala / RBI <span style="color:red">*</span></label>
            <div class="checkbox-group">
                @foreach (config('cfa.expectation_values') as $ex)
                    <label>
                        <input type="checkbox" name="expectations[]" value="{{ $ex }}"
                            @if ($ex === 'Other') id="expectation_other_checkbox" onchange="window.toggleExpectationOther && window.toggleExpectationOther()" @endif
                            @checked(in_array($ex, $oldExpectations, true))>
                        {{ $expectationLabels[$ex] ?? $ex }}
                    </label>
                @endforeach
            </div>
        </div>

        <div id="expectation_other_input" class="form-group full-width {{ in_array('Other', $oldExpectations, true) ? '' : 'hidden' }}">
            <label class="form-label" for="expectation_other_text">If Other, specify</label>
            <input type="text" name="expectation_other_text" id="expectation_other_text" class="form-control" value="{{ old('expectation_other_text') }}" maxlength="500">
        </div>

        <div id="departmentNameDiv" class="form-group full-width" style="display: none;">
            <label class="form-label" for="department_name">Department name</label>
            <select class="form-select" name="department_name" id="department_name">
                <option value="">Select</option>
                @foreach (config('cfa.department_names') as $dn)
                    <option value="{{ $dn }}" @selected(old('department_name') === $dn)>{{ $dn }}</option>
                @endforeach
            </select>
        </div>

    </div>

    <div class="consent-box">
        <label>
            <input type="checkbox" name="consent" id="consent" value="1" @checked(old('consent')) required>
            <span>
                I hereby declare that all information provided is true and correct. I consent to processing of my personal data for this application. /
                मैं घोषणा करता/करती हूँ कि दी गई जानकारी सत्य है तथा व्यक्तिगत डेटा के उपयोग की सहमति देता/देती हूँ।
                <span style="color:red;font-weight:700">*</span>
            </span>
        </label>
    </div>

    <div class="form-actions">
        <button type="button" class="btn-back" id="backToA">← Back</button>
        <button type="submit" class="btn-success" id="cfaSubmitBtn" disabled>Submit application</button>
    </div>
</div>
