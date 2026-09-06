<?php

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Support\Exports\ExportWriter;
use App\Support\Exports\PlayerDataExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inzageverzoek: alles wat de school over één speler bewaart, in één werkmap.
 *
 * Bestaat omdat een ouder daar recht op heeft en het anders neerkomt op met de
 * hand schermen overtypen. De knop staat op de pagina van de speler zelf, want
 * dat is waar de vraag gesteld wordt.
 *
 * Alleen de eigenaar. Een trainer beslist niet welke gegevens van andermans
 * kind het gebouw uit gaan.
 */
class PlayerDataController extends Controller
{
    public function __construct(protected ExportWriter $writer) {}

    public function __invoke(Request $request, Player $player): StreamedResponse
    {
        abort_unless($request->user()->isEigenaar(), 403);
        $this->authorize('view', $player);

        return $this->writer->download(new PlayerDataExport($player), 'xlsx', []);
    }
}
