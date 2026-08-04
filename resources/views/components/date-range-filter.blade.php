@props(['fromDate' => '', 'toDate' => '', 'action' => ''])

<div class="bg-white shadow-sm sm:rounded-lg p-4 mb-6">
    <form method="GET" action="{{ $action }}" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label for="from_date" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
            <input type="date" 
                   id="from_date" 
                   name="from_date" 
                   value="{{ $fromDate }}"
                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
        </div>

        <div class="flex-1 min-w-[200px]">
            <label for="to_date" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
            <input type="date" 
                   id="to_date" 
                   name="to_date" 
                   value="{{ $toDate }}"
                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm">
        </div>

        <div class="flex gap-2">
            <button type="submit" 
                    class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-md">
                Apply Filter
            </button>
            <a href="{{ $action }}" 
               class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-md">
                Reset
            </a>
        </div>
    </form>

    <div class="mt-3 text-xs text-gray-500">
        <strong>Tip:</strong> Date ranges are bookmarkable — you can share filtered report URLs directly.
    </div>
</div>
