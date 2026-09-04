<?php

namespace App\Http\Requests\Players;

use App\Enums\PlayerPosition;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $player = $this->route('player');

        return $player
            ? $this->user()->can('update', $player)
            : $this->user()->can('create', \App\Models\Player::class);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:'.now()->subYears(60)->toDateString()],
            'position' => ['required', Rule::enum(PlayerPosition::class)],
            'is_active' => ['required', 'boolean'],

            // Let op: exists gaat rechtstreeks naar de database en kent de
            // global scope niet. Zonder deze where zou een groep-id van een
            // andere school gewoon door de validatie komen.
            'groups' => ['array'],
            'groups.*' => [
                'integer',
                Rule::exists('groups', 'id')->where('school_id', app(Tenancy::class)->id()),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'De voornaam',
            'last_name' => 'De achternaam',
            'date_of_birth' => 'De geboortedatum',
            'position' => 'De positie',
            'is_active' => 'De status',
            'groups' => 'De groepen',
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'De geboortedatum moet in het verleden liggen.',
            'date_of_birth.after' => 'Deze geboortedatum lijkt niet te kloppen.',
            'groups.*.exists' => 'Een van de gekozen groepen bestaat niet binnen deze school.',
        ];
    }
}
