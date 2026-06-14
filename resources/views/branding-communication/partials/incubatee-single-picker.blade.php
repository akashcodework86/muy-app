<label for="bcIncubateeSearch">Incubatee <span class="bc-req">*</span></label>
<input type="text" id="bcIncubateeSearch" class="bc-search" autocomplete="off"
    placeholder="Search by name, phone, or application number…"
    value="{{ old('incubatee_label') }}">
<input type="hidden" name="cfa_submission_id" id="bcCfaId" value="{{ old('cfa_submission_id') }}">
<input type="hidden" name="legacy_application_id" id="bcLegacyId" value="{{ old('legacy_application_id') }}">
<input type="hidden" name="incubatee_key" id="bcIncubateeKey" value="{{ old('incubatee_key') }}">
<div class="bc-picker">
    <div class="bc-picker__col">
        <div class="bc-picker__head">
            <span>Search results</span>
            <span id="bcResultsCount" class="bc-picker__count" hidden>0</span>
        </div>
        <div id="bcResults" class="bc-picker__body bc-picker__body--results" role="listbox">
            <p class="bc-picker__empty">Type at least 2 characters to search incubatees.</p>
        </div>
    </div>
    <div class="bc-picker__col">
        <div class="bc-picker__head" id="bcDetailHead">Incubatee details</div>
        <div id="bcDetail" class="bc-picker__body bc-picker__body--detail">
            <p class="bc-picker__empty">Hover a result to preview. Click to select.</p>
        </div>
    </div>
</div>
<p class="bc-hint">Search and select one incubatee for this case study or testimonial.</p>
@error('incubatee_key')<p class="bc-hint" style="color:#b91c1c;">{{ $message }}</p>@enderror
