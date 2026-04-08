/**
 * MUY CFA public form — behaviour aligned with legacy rbi-new/index.php
 */
(function () {
    const $ = (id) => document.getElementById(id);

    let phoneDuplicateTaken = false;
    let phoneCheckLoading = false;
    let phoneCheckTimer = null;
    const MOBILE_OK = /^[6-9]\d{9}$/;

    function lockGenderToFemale() {
        const gender = $('gender');
        if (!gender) return;
        gender.value = 'Female';
        gender.dataset.cfaLocked = '1';
        // Don't disable: disabled fields are not submitted.
        gender.style.pointerEvents = 'none';
        gender.classList.add('cfa-select-locked');
        gender.setAttribute('aria-readonly', 'true');
        gender.setAttribute('tabindex', '-1');
    }

    function unlockGender() {
        const gender = $('gender');
        if (!gender) return;
        delete gender.dataset.cfaLocked;
        gender.style.pointerEvents = '';
        gender.classList.remove('cfa-select-locked');
        gender.removeAttribute('aria-readonly');
        gender.removeAttribute('tabindex');
    }

    function hideMobileDuplicateError() {
        const e = $('mobile-duplicate-error');
        if (e) {
            e.classList.add('hidden');
            e.style.display = '';
        }
        const input = $('mobile');
        if (input) {
            input.style.borderColor = '';
            input.style.boxShadow = '';
        }
    }

    function showMobileDuplicateError(msg) {
        const e = $('mobile-duplicate-error');
        if (e) {
            const textEl = $('mobile-duplicate-error-text');
            if (textEl) textEl.textContent = msg || '';
            e.classList.remove('hidden');
            e.style.display = 'flex';
        }
        // Highlight the mobile input with red border
        const input = $('mobile');
        if (input) {
            input.style.borderColor = '#ef4444';
            input.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.18)';
        }
    }

    async function runPhoneAvailabilityCheck() {
        const url = typeof window.CFA_CHECK_PHONE_URL === 'string' ? window.CFA_CHECK_PHONE_URL : '';
        const mobileEl = $('mobile');
        if (!url || !mobileEl) {
            phoneCheckLoading = false;
            checkSectionAComplete();
            return;
        }
        const p = mobileEl.value.trim();
        if (!MOBILE_OK.test(p)) {
            phoneDuplicateTaken = false;
            phoneCheckLoading = false;
            hideMobileDuplicateError();
            checkSectionAComplete();
            return;
        }

        phoneCheckLoading = true;
        checkSectionAComplete();

        const form = $('cfaMainForm');
        const tokenInput = form && form.querySelector('input[name="_token"]');
        const csrf = tokenInput ? tokenInput.value : '';

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ phone: p }),
            });
            const data = await res.json().catch(() => ({}));
            phoneCheckLoading = false;

            const stillSame = mobileEl.value.trim() === p;
            if (!stillSame) {
                schedulePhoneCheck();
                return;
            }

            if (!res.ok || data.ok === false) {
                phoneDuplicateTaken = false;
                hideMobileDuplicateError();
                checkSectionAComplete();
                return;
            }

            if (data.available === false) {
                phoneDuplicateTaken = true;
                showMobileDuplicateError(data.message || 'This number is already registered.');
            } else {
                phoneDuplicateTaken = false;
                hideMobileDuplicateError();
            }
        } catch (err) {
            phoneCheckLoading = false;
            phoneDuplicateTaken = false;
            hideMobileDuplicateError();
        }
        checkSectionAComplete();
        updateCounter();
        updateGuide();
    }

    function schedulePhoneCheck() {
        if (phoneCheckTimer) clearTimeout(phoneCheckTimer);
        const mobileEl = $('mobile');
        const p = (mobileEl && mobileEl.value.trim()) || '';

        if (p.length !== 10 || !MOBILE_OK.test(p)) {
            phoneDuplicateTaken = false;
            phoneCheckLoading = false;
            hideMobileDuplicateError();
            checkSectionAComplete();
            updateCounter();
            updateGuide();
            return;
        }

        phoneCheckTimer = setTimeout(function () {
            phoneCheckTimer = null;
            runPhoneAvailabilityCheck();
        }, 450);
    }

    function formatIndianNumber(x) {
        x = String(x);
        let afterPoint = '';
        if (x.indexOf('.') > 0) afterPoint = x.substring(x.indexOf('.'));
        x = Math.floor(parseFloat(x) || 0).toString();
        let lastThree = x.substring(x.length - 3);
        let otherNumbers = x.substring(0, x.length - 3);
        if (otherNumbers !== '') lastThree = ',' + lastThree;
        return otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + lastThree + afterPoint;
    }

    function convertToWords(num) {
        const a = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        const b = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        if (num === 0) return 'zero';
        function numToWords(n) {
            if (n < 20) return a[n];
            if (n < 100) return b[Math.floor(n / 10)] + (n % 10 ? '-' + a[n % 10] : '');
            if (n < 1000) return a[Math.floor(n / 100)] + ' hundred' + (n % 100 ? ' and ' + numToWords(n % 100) : '');
            if (n < 100000) return numToWords(Math.floor(n / 1000)) + ' thousand ' + numToWords(n % 1000);
            if (n < 10000000) return numToWords(Math.floor(n / 100000)) + ' lakh ' + numToWords(n % 100000);
            return numToWords(Math.floor(n / 10000000)) + ' crore ' + numToWords(n % 10000000);
        }
        return numToWords(num).replace(/\s+/g, ' ').trim();
    }

    function validateNameInput(input) {
        if (!input || !input.id) return;
        const value = input.value.trim();
        const pattern = /^[A-Za-z.\-'\s]+$/;
        const err = $(input.id + '-error');
        if (!err) return;
        if (value && !pattern.test(value)) err.classList.remove('hidden');
        else err.classList.add('hidden');
    }

    function restrictMobile(input) {
        if (!input) return;
        let v = input.value.replace(/[^0-9]/g, '');
        if (v.length > 10) v = v.substring(0, 10);
        input.value = v;
    }

    function validateMobileEl(id, errId) {
        const mobile = ($(id) && $(id).value.trim()) || '';
        const err = $(errId);
        const pattern = /^[6-9]\d{9}$/;
        if (!err) return pattern.test(mobile);
        if (mobile && !pattern.test(mobile)) {
            err.classList.remove('hidden');
            return false;
        }
        err.classList.add('hidden');
        return true;
    }

    function validatePincode() {
        const pincode = ($('pincode') && $('pincode').value.trim()) || '';
        const err = $('pincode-error');
        const pattern = /^[1-9]\d{5}$/;
        if (!err) return;
        if (!pattern.test(pincode)) err.classList.remove('hidden');
        else err.classList.add('hidden');
    }

    function validateEmail() {
        const email = ($('email') && $('email').value.trim()) || '';
        const err = $('email-error');
        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!err) return;
        if (email && !pattern.test(email)) err.classList.remove('hidden');
        else err.classList.add('hidden');
    }

    function validateAmountInput(inputId, errId, previewId) {
        const input = $(inputId);
        if (!input) return;
        const raw = input.value.replace(/,/g, '');
        const err = $(errId);
        const preview = $(previewId);
        const isValid = /^(\d+)(\.\d{1,2})?$/.test(raw);
        if (!isValid && raw !== '') {
            if (err) err.classList.remove('hidden');
            if (preview) preview.textContent = '';
            return;
        }
        if (err) err.classList.add('hidden');
        input.value = raw === '' ? '' : formatIndianNumber(raw);
        if (preview) {
            if (raw !== '' && isFinite(parseInt(raw, 10))) {
                preview.textContent = 'You entered: ₹ ' + convertToWords(parseInt(raw, 10));
            } else preview.textContent = '';
        }
    }

    function applyDobLimits() {
        const dob = $('dob');
        if (!dob) return;
        const cat = $('category') && $('category').value;
        const maxInd = dob.getAttribute('data-max-individual');
        const minInd = dob.getAttribute('data-min-individual');
        const maxOrg = dob.getAttribute('data-max-formation');
        const minOrg = dob.getAttribute('data-min-formation');
        if (cat === 'SHG' || cat === 'CBO') {
            if (maxOrg) dob.setAttribute('max', maxOrg);
            if (minOrg) dob.setAttribute('min', minOrg);
        } else {
            if (maxInd) dob.setAttribute('max', maxInd);
            if (minInd) dob.setAttribute('min', minInd);
        }
        validateDobAge();
    }

    function validateDobAge() {
        const dob = $('dob');
        const err = $('dob-error');
        if (!dob || !err) return true;
        const v = dob.value;
        const cat = $('category') && $('category').value;
        err.classList.add('hidden');
        if (!v) return true;
        const today = new Date().toISOString().split('T')[0];
        const maxOrg = dob.getAttribute('data-max-formation');
        const minOrg = dob.getAttribute('data-min-formation');
        const maxInd = dob.getAttribute('data-max-individual');
        const minInd = dob.getAttribute('data-min-individual');

        if (v > today) {
            err.textContent = 'Date cannot be in the future. / भविष्य की तिथि नहीं।';
            err.classList.remove('hidden');
            return false;
        }

        if (cat === 'SHG' || cat === 'CBO') {
            if (maxOrg && v > maxOrg) {
                err.textContent = 'Formation date cannot be in the future.';
                err.classList.remove('hidden');
                return false;
            }
            if (minOrg && v < minOrg) {
                err.textContent = 'Please choose a formation date within the allowed range.';
                err.classList.remove('hidden');
                return false;
            }
            return true;
        }

        if (maxInd && v > maxInd) {
            err.textContent = 'You must be at least 17 years old. / आयु कम से कम 17 वर्ष होनी चाहिए।';
            err.classList.remove('hidden');
            return false;
        }
        if (minInd && v < minInd) {
            err.textContent = 'Please check date of birth. / कृपया जन्म तिथि जाँचें।';
            err.classList.remove('hidden');
            return false;
        }
        return true;
    }

    function toggleCategoryFields() {
        applyDobLimits();
        const category = $('category') && $('category').value;
        const fullNameDiv = $('fullNameDiv');
        const shgDiv = $('shgCboNameDiv');
        const guardianDiv = $('guardianDiv');
        const casteDiv = $('casteDiv');
        const memberDiv = $('memberQuestionDiv');
        const fullNameInput = $('fullname');
        const guardianInput = $('guardian_name');
        const shgInput = $('shg_cbo_name');
        const casteSelect = $('caste');
        const genderSelect = $('gender');
        const educationSelect = $('education');
        const dobLabel = $('dobLabel');

        if (!category) return;

        if (category === 'SHG' || category === 'CBO') {
            if (fullNameDiv) fullNameDiv.style.display = 'none';
            if (fullNameInput) {
                fullNameInput.removeAttribute('required');
                fullNameInput.disabled = true;
                fullNameInput.value = '';
            }
            if (guardianDiv) guardianDiv.style.display = 'none';
            if (guardianInput) {
                guardianInput.removeAttribute('required');
                guardianInput.disabled = true;
                guardianInput.value = '';
            }
            if (shgDiv) shgDiv.style.display = 'block';
            if (shgInput) {
                shgInput.required = true;
                shgInput.disabled = false;
            }
            if (casteDiv) casteDiv.style.display = 'none';
            if (casteSelect) {
                casteSelect.removeAttribute('required');
                casteSelect.value = '';
            }
            if (genderSelect) {
                // SHG/CBO: force Gender = Female (unchangeable, but still submitted)
                genderSelect.disabled = false;
                if (category === 'SHG') {
                    lockGenderToFemale();
                } else if (category === 'CBO') {
                    lockGenderToFemale();
                }
            }
            if (educationSelect) {
                educationSelect.innerHTML = '<option value="NA" selected>NA</option>';
            }
            if (dobLabel) dobLabel.innerHTML = 'Formation date <span style="color:red">*</span>';
            const dh = $('dob-hint');
            if (dh) dh.style.display = 'none';
        } else {
            if (fullNameDiv) fullNameDiv.style.display = 'block';
            if (fullNameInput) {
                fullNameInput.required = true;
                fullNameInput.disabled = false;
            }
            if (guardianDiv) guardianDiv.style.display = 'block';
            if (guardianInput) {
                guardianInput.required = true;
                guardianInput.disabled = false;
            }
            if (shgDiv) shgDiv.style.display = 'none';
            if (shgInput) {
                shgInput.removeAttribute('required');
                shgInput.disabled = true;
                shgInput.value = '';
            }
            if (casteDiv) casteDiv.style.display = 'block';
            if (casteSelect) casteSelect.required = true;
            if (genderSelect) {
                unlockGender();
                genderSelect.disabled = false;
            }
            if (educationSelect) {
                const opts = ['Below 8th', '8th pass', '10th pass', '12th pass', 'ITI', 'Diploma', 'Certificate', 'Under Graduate', 'Post Graduate', 'NA'];
                const oldEdu = educationSelect.getAttribute('data-old-education') || educationSelect.value;
                educationSelect.innerHTML = '<option value="">Select</option>' + opts.map((o) => '<option value="' + o + '">' + o + '</option>').join('');
                if (oldEdu) educationSelect.value = oldEdu;
            }
            if (dobLabel) dobLabel.innerHTML = 'Date of birth / जन्म तिथि <span style="color:red">*</span>';
            const dh = $('dob-hint');
            if (dh) dh.style.display = 'block';
        }
        if (memberDiv) memberDiv.style.display = category === 'Individual' ? 'block' : 'none';

        if (category === 'Individual') {
            toggleShgMember();
        } else {
            const snd = $('shgNameDiv');
            const lkd = $('lakhpatiDiv');
            const sn = $('shg_name');
            const lp = $('lakhpati');
            if (snd) snd.style.display = 'none';
            if (lkd) lkd.style.display = 'none';
            if (sn) {
                sn.removeAttribute('required');
                sn.value = '';
                const se = $('shg_name-error');
                if (se) se.classList.add('hidden');
            }
            if (lp) {
                lp.removeAttribute('required');
                lp.value = 'No';
            }
            const star = $('shgNameStar');
            const lStar = $('lakhpatiStar');
            if (star) star.style.display = 'none';
            if (lStar) lStar.style.display = 'none';
        }

        checkSectionAComplete();
        updateCounter();
        updateGuide();
        updateProfile();
    }

    function toggleShgMember() {
        const isMember = $('is_member') && $('is_member').value;
        const show = isMember === 'Yes';
        const shgNameDiv = $('shgNameDiv');
        const lakhpatiDiv = $('lakhpatiDiv');
        const shgInput = $('shg_name');
        const lakhpatiSel = $('lakhpati');
        const star = $('shgNameStar');
        const lStar = $('lakhpatiStar');
        if (shgNameDiv) shgNameDiv.style.display = show ? 'block' : 'none';
        if (lakhpatiDiv) lakhpatiDiv.style.display = show ? 'block' : 'none';
        if (shgInput) {
            if (show) {
                shgInput.required = true;
                shgInput.disabled = false;
            } else {
                shgInput.removeAttribute('required');
                shgInput.value = '';
                const se = $('shg_name-error');
                if (se) se.classList.add('hidden');
            }
        }
        if (lakhpatiSel) {
            if (show) lakhpatiSel.required = true;
            else {
                lakhpatiSel.removeAttribute('required');
                lakhpatiSel.value = 'No';
            }
        }
        if (star) star.style.display = show ? 'inline' : 'none';
        if (lStar) lStar.style.display = show ? 'inline' : 'none';
        validateShgNameIfNeeded();
        checkSectionAComplete();
        updateCounter();
        updateGuide();
        updateProfile();
    }

    function validateShgNameIfNeeded() {
        const show = $('is_member') && $('is_member').value === 'Yes';
        const shgInput = $('shg_name');
        const err = $('shg_name-error');
        if (!shgInput || !err) return;
        if (!show) {
            err.classList.add('hidden');
            return;
        }
        if (!String(shgInput.value || '').trim()) err.classList.remove('hidden');
        else err.classList.add('hidden');
    }

    function toggleTrainingInstitute() {
        const v = $('training_received') && $('training_received').value;
        const div = $('training_institute_div');
        if (div) div.style.display = v === 'Yes' ? 'block' : 'none';
    }

    function toggleEmploymentCount() {
        const v = $('current_employment') && $('current_employment').value;
        const div = $('employmentCountDiv');
        if (div) div.style.display = v === 'Yes' ? 'block' : 'none';
        if (v !== 'Yes' && $('employed_count')) $('employed_count').value = '';
    }

    function toggleBankLoan() {
        const v = $('loan_taken') && $('loan_taken').value;
        const div = $('bankLoanAmountDiv');
        if (div) div.style.display = v === 'Yes' ? 'block' : 'none';
        if (v !== 'Yes' && $('bank_loan')) {
            $('bank_loan').value = '';
            const e = $('bank-loan-error');
            if (e) e.classList.add('hidden');
            const p = $('bank-loan-preview');
            if (p) p.textContent = '';
        }
    }

    function toggleBuyerCount() {
        const v = $('regular_buyer') && $('regular_buyer').value;
        const div = $('buyerCountDiv');
        if (div) div.style.display = v === 'Yes' ? 'block' : 'none';
        if (v !== 'Yes' && $('buyer_count')) $('buyer_count').value = '';
    }

    function syncRegistrationOther() {
        const typeEl = $('registration_type');
        const otherWrap = $('registration_type_other_wrap');
        const otherEl = $('registration_type_other');
        const regYes = $('enterprise_registered') && $('enterprise_registered').value === 'Yes';
        if (!typeEl || !otherWrap || !otherEl) return;
        const isOther = typeEl.value === 'Other';
        otherWrap.style.display = isOther && regYes ? 'block' : 'none';
        if (isOther && regYes) otherEl.required = true;
        else {
            otherEl.removeAttribute('required');
            if (!isOther) otherEl.value = '';
        }
    }

    function toggleRegistrationDetails() {
        const sel = $('enterprise_registered');
        const show = sel && sel.value === 'Yes';
        const regDiv = $('registrationDetailsDiv');
        const typeEl = $('registration_type');
        const numEl = $('registration_number');
        if (!regDiv || !typeEl || !numEl) return;
        regDiv.style.display = show ? 'block' : 'none';
        if (show) {
            typeEl.required = true;
            numEl.required = true;
        } else {
            typeEl.removeAttribute('required');
            numEl.removeAttribute('required');
            typeEl.value = '';
            numEl.value = '';
            const dateEl = $('registration_date');
            if (dateEl) dateEl.value = '';
            const otherEl = $('registration_type_other');
            if (otherEl) otherEl.value = '';
            const ow = $('registration_type_other_wrap');
            if (ow) ow.style.display = 'none';
        }
        syncRegistrationOther();
        checkSectionAComplete();
        updateCounter();
        updateGuide();
    }

    function checkSectionAComplete() {
        const requiredFields = document.querySelectorAll('#sectionA [required]:not([disabled])');
        let allFilled = true;
        requiredFields.forEach((field) => {
            if (field.offsetParent === null) return;
            if (!String(field.value || '').trim()) allFilled = false;
        });
        const mval = ($('mobile') && $('mobile').value.trim()) || '';
        if (MOBILE_OK.test(mval) && phoneCheckLoading) allFilled = false;
        if (MOBILE_OK.test(mval) && phoneDuplicateTaken) allFilled = false;
        const btn = $('proceedButton');
        if (btn) btn.disabled = !allFilled;
    }

    function checkSectionBComplete() {
        const requiredFields = document.querySelectorAll('#sectionB [required]:not([disabled])');
        let allFilled = true;
        requiredFields.forEach((field) => {
            if (field.offsetParent === null) return;
            if (!String(field.value || '').trim()) allFilled = false;
        });
        const challenges = document.querySelectorAll('input[name="challenges[]"]');
        const expectations = document.querySelectorAll('input[name="expectations[]"]');
        if (!Array.from(challenges).some((c) => c.checked)) allFilled = false;
        if (!Array.from(expectations).some((c) => c.checked)) allFilled = false;
        const consent = $('consent');
        if (!consent || !consent.checked) allFilled = false;
        const submitBtn = $('cfaSubmitBtn');
        if (submitBtn) submitBtn.disabled = !allFilled;
    }

    function calculateStage() {
        const reg = ($('enterprise_registered') && $('enterprise_registered').value) || 'No';
        let rawTurnover = ($('turnover_last_fy') && $('turnover_last_fy').value) || '0';
        rawTurnover = rawTurnover.replace(/,/g, '');
        const turnover = parseFloat(rawTurnover) || 0;
        let stage = 'Seed';
        const logic = ['How your stage was determined:', '- Enterprise registered: ' + reg, '- Turnover in last FY: ₹' + turnover.toLocaleString('en-IN')];
        if (reg === 'No' && turnover === 0) {
            stage = 'Seed';
            logic.push('Enterprise is not registered and turnover is zero.');
        } else if (reg === 'No' && turnover > 0) {
            stage = 'Early';
            logic.push('Enterprise is not registered, but turnover has started (> 0).');
        } else if (reg === 'Yes' && turnover > 0 && turnover <= 500000) {
            stage = 'Early';
            logic.push('Enterprise is registered and turnover is between 1 and 5 lakh.');
        } else if (reg === 'Yes' && turnover > 500000) {
            stage = 'Growth';
            logic.push('Enterprise is registered and turnover is above 5 lakh.');
        } else {
            logic.push('No matching rule found. Keeping stage as Seed.');
        }
        logic.push('Final stage: ' + stage);
        const stageResult = $('stageResult');
        const detected = $('detected_stage');
        if (stageResult) stageResult.value = stage;
        if (detected) detected.value = stage;
        const sl = $('stageLogic');
        if (sl) sl.innerText = logic.join('\n');
        $('sectionA').style.display = 'none';
        $('sectionB').style.display = 'block';
        $('progressStep1').classList.remove('active');
        $('progressStep1').classList.add('done');
        $('progressStep2').classList.add('active');
        $('progressFill').style.width = '100%';
        const modal = $('stageLogicModal');
        if (modal) modal.style.display = 'flex';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
            checkSectionBComplete();
            updateCounter();
            updateGuide();
        }, 0);
    }

    window.closeStageModal = function () {
        const modal = $('stageLogicModal');
        if (modal) modal.style.display = 'none';
    };

    function goBackToSectionA() {
        $('sectionB').style.display = 'none';
        $('sectionA').style.display = 'block';
        $('progressStep1').classList.add('active');
        $('progressStep2').classList.remove('active');
        $('progressFill').style.width = '50%';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        updateCounter();
        updateGuide();
        updateProfile();
    }

    function getVisibleFields(section) {
        const fields = [];
        section.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"])').forEach((el) => {
            if (el.offsetParent) fields.push(el);
        });
        section.querySelectorAll('select').forEach((el) => {
            if (el.offsetParent) fields.push(el);
        });
        section.querySelectorAll('textarea').forEach((el) => {
            if (el.offsetParent) fields.push(el);
        });
        return fields;
    }

    function isFieldFilled(el) {
        if (el.type === 'checkbox') return el.checked;
        return String(el.value || '').trim() !== '';
    }

    const HIGHLIGHT_CLASS = 'cfa-required-empty';

    function clearRequiredHighlights() {
        document.querySelectorAll('.' + HIGHLIGHT_CLASS).forEach((el) => el.classList.remove(HIGHLIGHT_CLASS));
    }

    function getFieldLabel(field) {
        if (!field) return 'Field';
        if (field.id === 'consent') return 'Consent / घोषणा व सहमति';
        let byFor = null;
        if (field.id) {
            try {
                byFor = document.querySelector('label[for="' + field.id.replace(/"/g, '\\"') + '"]');
            } catch (e) {
                byFor = null;
            }
        }
        if (byFor) {
            return byFor.innerText.replace(/\*/g, '').replace(/\s+/g, ' ').trim().split(' / ')[0].trim();
        }
        const wrap = field.closest('.form-group');
        if (wrap) {
            const fl = wrap.querySelector('.form-label');
            if (fl) return fl.innerText.replace(/\*/g, '').replace(/\s+/g, ' ').trim().split(' / ')[0].trim();
        }
        return field.name || 'Field';
    }

    function countRequiredSlots(active) {
        if (!active) return 0;
        let n = 0;
        active.querySelectorAll('[required]:not([disabled])').forEach((f) => {
            if (f.offsetParent === null) return;
            n++;
        });
        if (active.id === 'sectionB') {
            const ch = active.querySelector('input[name="challenges[]"]');
            if (ch && ch.offsetParent) n++;
            const ex = active.querySelector('input[name="expectations[]"]');
            if (ex && ex.offsetParent) n++;
        }
        return n;
    }

    function collectMissingRequired() {
        const items = [];
        const sectionA = $('sectionA');
        const sectionB = $('sectionB');
        const active = sectionA && sectionA.offsetParent ? sectionA : sectionB;
        if (!active) return items;

        active.querySelectorAll('[required]:not([disabled])').forEach((field) => {
            if (field.offsetParent === null) return;
            if (field.type === 'checkbox') {
                if (!field.checked) items.push({ label: getFieldLabel(field), field });
                return;
            }
            if (!String(field.value || '').trim()) items.push({ label: getFieldLabel(field), field });
        });

        if (active.id === 'sectionB') {
            const ch = active.querySelectorAll('input[name="challenges[]"]');
            if (ch.length && ch[0].offsetParent && !Array.from(ch).some((c) => c.checked)) {
                const wrap = active.querySelector('[data-cfa-required-group="challenges"]');
                items.push({ label: 'Challenges — select at least one / चुनौतियाँ', field: ch[0], scrollEl: wrap || ch[0] });
            }
            const ex = active.querySelectorAll('input[name="expectations[]"]');
            if (ex.length && ex[0].offsetParent && !Array.from(ex).some((c) => c.checked)) {
                const wrap = active.querySelector('[data-cfa-required-group="expectations"]');
                items.push({ label: 'Expectations — select at least one / अपेक्षाएँ', field: ex[0], scrollEl: wrap || ex[0] });
            }
        }

        return items;
    }

    function updateMissingRequiredUI() {
        const listEl = $('missingRequiredList');
        const clearEl = $('missingAllClear');
        const progressText = $('requiredProgressText');
        if (!listEl) return;

        clearRequiredHighlights();

        const sectionA = $('sectionA');
        const sectionB = $('sectionB');
        const active = sectionA && sectionA.offsetParent ? sectionA : sectionB;
        const missing = collectMissingRequired();
        const slots = countRequiredSlots(active);

        if (progressText) {
            if (!active || slots === 0) progressText.textContent = '—';
            else if (missing.length === 0) progressText.textContent = 'All ' + slots + ' required done ✓';
            else progressText.textContent = missing.length + ' of ' + slots + ' required still empty';
        }

        listEl.innerHTML = '';
        if (clearEl) clearEl.hidden = missing.length > 0;

        missing.forEach((item) => {
            let wrap = item.scrollEl;
            if (!wrap) {
                const f = item.field;
                if (f.id === 'consent') wrap = f.closest('.consent-box');
                else wrap = f.parentElement;
            }
            if (wrap && wrap.classList) wrap.classList.add(HIGHLIGHT_CLASS);

            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'missing-jump-btn';
            btn.textContent = item.label;
            btn.addEventListener('click', function () {
                const scrollTarget = item.scrollEl || (item.field.id === 'consent' ? item.field.closest('.consent-box') : item.field.parentElement);
                const focusEl = item.field;
                if (scrollTarget && scrollTarget.scrollIntoView) scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (focusEl && typeof focusEl.focus === 'function') {
                    try {
                        focusEl.focus({ preventScroll: true });
                    } catch (e) {
                        focusEl.focus();
                    }
                }
            });
            li.appendChild(btn);
            listEl.appendChild(li);
        });
    }

    function updateCounter() {
        const sectionA = $('sectionA');
        const sectionB = $('sectionB');
        if (!sectionA || !sectionB) return;
        const activeSection = sectionA.offsetParent ? sectionA : sectionB;
        const fields = getVisibleFields(activeSection);
        let filled = 0;
        fields.forEach((el) => {
            if (isFieldFilled(el)) filled++;
        });
        const challenges = activeSection.querySelectorAll('input[name="challenges[]"]');
        const expectations = activeSection.querySelectorAll('input[name="expectations[]"]');
        let extraTotal = 0;
        let extraFilled = 0;
        if (challenges.length && challenges[0].offsetParent) {
            extraTotal++;
            extraFilled += Array.from(challenges).some((c) => c.checked) ? 1 : 0;
        }
        if (expectations.length && expectations[0].offsetParent) {
            extraTotal++;
            extraFilled += Array.from(expectations).some((c) => c.checked) ? 1 : 0;
        }
        const total = fields.length + extraTotal;
        const totalFilled = filled + extraFilled;
        const remaining = Math.max(0, total - totalFilled);
        const fc = $('filledCount');
        const rc = $('remainingCount');
        const cf = $('counterFill');
        if (fc) fc.textContent = String(totalFilled);
        if (rc) rc.textContent = String(remaining);
        if (cf) cf.style.width = total ? Math.round((totalFilled / total) * 100) + '%' : '0%';
        updateMissingRequiredUI();
    }

    function calculateAge(dobStr) {
        if (!dobStr) return null;
        const dob = new Date(dobStr);
        if (isNaN(dob.getTime())) return null;
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        return age;
    }

    function updateProfile() {
        const list = $('profileList');
        const placeholder = $('profilePlaceholder');
        if (!list || !placeholder) return;
        const items = [];
        const dob = $('dob') && $('dob').value;
        const gender = $('gender') && $('gender').value;
        const education = $('education') && $('education').value;
        const category = $('category') && $('category').value;
        const name =
            ($('fullname') && $('fullname').value.trim()) ||
            ($('shg_cbo_name') && $('shg_cbo_name').value.trim()) ||
            '';
        if (name) items.push({ label: 'Name', value: name });
        if (category) items.push({ label: 'Category', value: category });
        if (dob) {
            const age = calculateAge(dob);
            items.push({ label: 'DOB', value: dob });
            if (age !== null) items.push({ label: 'Age', value: age + ' years' });
        }
        if (gender && gender !== 'NA') items.push({ label: 'Gender', value: gender });
        if (education && education !== 'NA') items.push({ label: 'Education', value: education });
        list.innerHTML = '';
        if (items.length) {
            placeholder.style.display = 'none';
            items.forEach((i) => {
                const li = document.createElement('li');
                li.className = 'profile-item';
                li.innerHTML = '<span class="profile-label">' + i.label + ':</span> <span class="profile-value">' + i.value + '</span>';
                list.appendChild(li);
            });
        } else {
            placeholder.style.display = 'block';
        }
    }

    const guidesA = [
        'Select your applicant category to begin.',
        'Enter your full name as per ID proof.',
        'Provide contact details for communication.',
        'Select your block for location.',
        'Share your education and enterprise details.',
        'Fill turnover and employment info.',
        'Section A complete! Click Next to proceed.',
    ];
    const guidesB = [
        'Select business category and product.',
        'Share your challenges and expectations.',
        'Add business vision and training preference.',
        'Review your answers, then accept the consent.',
        'Check the consent and submit!',
    ];

    function updateGuide() {
        const sectionA = $('sectionA');
        const sectionB = $('sectionB');
        if (!sectionA || !sectionB) return;
        const isA = sectionA.offsetParent;
        const gc = $('guideContent');
        if (!gc) return;
        const dob = $('dob') && $('dob').value;
        if (isA && dob) {
            const age = calculateAge(dob);
            const msg =
                age !== null
                    ? 'Age: ' + age + ' years. Fill gender and education for a complete profile.'
                    : 'DOB entered. Select gender and education.';
            gc.innerHTML = '<p class="guide-text">' + msg + '</p>';
            return;
        }
        const activeSection = isA ? sectionA : sectionB;
        const fields = getVisibleFields(activeSection);
        let filled = 0;
        fields.forEach((el) => {
            if (isFieldFilled(el)) filled++;
        });
        const challenges = activeSection.querySelectorAll('input[name="challenges[]"]');
        const expectations = activeSection.querySelectorAll('input[name="expectations[]"]');
        if (challenges.length && Array.from(challenges).some((c) => c.checked)) filled++;
        if (expectations.length && Array.from(expectations).some((c) => c.checked)) filled++;
        const total =
            fields.length +
            (challenges.length && challenges[0].offsetParent ? 1 : 0) +
            (expectations.length && expectations[0].offsetParent ? 1 : 0);
        const progress = total ? filled / total : 0;
        const list = isA ? guidesA : guidesB;
        const idx = Math.min(Math.floor(progress * list.length), list.length - 1);
        gc.innerHTML = '<p class="guide-text">' + list[idx] + '</p>';
    }

    function updateProductOptions() {
        const category = $('business_category') && $('business_category').value;
        const productSelect = $('product');
        if (!productSelect || !window.CFA_PRODUCTS) return;
        productSelect.innerHTML = '<option value="">Select</option>';
        if (category && CFA_PRODUCTS[category]) {
            CFA_PRODUCTS[category].forEach((product) => {
                const option = document.createElement('option');
                option.value = product;
                option.textContent = product;
                productSelect.appendChild(option);
            });
        }
        const oldProduct = productSelect.getAttribute('data-old');
        if (oldProduct) {
            productSelect.value = oldProduct;
            productSelect.removeAttribute('data-old');
        }
        updateOtherProductField();
    }

    function updateOtherProductField() {
        const product = $('product') && $('product').value;
        const otherDiv = $('otherProductDiv');
        if (otherDiv) otherDiv.style.display = product === 'Others' ? 'block' : 'none';
    }

    window.toggleExpectationOther = function () {
        const cb = $('expectation_other_checkbox');
        const wrap = $('expectation_other_input');
        if (!cb || !wrap) return;
        if (cb.checked) wrap.classList.remove('hidden');
        else wrap.classList.add('hidden');
    };

    function wireInfoSource() {
        const src = $('info_source');
        if (!src) return;
        const resourceNameDiv = $('resourceNameDiv');
        const departmentNameDiv = $('departmentNameDiv');
        const v = src.value;
        if (v === 'Department') {
            if (departmentNameDiv) departmentNameDiv.style.display = 'block';
            if (resourceNameDiv) resourceNameDiv.style.display = 'none';
        } else if (v === 'RBI/MUY Staff') {
            if (departmentNameDiv) departmentNameDiv.style.display = 'none';
            if (resourceNameDiv) resourceNameDiv.style.display = 'block';
        } else {
            if (departmentNameDiv) departmentNameDiv.style.display = 'none';
            if (resourceNameDiv) resourceNameDiv.style.display = 'none';
        }
        checkSectionBComplete();
    }

    function wireFinancialSupport() {
        const fs = $('financial_support');
        const div = $('financialAmountDiv');
        if (!fs || !div) return;
        div.style.display = fs.value === 'Yes' ? 'block' : 'none';
        checkSectionBComplete();
    }

    function initFromServerOld() {
        const oldProduct = window.CFA_OLD_PRODUCT;
        const p = $('product');
        if (p && oldProduct) p.setAttribute('data-old', oldProduct);
        const bc = $('business_category');
        if (bc && window.CFA_OLD_BUSINESS_CATEGORY) {
            bc.value = window.CFA_OLD_BUSINESS_CATEGORY;
            updateProductOptions();
        }
        if (window.CFA_START_SECTION_B) {
            $('sectionA').style.display = 'none';
            $('sectionB').style.display = 'block';
            $('progressStep1').classList.remove('active');
            $('progressStep1').classList.add('done');
            $('progressStep2').classList.add('active');
            $('progressFill').style.width = '100%';
        }
        wireInfoSource();
        wireFinancialSupport();
        toggleCategoryFields();
        toggleShgMember();
        toggleTrainingInstitute();
        toggleEmploymentCount();
        toggleBankLoan();
        toggleBuyerCount();
        toggleRegistrationDetails();
        checkSectionAComplete();
        checkSectionBComplete();
        updateCounter();
        updateGuide();
        updateProfile();
        schedulePhoneCheck();
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!$('cfaMainForm')) return;

        const dobInput = $('dob');
        if (dobInput) {
            dobInput.addEventListener('change', validateDobAge);
            dobInput.addEventListener('input', validateDobAge);
        }

        $('category') && $('category').addEventListener('change', toggleCategoryFields);
        $('gender') &&
            $('gender').addEventListener('change', function () {
                if (this.dataset && this.dataset.cfaLocked === '1') lockGenderToFemale();
            });
        $('is_member') && $('is_member').addEventListener('change', toggleShgMember);
        $('training_received') && $('training_received').addEventListener('change', toggleTrainingInstitute);
        $('current_employment') && $('current_employment').addEventListener('change', toggleEmploymentCount);
        $('loan_taken') && $('loan_taken').addEventListener('change', toggleBankLoan);
        $('regular_buyer') && $('regular_buyer').addEventListener('change', toggleBuyerCount);
        $('enterprise_registered') && $('enterprise_registered').addEventListener('change', toggleRegistrationDetails);
        $('registration_type') && $('registration_type').addEventListener('change', syncRegistrationOther);

        $('proceedButton') && $('proceedButton').addEventListener('click', calculateStage);
        $('backToA') && $('backToA').addEventListener('click', goBackToSectionA);

        $('business_category') && $('business_category').addEventListener('change', updateProductOptions);
        $('product') && $('product').addEventListener('change', updateOtherProductField);
        $('financial_support') && $('financial_support').addEventListener('change', wireFinancialSupport);
        $('info_source') && $('info_source').addEventListener('change', wireInfoSource);

        $('mobile') &&
            $('mobile').addEventListener('input', function () {
                restrictMobile(this);
                validateMobileEl('mobile', 'mobile-error');
                schedulePhoneCheck();
            });
        $('alt_mobile') &&
            $('alt_mobile').addEventListener('input', function () {
                restrictMobile(this);
                validateMobileEl('alt_mobile', 'alt-mobile-error');
            });
        ['fullname', 'guardian_name'].forEach((id) => {
            const el = $(id);
            if (el) el.addEventListener('input', () => validateNameInput(el));
        });

        $('shg_name') && $('shg_name').addEventListener('input', validateShgNameIfNeeded);

        $('pincode') && $('pincode').addEventListener('input', validatePincode);
        $('email') && $('email').addEventListener('input', validateEmail);

        $('turnover_last_fy') &&
            $('turnover_last_fy').addEventListener('input', function () {
                validateAmountInput('turnover_last_fy', 'turnover-error', 'turnover-preview');
            });
        $('bank_loan') &&
            $('bank_loan').addEventListener('input', function () {
                validateAmountInput('bank_loan', 'bank-loan-error', 'bank-loan-preview');
            });
        $('financial_amount') &&
            $('financial_amount').addEventListener('input', function () {
                validateAmountInput('financial_amount', 'financial-amount-error', 'financial-amount-preview');
            });

        document.querySelectorAll('#sectionA input, #sectionA select').forEach((el) => {
            el.addEventListener('input', () => {
                checkSectionAComplete();
                updateCounter();
                updateGuide();
                updateProfile();
            });
            el.addEventListener('change', () => {
                checkSectionAComplete();
                updateCounter();
                updateGuide();
                updateProfile();
            });
        });

        document.querySelectorAll('#sectionB input, #sectionB select, #sectionB textarea').forEach((el) => {
            el.addEventListener('input', () => {
                checkSectionBComplete();
                updateCounter();
                updateGuide();
            });
            el.addEventListener('change', () => {
                checkSectionBComplete();
                updateCounter();
                updateGuide();
            });
        });

        $('consent') &&
            $('consent').addEventListener('change', () => {
                checkSectionBComplete();
                updateCounter();
                updateGuide();
            });

        initFromServerOld();
    });
})();
