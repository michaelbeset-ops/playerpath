<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
