<?php

namespace App\Http\Requests\Reports;

use App\Enums\ReportCategory;
use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createReport', $this->route('player'));
    }

    public function rules(): array
    {
        /** @var Player $player */
        $player = $this->route('player');

        $toegestaan = ReportCategory::valuesForPosition($player->position);

        return [
            'scores' => ['required', 'array', 'size:'.count($toegestaan)],
            'scores.*' => ['required', 'integer', 'between:1,10'],
            'note' => ['nullable', 'string', 'max:2000'],
        ] + collect($toegestaan)
            ->mapWithKeys(fn (string $categorie) => [
                'scores.'.$categorie => ['required', 'integer', 'between:1,10'],
            ])
            ->all();
    }

    public function messages(): array
    {
        return [
            'scores.required' => 'Vul voor elke categorie een cijfer in.',
            'scores.size' => 'Vul voor elke categorie een cijfer in.',
            'scores.*.required' => 'Vul voor elke categorie een cijfer in.',
            'scores.*.between' => 'Een cijfer moet tussen 1 en 10 liggen.',
            'scores.*.integer' => 'Een cijfer moet een heel getal zijn.',
        ];
    }

    public function attributes(): array
    {
        return [
            'note' => 'De toelichting',
        ];
    }

    /** @return array<string, int> */
    public function scores(): array
    {
        /** @var Player $player */
        $player = $this->route('player');

        // Alleen categorieën die bij deze positie horen, zodat er niets
        // extra's binnensluipt via het formulier.
        return collect($this->validated('scores'))
            ->only(ReportCategory::valuesForPosition($player->position))
            ->map(fn ($score) => (int) $score)
            ->all();
    }
}
