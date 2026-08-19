@props(['fromDate' => '', 'toDate' => '', 'action' => ''])

<div class="bg-card border border-border rounded-lg shadow-sm p-4 mb-6">
    <form method="GET" action="{{ $action }}" class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label for="from_date" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
            <input type="date"
                   id="from_date"
                   name="from_date"
                   value="{{ $fromDate }}"
                   class="block w-full rounded-md border border-input bg-background h-10 px-3 text-sm transition-colors focus:ring-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
        </div>

        <div class="flex-1 min-w-[200px]">
            <label for="to_date" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
            <input type="date"
                   id="to_date"
                   name="to_date"
                   value="{{ $toDate }}"
                   class="block w-full rounded-md border border-input bg-background h-10 px-3 text-sm transition-colors focus:ring-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-md h-10 px-4 text-sm font-medium shadow-sm transition-colors bg-primary text-primary-foreground hover:bg-primary/90">
                Apply Filter
            </button>
            <a href="{{ $action }}"
               class="inline-flex items-center justify-center gap-2 rounded-md h-10 px-4 text-sm font-medium border border-border bg-background hover:bg-accent hover:text-accent-foreground transition-colors">
                Reset
            </a>
        </div>
    </form>

    <div class="mt-3 text-xs text-gray-500">
        <strong>Tip:</strong> Date ranges are bookmarkable — you can share filtered report URLs directly.
    </div>
</div>
