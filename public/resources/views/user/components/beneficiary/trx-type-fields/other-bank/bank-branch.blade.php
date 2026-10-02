<option selected disabled>{{ __('Select Branch') }}</option>
@foreach ($branches as $item)
    <option value="{{ $item->id }}" >{{ $item->name }}</option>
@endforeach
