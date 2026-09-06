<?php

namespace App\Http\Requests\Reports;

use App\Enums\ReportCategory;
use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;

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
            'scores.*' => ['required', 'numeric', 'between:1,10', 'decimal:0,1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ] + collect($toegestaan)
            ->mapWithKeys(fn (string $categorie) => [
                'scores.'.$categorie => ['required', 'numeric', 'between:1,10', 'decimal:0,1'],
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
            'scores.*.decimal' => 'Gebruik hooguit één cijfer achter de komma, bijvoorbeeld 7,4.',
        ];
    }

    public function attributes(): array
    {
        return [
            'note' => 'De toelichting',
        ];
    }

    /** @return array<string, float> */
    public function scores(): array
    {
        /** @var Player $player */
        $player = $this->route('player');

        // Alleen categorieën die bij deze positie horen, zodat er niets
        // extra's binnensluipt via het formulier.
        return collect($this->validated('scores'))
            ->only(ReportCategory::valuesForPosition($player->position))
            // Als float: een cijfer heeft nu een decimaal. (int) maakte er
            // stilzwijgend een 7 van waar de trainer 7,4 bedoelde.
            ->map(fn ($score) => round((float) $score, 1))
            ->all();
    }
}
