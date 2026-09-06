<?php

namespace App\Support\Platform;

use App\Enums\Feature;
use App\Models\PlatformLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Beheeracties vastleggen.
 *
 * Eén plek, zodat elke regel dezelfde vorm heeft en er geen actie is die
 * "toevallig" niet gelogd wordt. Wie er iets aan toevoegt hoeft alleen te
 * beslissen wát er in de samenvatting staat.
 *
 * De samenvatting is bewust een leesbare zin en geen code. Als je hier over een
 * jaar iets opzoekt wil je "Betalingen uitgezet" lezen, niet
 * `{"features":{"betalingen":false}}` moeten ontcijferen. De ruwe details
 * staan er daarnaast wel bij, voor als de zin niet genoeg is.
 */
class PlatformAudit
{
    public function __construct(protected Request $request) {}

    /** @param array<string, mixed> $details */
    public function log(string $action, string $summary, ?School $school = null, array $details = []): PlatformLog
    {
        $beheerder = $this->request->user();

        return PlatformLog::create([
            'admin_id' => $beheerder?->id,
            'school_id' => $school?->id,
            'admin_email' => $beheerder?->email ?? 'onbekend',
            // Als tekst erbij: een regel moet leesbaar blijven nadat de school
            // verwijderd is, en juist die regel wil je later terugvinden.
            'school_name' => $school?->name,
            'action' => $action,
            'summary' => $summary,
            'details' => $details === [] ? null : $details,
            'ip_address' => $this->request->ip(),
        ]);
    }

    /**
     * Welke functies er zijn veranderd, in leesbare vorm.
     *
     * @param  array<string, bool>  $voor
     * @param  array<string, bool>  $na
     * @return array{summary: string, details: array<string, mixed>}|null
     */
    public function describeFeatureChange(array $voor, array $na): ?array
    {
        $aan = [];
        $uit = [];

        foreach ($na as $sleutel => $waarde) {
            if (($voor[$sleutel] ?? true) === $waarde) {
                continue;
            }

            $label = Feature::tryFrom($sleutel)?->label() ?? $sleutel;
            $waarde ? $aan[] = $label : $uit[] = $label;
        }

        if ($aan === [] && $uit === []) {
            return null;
        }

        $delen = [];

        if ($uit !== []) {
            $delen[] = implode(' en ', $uit).' uitgezet';
        }

        if ($aan !== []) {
            $delen[] = implode(' en ', $aan).' aangezet';
        }

        return [
            'summary' => ucfirst(implode(', ', $delen)),
            'details' => ['aan' => $aan, 'uit' => $uit],
        ];
    }

    /** Een korte omschrijving van een gebruiker, voor in het logboek. */
    public function describeUser(User $user): string
    {
        return $user->name.' ('.$user->email.')';
    }
}
