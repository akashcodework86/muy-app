@php
    /** @var list<string> $items */
    $name = $name ?? '';
    $type = $type ?? 'radio'; // radio | checkbox
    $multiple = $type === 'checkbox';
    $fieldName = $multiple ? "answers[{$name}][]" : "answers[{$name}]";
    $selected = old('answers.'.$name, $value ?? ($multiple ? [] : ''));
    if ($multiple && ! is_array($selected)) {
        $selected = $selected === '' || $selected === null ? [] : [$selected];
    }
@endphp
<div class="hs-options" data-field="{{ $name }}" role="{{ $multiple ? 'group' : 'radiogroup' }}" aria-label="{{ $name }}">
    @foreach ($items as $item)
        <label class="hs-check">
            @if ($multiple)
                <input type="checkbox" name="{{ $fieldName }}" value="{{ $item }}"
                       @checked(in_array($item, $selected, true))
                       @if (!empty($disabled)) disabled @endif>
            @else
                <input type="radio" name="{{ $fieldName }}" value="{{ $item }}"
                       @checked((string) $selected === (string) $item)
                       @if (!empty($disabled)) disabled @endif>
            @endif
            <span>{{ $item }}</span>
        </label>
    @endforeach
</div>
