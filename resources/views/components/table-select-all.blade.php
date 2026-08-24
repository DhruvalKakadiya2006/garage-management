{{-- Header checkbox for the shared multi-select table pattern. Place inside the
     first <th> of a table wrapped in a `tableSelection()` Alpine scope.
     Checked when every visible row is selected, indeterminate when only some
     are, unchecked when none are. --}}
<input type="checkbox"
       :checked="allSelected"
       @change="toggleAll()"
       x-effect="$el.indeterminate = someSelected && !allSelected"
       aria-label="Select all visible rows"
       class="h-4 w-4 align-middle rounded border-input text-primary focus:ring-ring cursor-pointer">