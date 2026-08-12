@props(['action', 'label' => null, 'confirm' => null])

{{-- A real form with a DELETE method, not a link. A destructive action
     behind a GET is one prefetch or crawler away from firing itself. --}}
<form
    method="POST"
    action="{{ $action }}"
    onsubmit="return confirm({{ Js::from($confirm ?? __('dashboard.common.confirm_delete')) }})"
    class="inline"
>
    @csrf
    @method('DELETE')
    <button
        type="submit"
        class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-orange-ink transition hover:bg-orange-ink hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-ink"
    >
        {{ $label ?? __('dashboard.common.delete') }}
    </button>
</form>
