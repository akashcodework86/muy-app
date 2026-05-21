@push('scripts')
<script>
(function () {
    const maleInput = document.getElementById('tpMaleParticipants');
    const femaleInput = document.getElementById('tpFemaleParticipants');
    const totalInput = document.getElementById('tpTotalParticipants');
    if (!maleInput || !femaleInput || !totalInput) {
        return;
    }

    const syncTotal = function () {
        const male = parseInt(maleInput.value || '0', 10) || 0;
        const female = parseInt(femaleInput.value || '0', 10) || 0;
        totalInput.value = String(male + female);
    };

    maleInput.addEventListener('input', syncTotal);
    femaleInput.addEventListener('input', syncTotal);
    syncTotal();
}());
</script>
@endpush
