@isset($permission)
    @if (admin_permission_by_name($permission))
        <a href="{{ $href ?? "javascript:void(0)" }}" title="{{ $title ?? '' }}" class="{{ $class ?? "" }}"><i class="{{ $icon ?? "" }}"></i>{{ __($text ?? "") }}</a>
    @endif
@else
    <a href="{{ $href ?? "javascript:void(0)" }}" title="{{ $title ?? '' }}" class="{{ $class ?? "" }}"><i class="{{ $icon ?? "" }}"></i>{{ __($text ?? "") }}</a>
@endisset
