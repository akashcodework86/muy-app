<form class="mg-filter" method="get" action="{{ $action }}">
    <div class="mg-filter__row">
        <div class="mg-field">
            <label for="mg-hub">Hub</label>
            <select class="mg-input" id="mg-hub" name="hub" onchange="this.form.submit()">
                <option value="">All hubs</option>
                @foreach ($hubs as $hub)
                    <option value="{{ $hub->id }}" @selected((int) ($filters['hub'] ?? 0) === (int) $hub->id)>{{ $hub->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mg-field">
            <label for="mg-district">District</label>
            <select class="mg-input" id="mg-district" name="district">
                <option value="">All districts</option>
                @foreach ($districts as $district)
                    <option value="{{ $district->id }}" @selected((int) ($filters['district'] ?? 0) === (int) $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mg-field">
            <label for="mg-from">From</label>
            <input class="mg-input" id="mg-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="mg-field">
            <label for="mg-to">To</label>
            <input class="mg-input" id="mg-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>
        <div class="mg-field" style="flex:1.4">
            <label for="mg-q">Search</label>
            <input class="mg-input" id="mg-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Title or district">
        </div>
        <button class="mg-btn" type="submit">Apply</button>
        <a class="mg-btn mg-btn--ghost" href="{{ $action }}">Reset</a>
    </div>
</form>
