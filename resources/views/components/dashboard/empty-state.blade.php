@props(['message', 'action' => null, 'actionLabel' => null])

<div class="rounded-2xl border border-dashed border-line bg-white px-6 py-12 text-center">
    <p class="text-sm text-muted-ink">{{ $message }}</p>

    @if ($action)
        <a href="{{ $action }}" class="btn btn-primary mt-5">{{ $actionLabel }}</a>
    @endif
</div>
