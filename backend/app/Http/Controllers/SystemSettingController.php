<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    private array $defaults = [
        'clinic_name' => 'Hair Clinic Pro',
        'clinic_phone' => '+252 61 400 1000',
        'clinic_address' => 'Mogadishu',
        'currency' => 'USD',
        'tax_percent' => '0',
        'slot_minutes' => '30',
        'working_days' => 'Saturday,Sunday,Monday,Tuesday,Wednesday',
    ];

    public function index(): JsonResponse
    {
        $stored = SystemSetting::pluck('setting_value', 'setting_key')->all();
        return response()->json(array_merge($this->defaults, $stored));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_name' => 'required|string|max:150',
            'clinic_phone' => 'nullable|string|max:40',
            'clinic_address' => 'nullable|string|max:255',
            'currency' => 'required|string|max:10',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'slot_minutes' => 'required|integer|min:5|max:240',
            'working_days' => 'nullable|string|max:255',
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => (string) $value]
            );
        }

        return $this->index();
    }
}
