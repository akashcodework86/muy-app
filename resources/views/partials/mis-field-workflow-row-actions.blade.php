@php
    use App\Support\MisFieldActivityApproval;
    $user = auth()->user();
    $canEdit = MisFieldActivityApproval::submitterCanEdit($user, $row);
    $canWithdraw = MisFieldActivityApproval::submitterCanWithdraw($user, $row);
    $ownsRow = $user && (int) ($row->submitted_by_user_id ?? 0) === (int) $user->id;
    if (! empty($ownerCanManageApproved) && $ownsRow) {
        $canEdit = true;
        $canWithdraw = true;
    }
    $editRoute = $editRoute ?? null;
    $destroyRoute = $destroyRoute ?? null;
    $destroyRouteParams = $destroyRouteParams ?? [$row];
@endphp
@if ($canEdit && $editRoute)
    <a class="{{ $editClass ?? 'tp-btn--edit' }}" href="{{ route($editRoute, $row) }}">{{ $editLabel ?? 'Edit & resubmit' }}</a>
@endif
@if ($canWithdraw && $destroyRoute)
    <form method="post" action="{{ route($destroyRoute, $destroyRouteParams) }}" style="display:inline;" onsubmit="return confirm('Withdraw this submission?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="{{ $withdrawClass ?? 'tp-btn--edit' }}" style="cursor:pointer;">Withdraw</button>
    </form>
@endif
