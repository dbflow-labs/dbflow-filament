<dl class="grid gap-3">
    @foreach ($rows as $label => $value)
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
            <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $value }}</dd>
        </div>
    @endforeach
</dl>
