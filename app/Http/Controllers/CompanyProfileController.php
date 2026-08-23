<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyProfileController extends Controller
{
    /**
     * Show the single company profile settings page.
     */
    public function index()
    {
        $profile = CompanyProfile::profile();

        $logoUrl = $profile->logo_path && Storage::disk('local')->exists($profile->logo_path)
            ? Storage::disk('local')->temporaryUrl($profile->logo_path, now()->addMinutes(60))
            : null;

        return view('company_profile.index', [
            'profile' => $profile,
            'logoUrl' => $logoUrl,
        ]);
    }

    /**
     * Update the single company profile row.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:11'],
            'terms_and_conditions' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $profile = CompanyProfile::profile();

        $data = array_intersect_key($validated, array_flip([
            'company_name',
            'address',
            'city',
            'state',
            'pincode',
            'phone',
            'email',
            'gstin',
            'bank_name',
            'bank_account_no',
            'bank_ifsc',
            'terms_and_conditions',
        ]));

        // Non-nullable columns store an empty string instead of null.
        foreach (['address', 'city', 'state', 'pincode', 'phone', 'email', 'gstin'] as $field) {
            $data[$field] = $data[$field] ?? '';
        }

        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('local')->delete($profile->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('company-profile', 'local');
        } elseif ($request->boolean('remove_logo') && $profile->logo_path) {
            Storage::disk('local')->delete($profile->logo_path);
            $data['logo_path'] = null;
        }

        $profile->update($data);

        return redirect()->route('company-profile.index')->with('success', 'Company profile updated successfully.');
    }
}
