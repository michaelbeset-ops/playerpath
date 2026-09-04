<?php

namespace App\Http\Requests\Groups;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group
            ? $this->user()->can('update', $group)
            : $this->user()->can('create', Group::class);
    }

    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Uniek binnen de school; de global scope beperkt de check al
                // tot de eigen school.
                Rule::unique('groups', 'name')
                    ->where('school_id', app(\App\Support\Tenancy\Tenancy::class)->id())
                    ->ignore($group?->id),
            ],
            'age_category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'De naam',
            'age_category' => 'De leeftijdscategorie',
            'is_active' => 'De status',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Er bestaat al een groep met deze naam.',
        ];
    }
}
