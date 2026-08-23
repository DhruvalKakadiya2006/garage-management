@php
    $profile = \App\Models\CompanyProfile::profile();
    $logoAbsPath = null;
    if ($profile->logo_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($profile->logo_path)) {
        $logoAbsPath = str_replace('\\', '/', \Illuminate\Support\Facades\Storage::disk('local')->path($profile->logo_path));
    }
    $addressLine = trim(implode(', ', array_filter([
        $profile->address,
        $profile->city,
        $profile->state,
        $profile->pincode,
    ])));
@endphp

<div class="doc-header">
    <table style="width:100%;">
        <tr>
            <td style="width:60%; vertical-align:top;">
                @if ($logoAbsPath)
                    <img src="{{ $logoAbsPath }}" class="company-logo" alt="">
                @endif
                <div class="company-name">{{ $profile->company_name ?: 'Your Company Name' }}</div>
                @if ($addressLine)
                    <div class="company-line">{{ $addressLine }}</div>
                @endif
                @if ($profile->gstin)
                    <div class="company-line">GSTIN: {{ $profile->gstin }}</div>
                @endif
                @if ($profile->phone)
                    <div class="company-line">Phone: {{ $profile->phone }}</div>
                @endif
                @if ($profile->email)
                    <div class="company-line">Email: {{ $profile->email }}</div>
                @endif
            </td>
            <td class="doc-meta" style="width:40%; vertical-align:top;">
                <div class="doc-title">{{ $documentTitle }}</div>
                @foreach ($docMeta as $label => $value)
                    <div class="field"><strong>{{ $label }}:</strong> {{ $value }}</div>
                @endforeach
            </td>
        </tr>
    </table>
</div>