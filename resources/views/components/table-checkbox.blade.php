@props(['id', 'attr' => 'data-row-id'])

{{-- Per-row checkbox for the shared multi-select table pattern. Place inside
     the first <td> of each row. The row attribute (default `data-row-id`, but
     customizable — e.g. `data-brand-id` / `data-model-id` — when a page hosts
     more than one independent selection scope) is what the `tableSelection()`
     Alpine component reads to know the visible rows on the current page
     (drives the select-all header checkbox). --}}
<input type="checkbox"
       {{ $attr }}="{{ $id }}"
       :checked="isSelected($el.getAttribute('{{ $attr }}'))"
       @change="toggleRow($el.getAttribute('{{ $attr }}'))"
       aria-label="Select row"
       class="h-4 w-4 align-middle rounded border border-gray-300 text-primary focus:ring-ring cursor-pointer">