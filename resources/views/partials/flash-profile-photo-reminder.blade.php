@if (session('profile_photo_reminder') && auth()->check() && auth()->user()->role === 'district_staff' && ! auth()->user()->avatar_path)
    <div class="banner banner--warning" role="status">{{ session('profile_photo_reminder') }}</div>
@endif
