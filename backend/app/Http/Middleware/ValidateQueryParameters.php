<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidateQueryParameters
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET')) {
            $rules = [
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'search' => 'nullable|string|max:255',
                'date' => 'nullable|date_format:Y-m-d',
                'patient_id' => 'nullable|integer|min:1',
                'doctor_id' => 'nullable|integer|min:1',
                'user_id' => 'nullable|integer|min:1',
                'status' => 'nullable|string|max:50',
                'role' => 'nullable|string|max:50',
                'payment_method' => 'nullable|string|max:50',
                'report_type' => 'nullable|string|max:80',
                'period' => 'nullable|in:daily,weekly,monthly,yearly,custom',
                'from' => 'nullable|date_format:Y-m-d',
                'to' => 'nullable|date_format:Y-m-d',
                'date_from' => 'nullable|date_format:Y-m-d',
                'date_to' => 'nullable|date_format:Y-m-d',
            ];
            if ($request->filled('from')) $rules['to'] .= '|after_or_equal:from';
            if ($request->filled('date_from')) $rules['date_to'] .= '|after_or_equal:date_from';
            Validator::make($request->query(), $rules)->validate();
        }
        return $next($request);
    }
}
