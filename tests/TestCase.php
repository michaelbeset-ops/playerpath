<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * De props van een Inertia-pagina, voor als je er iets uit nodig hebt
     * (een ondertekende URL bijvoorbeeld) in plaats van er iets over te
     * beweren.
     *
     * @return array<string, mixed>
     */
    protected function inertiaProps(TestResponse $response): array
    {
        return $response->viewData('page')['props'];
    }

    /**
     * De waarde van één widget op het dashboard.
     *
     * Een widget die niet op het dashboard staat, wordt ook niet berekend; die
     * komt hier als null terug, en dat is precies wat een test die daarop
     * controleert wil weten.
     *
     * @param  array<string, mixed>  $props
     */
    protected function widget(array $props, string $key): mixed
    {
        return $props['widgets'][$key] ?? null;
    }

    /**
     * Welke widgets er op dit dashboard staan.
     *
     * @param  iterable<int, array<string, mixed>>  $layout
     * @return list<string>
     */
    protected function widgetKeys(iterable $layout): array
    {
        return array_column(is_array($layout) ? $layout : iterator_to_array($layout), 'key');
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
