<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('followups.create');
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('due_at') && CarbonImmutable::parse($this->input('due_at'), config('ecosystem.display_timezone'))->utc()->lessThanOrEqualTo(now())) {
                $validator->errors()->add('due_at', 'The scheduled time must be in the future (Africa/Lagos).');
            }
        }];
    }

    public function rules(): array
    {
        return ['user_id' => 'required|integer', 'subject' => 'required|string|max:160', 'message' => 'required|string|max:5000', 'channels' => 'required|array|min:1', 'channels.*' => 'required|in:database,mail', 'due_at' => 'required|date_format:Y-m-d\TH:i'];
    }
}
