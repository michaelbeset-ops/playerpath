<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Een tekst waarvoor een ouder bij het inschrijven toestemming geeft.
 *
 * Vier soorten, elk met een eigen tekst per school en een **versie**. De
 * versie is de reden dat dit een tabel is en geen vinkje in de instellingen:
 * een toestemming hoort bij de tekst zoals die was op het moment van tekenen.
 * Verandert de school haar gedragsregels, dan gaat de versie omhoog en weet je
 * precies wie de nieuwe tekst nog niet heeft gezien.
 */
class ConsentDocument extends Model
{
    use BelongsToSchool, HasFactory;

    /** De soorten toestemming, met een standaardtekst als de school er geen heeft. */
    public const SOORTEN = [
        'avg' => [
            'title' => 'Privacy (AVG)',
            'body' => 'De school bewaart de gegevens van je kind om trainingen te plannen, de ontwikkeling bij te houden en je te bereiken. Ze worden niet gedeeld met anderen en je kunt ze altijd opvragen of laten verwijderen.',
        ],
        'beeldrecht' => [
            'title' => 'Foto en video',
            'body' => 'De school mag foto’s en video’s van de training gebruiken op haar website en sociale media. Je kunt dit altijd intrekken.',
        ],
        'gedragsregels' => [
            'title' => 'Gedragsregels',
            'body' => 'Wij gaan respectvol met elkaar om, op en naast het veld. Aanwijzingen van de trainer worden opgevolgd.',
        ],
        'medisch' => [
            'title' => 'Medische bijzonderheden',
            'body' => 'Je geeft bijzonderheden door die de trainer moet weten, zoals allergieën of blessures, en meldt wijzigingen.',
        ],
    ];

    protected $fillable = [
        'key',
        'title',
        'body',
        'version',
        'required',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'required' => 'boolean',
        ];
    }

    /**
     * Alle vier de soorten voor deze school, aangevuld met de standaardtekst
     * waar de school nog niets heeft vastgelegd.
     *
     * @return list<array{key: string, title: string, body: string, version: int, required: bool, saved: bool}>
     */
    public static function allForSchool(): array
    {
        $opgeslagen = self::query()->get()->keyBy('key');

        return collect(self::SOORTEN)->map(function (array $standaard, string $key) use ($opgeslagen) {
            $document = $opgeslagen->get($key);

            return [
                'key' => $key,
                'title' => $document?->title ?? $standaard['title'],
                'body' => $document?->body ?? $standaard['body'],
                'version' => $document?->version ?? 1,
                'required' => $document?->required ?? ($key === 'avg'),
                'saved' => $document !== null,
            ];
        })->values()->all();
    }

    /**
     * Tekst en verplichting vastleggen. Een andere tekst is een nieuwe versie.
     */
    public static function put(string $key, string $title, string $body, bool $required): self
    {
        $document = self::query()->where('key', $key)->first();

        if ($document === null) {
            return self::create([
                'key' => $key,
                'title' => $title,
                'body' => $body,
                'version' => 1,
                'required' => $required,
            ]);
        }

        $nieuweTekst = $document->body !== $body || $document->title !== $title;

        $document->update([
            'title' => $title,
            'body' => $body,
            'required' => $required,
            'version' => $nieuweTekst ? $document->version + 1 : $document->version,
        ]);

        return $document;
    }
}
