<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class CompanySettingController extends Controller
{
    public function edit()
    {
        $setting = CompanySetting::current();

        return view('admin.backend.settings.company', compact('setting'));
    }

    public function update(Request $request)
    {
        $setting = CompanySetting::current();

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:20',
            'currency_symbol' => 'nullable|string|max:10',
            'default_terms' => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:20',
            'footer_note' => 'nullable|string|max:255',
            'footer_contact' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $this->deleteLogo($setting->logo);
            $data['logo'] = $this->storeLogo($request);
        }

        $setting->update($data);

        return redirect()->back()->with('success', 'Company settings updated.');
    }

    private function storeLogo(Request $request): string
    {
        $manager = new ImageManager(new Driver);
        $file = $request->file('logo');
        $name = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();

        $dir = public_path('upload/company');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $manager->read($file)->scaleDown(width: 400)->save($dir.'/'.$name);

        return $name;
    }

    private function deleteLogo(?string $logo): void
    {
        if ($logo && file_exists(public_path('upload/company/'.$logo))) {
            unlink(public_path('upload/company/'.$logo));
        }
    }
}
