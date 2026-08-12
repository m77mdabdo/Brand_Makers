@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-line focus:border-petrol focus:ring-petrol rounded-md shadow-sm']) }}>
