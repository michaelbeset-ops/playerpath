<?php

namespace App\Support\PlayerCard;

use App\Models\School;
use App\Support\Rating\AgeCategory;
use Illuminate\Support\Str;

/**
 * Welke mijlpalen er bij deze school gelden.
 *
 * Negen badges bestaan er; standaard staan er **vier** aan, de leukste en
 * meest motiverende: je eerste rapport, vijf keer aanwezig, vijf punten
 * gegroeid en een doel gehaald. Een lijst van negen was te lang, en een
 * kaart met acht "nog te behalen" leest als een verlanglijst.
 *
 * De trainer of eigenaar kiest zelf welke er gelden, in één keer voor alle
 * spelers of per leeftijdscategorie (een O8 hoeft niet aan "alle categorieën
 * op 70" te werken). Opslag in `schools.rating_settings['badges']`, net als
 * de rest van de rekenkern: standaard in code, alleen afwijkingen bewaard.
 *
 * Daarnaast kan een school **eigen mijlpalen** bedenken ("Eerste wedstrijd
 * gekeept"), met een naam en een omschrijving die je zelf typt. Die hebben
 * geen regel om uit af te leiden, dus de trainer kent ze met de hand toe
 * (`player_badges`). Ze gelden voor alle spelers en staan naast de standaard.
 */
class BadgeSettings
{
    /** @var list<string> */
    public const STANDAARD = ['eerste_rapport', 'aanwezig_vijf', 'groei', 'doel_gehaald'];

    /** Voorvoegsel van een eigen mijlpaal, zodat hij nooit botst met de catalogus. */
    public const EIGEN = 'eigen_';

    /** @var array{default: list<string>, categories: array<string, list<string>>, custom: list<array{key: string, label: string, description: string}>} */
    protected array $waarden;

    public function __construct(?School $school = null)
    {
        $opgeslagen = $school?->rating_settings['badges'] ?? [];

        $this->waarden = [
            'default' => $this->schoon($opgeslagen['default'] ?? null) ?? self::STANDAARD,
            'categories' => collect($opgeslagen['categories'] ?? [])
                ->map(fn ($keys) => $this->schoon($keys))
                ->filter()
                ->all(),
            'custom' => self::eigen($opgeslagen['custom'] ?? []),
        ];
    }

    public static function for(?School $school): self
    {
        return new self($school);
    }

    /**
     * De sleutels die gelden voor een speler in deze categorie.
     *
     * @return list<string>
     */
    public function keysFor(?string $category): array
    {
        if ($category !== null && isset($this->waarden['categories'][$category])) {
            return $this->waarden['categories'][$category];
        }

        return $this->waarden['default'];
    }

    /** @return list<string> */
    public function defaultKeys(): array
    {
        return $this->waarden['default'];
    }

    /** @return array<string, list<string>> */
    public function categoryOverrides(): array
    {
        return $this->waarden['categories'];
    }

    /**
     * De mijlpalen die deze school zelf heeft bedacht.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public function customBadges(): array
    {
        return $this->waarden['custom'];
    }

    /**
     * Alle leeftijdscategorieën waarvoor je kunt afwijken.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function categories(): array
    {
        $keys = array_map(fn (int $band) => 'O'.$band, AgeCategory::BANDEN);
        $keys[] = 'O18+';

        return array_map(fn (string $key) => ['key' => $key, 'label' => AgeCategory::describe($key)], $keys);
    }

    /**
     * @param  list<string>  $default
     * @param  array<string, list<string>|null>  $categories  null of leeg = geen afwijking
     * @param  list<array{key?: ?string, label: string, description?: ?string}>  $custom
     */
    public static function save(School $school, array $default, array $categories, array $custom = []): void
    {
        $instellingen = $school->rating_settings ?? [];

        $instellingen['badges'] = [
            'default' => array_values($default),
            'categories' => collect($categories)
                ->map(fn ($keys) => $keys === null ? null : array_values($keys))
                ->filter(fn ($keys) => $keys !== null && $keys !== [])
                ->all(),
            'custom' => self::eigen($custom),
        ];

        $school->update(['rating_settings' => $instellingen]);
    }

    /**
     * Eigen mijlpalen opschonen: een naam is verplicht, en elke mijlpaal krijgt
     * een vaste sleutel die hij daarna houdt — daar hangen de toekenningen aan.
     * Een mijlpaal hernoemen mag dus zonder dat iemand zijn badge kwijtraakt.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    protected static function eigen(mixed $lijst): array
    {
        if (! is_array($lijst)) {
            return [];
        }

        $uit = [];
        $gezien = [];

        foreach ($lijst as $badge) {
            $label = trim((string) ($badge['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $key = (string) ($badge['key'] ?? '');

            if (! str_starts_with($key, self::EIGEN) || strlen($key) > 40 || in_array($key, $gezien, true)) {
                $key = self::EIGEN.Str::lower(Str::random(10));
            }

            $gezien[] = $key;
            $uit[] = [
                'key' => $key,
                'label' => Str::limit($label, 40, ''),
                'description' => Str::limit(trim((string) ($badge['description'] ?? '')), 120, ''),
            ];
        }

        return $uit;
    }

    /**
     * Alleen sleutels die bestaan, in de volgorde van de catalogus.
     *
     * @return list<string>|null
     */
    protected function schoon(mixed $keys): ?array
    {
        if (! is_array($keys)) {
            return null;
        }

        $bekend = array_column(PlayerBadges::catalogue(), 'key');
        $geldig = array_values(array_filter($bekend, fn (string $key) => in_array($key, $keys, true)));

        return $geldig === [] ? null : $geldig;
    }
}
