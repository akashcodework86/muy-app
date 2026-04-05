{{-- Section A — legacy RBI index.php (district fixed to referral staff) --}}
<div id="sectionA" class="form-section">
    <h2 class="section-header">Section A — Applicant Details</h2>
    <div class="form-grid">
        <div>
            <label class="form-label" for="category">Applicant Category / आवेदक की श्रेणी <span style="color:red">*</span></label>
            <select class="form-select" name="category" id="category" required>
                <option value="">Choose / चुनें</option>
                <option value="Individual" @selected(old('category') === 'Individual')>Individual / व्यक्तिगत</option>
                <option value="SHG" @selected(old('category') === 'SHG')>SHG / एस.एच.जी</option>
                <option value="CBO" @selected(old('category') === 'CBO')>CBO / सी.बी.ओ</option>
            </select>
        </div>

        <div id="fullNameDiv">
            <label class="form-label" for="fullname">Full Name / आवेदक का पूरा नाम <span style="color:red">*</span></label>
            <input type="text" class="form-control" name="applicant_name" id="fullname" value="{{ old('applicant_name') }}" maxlength="191">
            <p id="fullname-error" class="text-red-500 hidden">Enter a valid name (letters, spaces, . ' - only)</p>
        </div>

        <div id="shgCboNameDiv" style="display: none;">
            <label class="form-label" for="shg_cbo_name">SHG/CBO Name / एस.एच.जी / सी.बी.ओ <span style="color:red">*</span></label>
            <input type="text" class="form-control" name="shg_cbo_name" id="shg_cbo_name" value="{{ old('shg_cbo_name') }}" maxlength="191">
        </div>

        <div id="guardianDiv">
            <label class="form-label" for="guardian_name">Father/Husband Name / पिता/पति का नाम <span style="color:red">*</span></label>
            <input type="text" class="form-control" name="guardian_name" id="guardian_name" value="{{ old('guardian_name') }}" maxlength="191">
            <p id="guardian_name-error" class="text-red-500 hidden">Enter a valid name (letters, spaces, . ' - only)</p>
        </div>

        <div>
            <label class="form-label" for="gender">Gender / लिंग <span style="color:red">*</span></label>
            <select class="form-select" name="gender" id="gender" required>
                <option value="">Select Gender</option>
                @foreach (config('cfa.genders') as $g)
                    <option value="{{ $g }}" @selected(old('gender') === $g)>{{ $g }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label id="dobLabel" class="form-label" for="dob">Date of Birth / Formation Date / जन्म तिथि <span style="color:red">*</span></label>
            <input type="date"
                class="form-control"
                name="dob"
                id="dob"
                value="{{ old('dob') }}"
                required
                max="{{ now()->subYears(17)->toDateString() }}"
                min="{{ now()->subYears(120)->toDateString() }}"
                data-max-individual="{{ now()->subYears(17)->toDateString() }}"
                data-min-individual="{{ now()->subYears(120)->toDateString() }}"
                data-max-formation="{{ now()->toDateString() }}"
                data-min-formation="{{ now()->subYears(100)->toDateString() }}"
                title="Individual: at least 17 years old. Calendar only allows valid years.">
            <p id="dob-hint" class="text-gray-700" style="font-size:0.8rem;margin-top:0.35rem;">Individual: minimum age <strong>17</strong> — latest allowed date is {{ now()->subYears(17)->format('d M Y') }}. / न्यूनतम आयु <strong>17</strong> वर्ष।</p>
            <p id="dob-error" class="text-red-500 hidden">Date cannot be in the future.</p>
        </div>

        <div id="casteDiv">
            <label class="form-label" for="caste">Social Category / सामाजिक श्रेणी <span style="color:red">*</span></label>
            <select id="caste" class="form-select" name="caste">
                <option value="">Select Category / श्रेणी चुनें</option>
                <option value="GEN" @selected(old('caste') === 'GEN')>General / सामान्य</option>
                <option value="EWS" @selected(old('caste') === 'EWS')>EWS / आर्थिक रूप से कमजोर वर्ग</option>
                <option value="OBC" @selected(old('caste') === 'OBC')>OBC / ओ.बी.सी</option>
                <option value="SC" @selected(old('caste') === 'SC')>SC / एस.सी</option>
                <option value="ST" @selected(old('caste') === 'ST')>ST / एस.टी</option>
                <option value="OTH" @selected(old('caste') === 'OTH')>Other / अन्य</option>
            </select>
        </div>

        <div>
            <label class="form-label" for="mobile">Mobile / WhatsApp / मोबाइल नंबर <span style="color:red">*</span></label>
            <input type="tel" class="form-control" name="phone" id="mobile" value="{{ old('phone') }}" maxlength="10" inputmode="numeric" required autocomplete="tel">
            <p id="mobile-error" class="text-red-500 hidden">Valid 10-digit mobile starting with 6–9.</p>
            <p id="mobile-duplicate-error" class="text-red-500 hidden" style="font-size:0.85rem;margin-top:0.35rem;"></p>
        </div>

        <div>
            <label class="form-label" for="alt_mobile">Alternate number</label>
            <input type="tel" class="form-control" name="alt_mobile" id="alt_mobile" value="{{ old('alt_mobile') }}" maxlength="10" inputmode="numeric">
            <p id="alt-mobile-error" class="text-red-500 hidden">Valid 10-digit number starting with 6–9.</p>
        </div>

        <div>
            <label class="form-label" for="email">Email / ईमेल</label>
            <input type="email" class="form-control" name="email" id="email" value="{{ old('email') }}" maxlength="191">
            <p id="email-error" class="text-red-500 hidden">Please enter a valid email.</p>
        </div>

        <div>
            <label class="form-label" for="village">Address (Village) / गांव</label>
            <input type="text" class="form-control" name="village" id="village" value="{{ old('village') }}" maxlength="191">
        </div>

        <div>
            <label class="form-label">District / जनपद <span style="color:red">*</span></label>
            <input type="text" class="form-control" value="{{ $districtName }}" disabled>
            <input type="hidden" name="district" value="{{ $districtName }}">
        </div>

        <div>
            <label class="form-label" for="block">Block / विकास खंड <span style="color:red">*</span></label>
            <select class="form-select" name="block" id="block" required>
                <option value="">Select Block</option>
                @foreach ($blocks as $b)
                    <option value="{{ $b }}" @selected(old('block') === $b)>{{ $b }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label" for="pincode">Pin Code / पिनकोड <span style="color:red">*</span></label>
            <input type="text" class="form-control" name="pincode" id="pincode" value="{{ old('pincode') }}" maxlength="6" inputmode="numeric" required>
            <p id="pincode-error" class="text-red-500 hidden">Valid 6-digit PIN starting with 1–9.</p>
        </div>

        <div>
            <label class="form-label" for="education">Education / शैक्षणिक योग्यता <span style="color:red">*</span></label>
            <select class="form-select" name="education" id="education" required data-old-education="{{ old('education') }}">
                <option value="">Select</option>
                @foreach (config('cfa.education_individual') as $e)
                    <option value="{{ $e }}" @selected(old('education') === $e)>{{ $e }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label" for="id_proof_type">ID Proof Type</label>
            <select class="form-select" name="id_proof_type" id="id_proof_type">
                <option value="">Select</option>
                @foreach (array_filter(config('cfa.id_proof_types')) as $ip)
                    <option value="{{ $ip }}" @selected(old('id_proof_type') === $ip)>{{ $ip }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label" for="id_proof_number">ID Proof Number</label>
            <input type="text" class="form-control" name="id_proof_number" id="id_proof_number" value="{{ old('id_proof_number') }}" maxlength="120">
        </div>

        <div id="memberQuestionDiv">
            <label class="form-label" for="is_member">Member of SHG/CBO?</label>
            <select class="form-select" name="is_member" id="is_member">
                <option value="No" @selected(old('is_member', 'No') === 'No')>No</option>
                <option value="Yes" @selected(old('is_member') === 'Yes')>Yes</option>
            </select>
        </div>

        <div id="shgNameDiv" style="display: none;">
            <label class="form-label" for="shg_name">SHG/CBO Name (if Yes) / नाम <span id="shgNameStar" style="color:red;display:none" aria-hidden="true">*</span></label>
            <input type="text" class="form-control" name="shg_name" id="shg_name" value="{{ old('shg_name') }}" maxlength="191" autocomplete="organization">
            <p id="shg_name-error" class="text-red-500 hidden">Enter the SHG/CBO name. / एस.एच.जी / सी.बी.ओ का नाम भरें।</p>
        </div>

        <div id="lakhpatiDiv" style="display: none;">
            <label class="form-label" for="lakhpati">Lakhpati Didi? <span id="lakhpatiStar" style="color:red;display:none" aria-hidden="true">*</span></label>
            <select class="form-select" name="lakhpati" id="lakhpati">
                <option value="No" @selected(old('lakhpati', 'No') === 'No')>No</option>
                <option value="Yes" @selected(old('lakhpati') === 'Yes')>Yes</option>
            </select>
        </div>

        <div>
            <label class="form-label" for="enterprise_registered">Enterprise Registered? / उद्यम पंजीकृत? <span style="color:red">*</span></label>
            <select class="form-select" name="is_registered" id="enterprise_registered" required>
                <option value="">Choose</option>
                <option value="Yes" @selected(old('is_registered') === 'Yes')>Yes</option>
                <option value="No" @selected(old('is_registered') === 'No')>No</option>
            </select>
        </div>

        <div id="registrationDetailsDiv" style="display: none;" class="form-group full-width">
            <div class="form-grid" style="grid-column:1/-1;">
                <div>
                    <label class="form-label" for="registration_type">Type of registration / Licence</label>
                    <select class="form-select" name="registration_type" id="registration_type">
                        <option value="">Select / चुनें</option>
                        @foreach (config('cfa.registration_types') as $rt)
                            <option value="{{ $rt }}" @selected(old('registration_type') === $rt)>{{ $rt }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="registration_type_other_wrap" style="display: none;">
                    <label class="form-label" for="registration_type_other">If Other, specify</label>
                    <input type="text" class="form-control" name="registration_type_other" id="registration_type_other" value="{{ old('registration_type_other') }}" maxlength="191">
                </div>
                <div>
                    <label class="form-label" for="registration_number">Registration number</label>
                    <input type="text" class="form-control" name="registration_number" id="registration_number" value="{{ old('registration_number') }}" maxlength="191" autocomplete="off">
                </div>
                <div>
                    <label class="form-label" for="registration_date">Registration date (optional)</label>
                    <input type="date" class="form-control" name="registration_date" id="registration_date" value="{{ old('registration_date') }}" max="{{ now()->toDateString() }}">
                </div>
            </div>
        </div>

        <div>
            <label class="form-label" for="training_received">Entrepreneurship / self-employment training received? <span style="color:red">*</span></label>
            <select name="training_received" id="training_received" class="form-select" required>
                <option value="">Select</option>
                <option value="Yes" @selected(old('training_received') === 'Yes')>Yes</option>
                <option value="No" @selected(old('training_received') === 'No')>No</option>
            </select>
        </div>

        <div class="form-group full-width" id="training_institute_div" style="display:none;">
            <label class="form-label" for="training_institute">Name of the Institute</label>
            <input type="text" name="training_institute" id="training_institute" class="form-control" value="{{ old('training_institute') }}" maxlength="191" placeholder="Institute name">
        </div>

        <div>
            <label class="form-label" for="turnover_last_fy">Turnover of last financial year <span style="color:red">*</span></label>
            <input type="text" class="form-control" name="turnover_last_fy" id="turnover_last_fy" value="{{ old('turnover_last_fy') }}" required>
            <p id="turnover-error" class="text-red-500 hidden">Valid amount (numbers only)</p>
            <p id="turnover-preview" class="text-gray-700"></p>
        </div>

        <div>
            <label class="form-label" for="current_employment">Currently generating employment? <span style="color:red">*</span></label>
            <select class="form-select" name="current_employment" id="current_employment" required>
                <option value="">Choose</option>
                <option value="Yes" @selected(old('current_employment') === 'Yes')>Yes</option>
                <option value="No" @selected(old('current_employment') === 'No')>No</option>
            </select>
        </div>

        <div id="employmentCountDiv" style="display: none;">
            <label class="form-label" for="employed_count">How many people employed?</label>
            <input type="number" min="1" class="form-control" name="employed_count" id="employed_count" value="{{ old('employed_count') }}">
            <p id="employment-count-error" class="text-red-500 hidden">Enter a number greater than 0.</p>
        </div>

        <div>
            <label class="form-label" for="business_age">Age of Business <span style="color:red">*</span></label>
            <select class="form-select" name="business_age" id="business_age" required>
                <option value="">Choose</option>
                @foreach (config('cfa.business_age') as $ba)
                    <option value="{{ $ba }}" @selected(old('business_age') === $ba)>{{ $ba }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label" for="loan_taken">Bank loan applied/taken? <span style="color:red">*</span></label>
            <select class="form-select" name="loan_taken" id="loan_taken" required>
                <option value="">Choose</option>
                <option value="Yes" @selected(old('loan_taken') === 'Yes')>Yes</option>
                <option value="No" @selected(old('loan_taken') === 'No')>No</option>
            </select>
        </div>

        <div id="bankLoanAmountDiv" style="display: none;">
            <label class="form-label" for="bank_loan">Bank loan amount</label>
            <input type="text" class="form-control" name="bank_loan" id="bank_loan" value="{{ old('bank_loan') }}">
            <p id="bank-loan-error" class="text-red-500 hidden">Valid amount</p>
            <p id="bank-loan-preview" class="text-gray-700"></p>
        </div>

        <div>
            <label class="form-label" for="regular_buyer">Marketing partners associated?</label>
            <select class="form-select" name="regular_buyer" id="regular_buyer">
                <option value="">Choose</option>
                <option value="Yes" @selected(old('regular_buyer') === 'Yes')>Yes</option>
                <option value="No" @selected(old('regular_buyer') === 'No')>No</option>
            </select>
        </div>

        <div id="buyerCountDiv" style="display: none;">
            <label class="form-label" for="buyer_count">How many partners?</label>
            <input type="number" min="1" class="form-control" name="buyer_count" id="buyer_count" value="{{ old('buyer_count') }}">
            <p id="buyer-count-error" class="text-red-500 hidden">Enter a number greater than 0.</p>
        </div>

        <input type="hidden" name="detected_stage" id="detected_stage" value="">
    </div>

    <div class="form-actions">
        <button type="button" id="proceedButton" class="btn-primary" disabled>Next →</button>
    </div>
</div>
