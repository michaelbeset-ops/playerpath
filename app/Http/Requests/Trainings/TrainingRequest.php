<?php

namespace App\Http\Requests\Trainings;

use App\Models\Training;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $training = $this->route('training');

        return $training
            ? $this->user()->can('update', $training)
            : $this->user()->can('create', Training::class);
    }

    public function rules(): array
    {
        return [
            // exists kent de global scope niet, dus expliciet op school begrenzen.
            // Zie CLAUDE.md 3.1.
            'group_id' => [
                'required',
                'integer',
                Rule::exists('groups', 'id')->where('school_id', app(Tenancy::class)->id()),
            ],
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],

            // De trainers die erbij staan. Alleen gebruikers van deze school;
            // exists kent de global scope niet, dus expliciet begrenzen.
            'trainers' => ['array'],
            'trainers.*' => [
                'integer',
                Rule::exists('users', 'id')->where('school_id', app(Tenancy::class)->id()),
            ],

            // Alleen bij aanmaken: een reeks wekelijkse trainingen in één keer.
            'repeat_until' => ['nullable', 'date', 'after:date', 'before:'.now()->addYear()->toDateString()],
        ];
    }

    public function attributes(): array
    {
        return [
            'group_id' => 'De groep',
            'date' => 'De datum',
            'starts_at' => 'De begintijd',
            'ends_at' => 'De eindtijd',
            'location' => 'De locatie',
            'note' => 'De toelichting',
            'repeat_until' => 'De herhaaldatum',
            'trainers' => 'De trainers',
        ];
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => 'De eindtijd moet na de begintijd liggen.',
            'group_id.exists' => 'Deze groep bestaat niet binnen jouw school.',
            'repeat_until.after' => 'Herhalen tot moet na de eerste trainingsdatum liggen.',
            'repeat_until.before' => 'Plan maximaal een jaar vooruit.',
            'trainers.*.exists' => 'Een van de gekozen trainers hoort niet bij deze school.',
        ];
    }

    /** @return array<string, mixed> */
    public function trainingData(): array
    {
        $datum = $this->validated('date');

        return [
            'group_id' => $this->validated('group_id'),
            'starts_at' => CarbonImmutable::parse($datum.' '.$this->validated('starts_at')),
            'ends_at' => CarbonImmutable::parse($datum.' '.$this->validated('ends_at')),
            'location' => $this->validated('location'),
            'note' => $this->validated('note'),
        ];
    }

    /**
     * De momenten waarop een training komt te staan.
     *
     * Zonder herhaaldatum is dat er één. Met herhaaldatum krijg je wekelijks
     * een losse training tot en met die datum. Die trainingen staan daarna
     * volledig op zichzelf: er is geen reeks die je in één keer aanpast.
     *
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function occurrences(CarbonImmutable $start, CarbonImmutable $end, ?string $repeatUntil): array
    {
        $momenten = [['starts_at' => $start, 'ends_at' => $end]];

        if ($repeatUntil === null) {
            return $momenten;
        }

        $grens = CarbonImmutable::parse($repeatUntil)->endOfDay();
        $volgendeStart = $start->addWeek();
        $volgendeEind = $end->addWeek();

        while ($volgendeStart->lessThanOrEqualTo($grens)) {
            $momenten[] = ['starts_at' => $volgendeStart, 'ends_at' => $volgendeEind];

            $volgendeStart = $volgendeStart->addWeek();
            $volgendeEind = $volgendeEind->addWeek();
        }

        return $momenten;
    }
}
