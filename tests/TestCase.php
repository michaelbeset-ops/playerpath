<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * De waarde van één kerncijfer op het dashboard.
     *
     * De tegels komen als lijst binnen, in de volgorde die de gebruiker ziet.
     * Een tegel die uitstaat zit er niet in, en dat is precies wat een test
     * die daarop controleert wil weten.
     *
     * @param  iterable<int, array<string, mixed>>  $tiles
     */
    protected function tileValue(iterable $tiles, string $key): mixed
    {
        foreach ($tiles as $tegel) {
            if ($tegel['key'] === $key) {
                return $tegel['value'];
            }
        }

        return null;
    }

    /** @param  iterable<int, array<string, mixed>>  $tiles */
    protected function tileKeys(iterable $tiles): array
    {
        return array_column(is_array($tiles) ? $tiles : iterator_to_array($tiles), 'key');
    }

    /**
     * Alle adressen uit het hoofdmenu, groepen platgeslagen.
     *
     * Het menu staat sinds de balk bovenin in groepen met uitklappen, dus een
     * test die wil weten óf iets in het menu staat moet een niveau dieper
     * kijken. Dat hoort niet in elke test opnieuw te staan.
     *
     * @param  iterable<int, array<string, mixed>>  $nav
     * @return list<string>
     */
    protected function navHrefs(iterable $nav): array
    {
        $hrefs = [];

        foreach ($nav as $groep) {
            if (($groep['href'] ?? null) !== null) {
                $hrefs[] = $groep['href'];
            }

            foreach ($groep['items'] ?? [] as $item) {
                $hrefs[] = $item['href'];
            }
        }

        return $hrefs;
    }
}
