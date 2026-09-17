<?php

namespace App\Http\Requests\Trainings;

use App\Enums\ProductAudience;
use App\Models\Location;
use App\Models\Training;
use App\Support\Money\Money;
use App\Support\PlayerCard\BadgeSettings;
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
            // Een privétraining (een geboekt moment) heeft geen groep en krijgt
            // er bij bewerken ook geen: dan zou hij een groepstraining worden.
            'group_id' => [
                Rule::requiredIf(! $this->isPrivate()),
                'nullable',
                'integer',
                Rule::exists('groups', 'id')->where('school_id', app(Tenancy::class)->id()),
            ],
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_id' => [
                'nullable', 'integer',
                Rule::exists('locations', 'id')->where('school_id', app(Tenancy::class)->id()),
            ],
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

            // Los inschrijven: wie, hoeveel, wat kost het, hoe betalen, goedkeuren.
            'open_enrollment' => ['boolean'],
            'age_categories' => ['nullable', 'array'],
            'age_categories.*' => ['string', Rule::in(array_column(BadgeSettings::categories(), 'key'))],
            'audience' => ['nullable', Rule::enum(ProductAudience::class)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'price' => ['nullable', 'string', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', Rule::in(['online', 'cash'])],
            'requires_approval' => ['boolean'],
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
            'age_categories' => 'De leeftijdscategorieën',
            'audience' => 'De positie',
            'capacity' => 'Het maximum aantal',
            'price' => 'De prijs',
            'payment_methods' => 'De betaalwijzen',
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
            'price.regex' => 'Vul een bedrag in, bijvoorbeeld 7,50.',
        ];
    }

    /** @return array<string, mixed> */
    public function trainingData(): array
    {
        $datum = $this->validated('date');

        return [
            ...($this->isPrivate() ? [] : ['group_id' => $this->validated('group_id')]),
            'starts_at' => CarbonImmutable::parse($datum.' '.$this->validated('starts_at')),
            'ends_at' => CarbonImmutable::parse($datum.' '.$this->validated('ends_at')),
            // De gekozen locatie vult de tekst; die blijft staan zoals hij op
            // dat moment heette. Zie Location.
            'location_id' => $this->validated('location_id'),
            'location' => $this->locatienaam() ?? $this->vrijeLocatie(),
            'note' => $this->validated('note'),
            ...$this->enrollmentData(),
        ];
    }

    /**
     * De inschrijfregels. Staat inschrijven uit, dan worden de regels niet
     * bewaard: een training die dicht is heeft geen prijs en geen limiet.
     *
     * @return array<string, mixed>
     */
    public function enrollmentData(): array
    {
        $open = (bool) $this->validated('open_enrollment', false);

        if (! $open) {
            return [
                'open_enrollment' => false,
                'age_categories' => null,
                'audience' => ProductAudience::All,
                'capacity' => null,
                'price_cents' => 0,
                'payment_methods' => null,
                'requires_approval' => false,
            ];
        }

        $wijzen = array_values(array_unique($this->validated('payment_methods') ?? []));

        return [
            'open_enrollment' => true,
            'age_categories' => array_values(array_unique($this->validated('age_categories') ?? [])) ?: null,
            'audience' => $this->validated('audience') ?? ProductAudience::All->value,
            'capacity' => $this->validated('capacity'),
            'price_cents' => Money::toCents((string) ($this->validated('price') ?? '0')),
            // Zonder keuze allebei: dat is wat de meeste scholen bedoelen.
            'payment_methods' => $wijzen === [] ? null : $wijzen,
            'requires_approval' => (bool) $this->validated('requires_approval', false),
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

    /** Bewerken we een privétraining (een geboekt moment zonder groep)? */
    public function isPrivate(): bool
    {
        $training = $this->route('training');

        return $training instanceof Training && $training->slot_id !== null;
    }

    /**
     * De locatie als losse tekst, zonder gekozen locatie.
     *
     * Het formulier stuurt die tekst niet mee. Een oude training met alleen
     * een vrije tekst verliest die dan niet bij opslaan; wie een gekozen
     * locatie leegmaakt, maakt hem wel leeg.
     */
    protected function vrijeLocatie(): ?string
    {
        if ($this->has('location')) {
            return $this->validated('location');
        }

        $training = $this->route('training');

        return $training instanceof Training && $training->location_id === null
            ? $training->location
            : null;
    }

    /** De naam van de gekozen locatie, als er een gekozen is. */
    protected function locatienaam(): ?string
    {
        $id = $this->validated('location_id');

        return $id === null ? null : Location::whereKey($id)->value('name');
    }
}
