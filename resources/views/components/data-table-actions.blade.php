@props([
    'model',
    'viewRoute' => null,
    'editRoute' => null,
    'deleteRoute' => null,
    'confirm' => 'Delete this record?',
])

<td class="px-6 py-4 whitespace-nowrap text-right text-sm">
    <div class="inline-flex items-center gap-2">
        @if ($viewRoute)
            <a
                href="{{ $viewRoute($model) }}"
                title="View"
                class="inline-flex items-center justify-center rounded-md p-2 text-primary transition-colors duration-150 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <x-heroicon-o-eye class="w-5 h-5" />
            </a>
        @endif

        @if ($editRoute)
            <a
                href="{{ $editRoute($model) }}"
                title="Edit"
                class="inline-flex items-center justify-center rounded-md p-2 text-primary transition-colors duration-150 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <x-heroicon-o-pencil-square class="w-5 h-5" />
            </a>
        @endif

        @if ($deleteRoute)
            @can('admin')
                <form
                    action="{{ $deleteRoute($model) }}"
                    method="POST"
                    onsubmit="return confirm('{{ addslashes($confirm) }}');"
                    class="inline"
                >
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        title="Delete"
                        class="inline-flex items-center justify-center rounded-md p-2 text-red-600 transition-colors duration-150 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <x-heroicon-o-trash class="w-5 h-5" />
                    </button>
                </form>
            @endcan
        @endif
    </div>
</td>
