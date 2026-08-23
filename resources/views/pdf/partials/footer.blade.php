@php
    $documentLabel = $documentLabel ?? 'document';
    $companyName = $companyName ?? '';
@endphp

<div class="footer">
    This is a computer-generated {{ $documentLabel }}. Generated on {{ now()->format('d M Y H:i') }} — {{ $companyName }}
</div>