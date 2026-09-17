<?php

namespace App\Support\Pagination;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\MergeProp;
use Inertia\Support\Header;

/**
 * Lange lijsten in stukken, met een knop "Meer laden".
 *
 * Een school met duizend spelers of twintigduizend rekeningen stuurt die niet
 * in één keer naar een telefoon. Daarom gaat een lijst per pagina, en plakt
 * het scherm de volgende pagina eronder (Inertia's merge-props).
 *
 * Twee manieren van ophalen, en dat verschil is bewust:
 *
 * - **"Meer laden"** is een partial reload met alleen deze lijst. Dan komt
 *   precies pagina N terug, en Inertia plakt die achter wat er al stond.
 * - **Elke andere visit** (de pagina openen, verversen, een filter wijzigen)
 *   krijgt pagina 1 tot en met N in één keer. "Meer laden" zet `?page=3` in
 *   het adres; zonder dit zou verversen alleen pagina 3 tonen en lijkt de
 *   bovenkant van de lijst verdwenen.
 *
 * De meta (totaal, of er meer is) is een gewone prop naast de lijst. Die wordt
 * vervangen, niet samengevoegd.
 */
class LoadMore
{
    /** Verversen met `?page=999` mag geen twintigduizend rijen in één keer opleveren. */
    public const MAX_PAGINAS_BIJ_VERVERSEN = 10;

    /**
     * @param  callable(mixed): mixed  $vorm  Zet één model om naar wat het scherm krijgt.
     * @return array{0: MergeProp, 1: array{page: int, perPage: int, total: int, shown: int, hasMore: bool, nextPage: int|null}}
     */
    public static function paginate(Builder $query, Request $request, string $prop, int $perPage, callable $vorm): array
    {
        [$rijen, $meta] = self::slice($query, $request, $prop, $perPage);

        return [Inertia::merge($rijen->map($vorm)->values()), $meta];
    }

    /**
     * Hetzelfde, maar met de modellen zelf; voor een scherm dat eerst nog iets
     * over de hele pagina moet opzoeken voordat het de rijen vormgeeft.
     *
     * @return array{0: Collection<int, \Illuminate\Database\Eloquent\Model>, 1: array{page: int, perPage: int, total: int, shown: int, hasMore: bool, nextPage: int|null}}
     */
    public static function slice(Builder $query, Request $request, string $prop, int $perPage): array
    {
        $pagina = max(1, $request->integer('page', 1));
        $totaal = (clone $query)->toBase()->getCountForPagination();

        if (self::laadtMeer($request, $prop)) {
            $vanaf = ($pagina - 1) * $perPage;
            $aantal = $perPage;
        } else {
            $pagina = min($pagina, self::MAX_PAGINAS_BIJ_VERVERSEN);
            $vanaf = 0;
            $aantal = $pagina * $perPage;
        }

        $rijen = (clone $query)->offset($vanaf)->limit($aantal)->get();

        $getoond = min($totaal, $vanaf + $rijen->count());
        $meer = $getoond < $totaal;

        return [
            $rijen,
            [
                'page' => $pagina,
                'perPage' => $perPage,
                'total' => $totaal,
                'shown' => $getoond,
                'hasMore' => $meer,
                'nextPage' => $meer ? $pagina + 1 : null,
            ],
        ];
    }

    /** Is dit de knop "Meer laden" voor precies deze lijst? */
    public static function laadtMeer(Request $request, string $prop): bool
    {
        if (! $request->header(Header::PARTIAL_COMPONENT)) {
            return false;
        }

        $alleen = array_filter(explode(',', (string) $request->header(Header::PARTIAL_ONLY, '')));

        return in_array($prop, $alleen, strict: true);
    }
}
