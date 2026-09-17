<?php

namespace Tests\Feature\Performance;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Een school met duizend spelers en twintigduizend rekeningen.
 *
 * De lijsten komen per pagina met "Meer laden", de totalen blijven over
 * alles gaan, en het aantal databasevragen groeit niet mee met het aantal
 * rijen.
 */
class LargeListsTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);
    }

    /** Wat de knop "Meer laden" stuurt: een partial reload met alleen deze lijst. */
    protected function laadMeer(User $user, string $url, string $component, array $props): TestResponse
    {
        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => $component,
            'X-Inertia-Partial-Data' => implode(',', $props),
        ];

        $versie = app(HandleInertiaRequests::class)->version(Request::create('/'));

        if ($versie) {
            $headers['X-Inertia-Version'] = $versie;
        }

        return $this->actingAs($user)->get($url, $headers)->assertOk();
    }

    protected function speler(string $achternaam, array $velden = []): Player
    {
        return Player::factory()->for($this->school)->create(['last_name' => $achternaam, 'first_name' => 'Kind', ...$velden]);
    }

    // --- Klanten ---

    public function test_klanten_komen_per_vijftig_en_de_tweede_pagina_levert_de_rest(): void
    {
        foreach (range(1, 60) as $i) {
            $this->speler(sprintf('Speler%02d', $i));
        }

        $this->actingAs($this->eigenaar)
            ->get('/clients')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('players', 50)
                ->where('players.0.name', 'Kind Speler01')
                ->where('playersPage.total', 60)
                ->where('playersPage.hasMore', true)
                ->where('playersPage.nextPage', 2)
                // De telling bovenaan blijft het totaal.
                ->where('counts.players', 60)
            );

        $json = $this->laadMeer($this->eigenaar, '/clients?page=2', 'clients/Index', ['players', 'playersPage'])->json();

        $this->assertCount(10, $json['props']['players']);
        $this->assertSame('Kind Speler51', $json['props']['players'][0]['name']);
        $this->assertFalse($json['props']['playersPage']['hasMore']);
        // Inertia plakt deze pagina achter de vorige in plaats van hem te vervangen.
        $this->assertContains('players', $json['mergeProps']);
        $this->assertNotContains('playersPage', $json['mergeProps']);
    }

    public function test_verversen_na_meer_laden_toont_alles_tot_die_pagina(): void
    {
        foreach (range(1, 60) as $i) {
            $this->speler(sprintf('Speler%02d', $i));
        }

        // "Meer laden" zet ?page=2 in het adres; verversen mag de bovenkant
        // van de lijst niet kwijtraken.
        $this->actingAs($this->eigenaar)
            ->get('/clients?page=2')
            ->assertInertia(fn ($page) => $page
                ->has('players', 60)
                ->where('players.0.name', 'Kind Speler01')
                ->where('playersPage.hasMore', false)
            );
    }

    public function test_filters_en_zoeken_blijven_werken_over_de_paginas_heen(): void
    {
        foreach (range(1, 55) as $i) {
            $this->speler(sprintf('Keeper%02d', $i));
        }

        foreach (range(1, 5) as $i) {
            $this->speler(sprintf('Stopper%02d', $i), ['is_active' => false]);
        }

        $this->actingAs($this->eigenaar)
            ->get('/clients?search=keeper')
            ->assertInertia(fn ($page) => $page
                ->has('players', 50)
                ->where('playersPage.total', 55)
            );

        $json = $this->laadMeer($this->eigenaar, '/clients?search=keeper&page=2', 'clients/Index', ['players', 'playersPage'])->json();

        $this->assertCount(5, $json['props']['players']);
        $this->assertSame('Kind Keeper51', $json['props']['players'][0]['name']);

        $this->actingAs($this->eigenaar)
            ->get('/clients?status=inactive')
            ->assertInertia(fn ($page) => $page
                ->has('players', 5)
                ->where('playersPage.total', 5)
                ->where('playersPage.hasMore', false)
            );
    }

    public function test_klanten_doet_niet_meer_queries_bij_meer_spelers(): void
    {
        $groep = Group::factory()->for($this->school)->create();

        $maakSpelers = function (int $aantal) use ($groep) {
            foreach (range(1, $aantal) as $i) {
                $speler = Player::factory()->for($this->school)->create();
                $speler->groups()->attach($groep->id);

                $ouder = User::factory()->for($this->school)->create();
                $ouder->assignRole(Role::Ouder->value);
                $speler->guardians()->attach($ouder->id, ['relationship' => 'moeder']);

                Payment::factory()->for($this->school)->create(['player_id' => $speler->id, 'due_on' => now()->subWeek()]);
            }
        };

        $maakSpelers(5);
        $weinig = $this->telQueries(fn () => $this->actingAs($this->eigenaar)->get('/clients')->assertOk());

        $maakSpelers(55);
        $veel = $this->telQueries(fn () => $this->actingAs($this->eigenaar)->get('/clients')->assertOk());

        $this->assertSame($weinig, $veel, "Klanten deed {$weinig} queries bij 5 spelers en {$veel} bij 60.");
    }

    // --- Betalingen ---

    public function test_betalingen_komen_per_honderd_en_de_totalen_gaan_over_alles(): void
    {
        $speler = $this->speler('Jansen');

        foreach (range(1, 130) as $i) {
            Payment::factory()->for($this->school)->create([
                'player_id' => $speler->id,
                'amount_cents' => 1000,
                // Een paar dagen, zodat een dag over de paginagrens loopt.
                'due_on' => now()->startOfMonth()->addDays(intdiv($i, 20))->toDateString(),
            ]);
        }

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=all&period=this_month')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('payments', 100)
                ->where('paymentsPage.total', 130)
                ->where('paymentsPage.hasMore', true)
                ->where('totals.count', 130)
                ->where('totals.total', '€ 1.300,00')
            );

        $eerste = $this->actingAs($this->eigenaar)->get('/payments?tab=all&period=this_month');
        $idsEerste = collect($this->inertiaProps($eerste)['payments'])->pluck('id');

        $json = $this->laadMeer($this->eigenaar, '/payments?tab=all&period=this_month&page=2', 'billing/Payments', ['payments', 'paymentsPage'])->json();
        $idsTweede = collect($json['props']['payments'])->pluck('id');

        $this->assertCount(30, $idsTweede);
        // Samen precies alle rekeningen, zonder dubbele.
        $this->assertCount(130, $idsEerste->merge($idsTweede)->unique());
        // De volgorde loopt door: de tweede pagina begint waar de eerste ophield.
        $this->assertLessThanOrEqual(
            $this->inertiaProps($eerste)['payments'][99]['group_key'],
            $json['props']['payments'][0]['group_key'],
        );
        $this->assertContains('payments', $json['mergeProps']);
    }

    public function test_zoeken_in_betalingen_neemt_procent_en_underscore_letterlijk(): void
    {
        $speler = $this->speler('Jansen');

        Payment::factory()->for($this->school)->create(['player_id' => $speler->id, 'description' => '100% korting']);
        Payment::factory()->for($this->school)->create(['player_id' => $speler->id, 'description' => '1000 euro kamp']);
        Payment::factory()->for($this->school)->create(['player_id' => $speler->id, 'description' => 'kamp_zomer']);
        Payment::factory()->for($this->school)->create(['player_id' => $speler->id, 'description' => 'kampXzomer']);

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=all&period=all&search='.urlencode('100%'))
            ->assertInertia(fn ($page) => $page
                ->has('payments', 1)
                ->where('payments.0.description', '100% korting')
                ->where('totals.count', 1)
            );

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=all&period=all&search=kamp_')
            ->assertInertia(fn ($page) => $page
                ->has('payments', 1)
                ->where('payments.0.description', 'kamp_zomer')
            );
    }

    // --- Abonnementen ---

    public function test_abonnementen_komen_per_vijftig(): void
    {
        foreach (range(1, 55) as $i) {
            Subscription::factory()->for($this->school)->create([
                'player_id' => $this->speler('Abo'.$i)->id,
                'starts_on' => now()->subDays($i)->toDateString(),
            ]);
        }

        $this->actingAs($this->eigenaar)
            ->get('/subscriptions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('subscriptions', 50)
                ->where('subscriptionsPage.total', 55)
                ->where('subscriptions.0.player', 'Kind Abo1')
            );

        $json = $this->laadMeer($this->eigenaar, '/subscriptions?page=2', 'billing/Subscriptions', ['subscriptions', 'subscriptionsPage'])->json();

        $this->assertCount(5, $json['props']['subscriptions']);
        $this->assertSame('Kind Abo51', $json['props']['subscriptions'][0]['player']);
    }

    // --- De ouder ---

    protected function ouderMetKinderen(int $aantal): User
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $product = Product::factory()->for($this->school)->create();

        foreach (range(1, $aantal) as $i) {
            $kind = $this->speler('Kind'.$i);
            $ouder->children()->attach($kind->id);

            Subscription::factory()->for($this->school)->create(['player_id' => $kind->id, 'product_id' => $product->id]);
        }

        return $ouder;
    }

    public function test_een_ouder_ziet_oudere_betalingen_met_meer_laden(): void
    {
        $ouder = $this->ouderMetKinderen(1);
        $kind = $ouder->children()->first();

        foreach (range(1, 30) as $i) {
            Payment::factory()->for($this->school)->create([
                'player_id' => $kind->id,
                'description' => 'Termijn '.$i,
                'due_on' => now()->subMonths($i)->toDateString(),
            ]);
        }

        $this->actingAs($ouder)
            ->get('/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('payments', 24)
                ->where('payments.0.description', 'Termijn 1')
                ->where('paymentsPage.total', 30)
                ->where('paymentsPage.hasMore', true)
            );

        $json = $this->laadMeer($ouder, '/billing?page=2', 'billing/MyBilling', ['payments', 'paymentsPage'])->json();

        $this->assertCount(6, $json['props']['payments']);
        $this->assertSame('Termijn 25', $json['props']['payments'][0]['description']);
    }

    public function test_mijn_abonnement_doet_niet_per_kind_een_query(): void
    {
        $een = $this->ouderMetKinderen(1);
        $vier = $this->ouderMetKinderen(4);

        $weinig = $this->telQueries(fn () => $this->actingAs($een)->get('/billing')->assertOk());
        $veel = $this->telQueries(fn () => $this->actingAs($vier)->get('/billing')->assertOk());

        $this->assertSame($weinig, $veel, "Mijn abonnement deed {$weinig} queries bij 1 kind en {$veel} bij 4.");
    }

    // --- Inschrijvingen ---

    public function test_open_inschrijvingen_staan_er_helemaal_en_afgehandelde_per_pagina(): void
    {
        Enrollment::factory()->count(3)->for($this->school)->create(['status' => EnrollmentStatus::AwaitingApproval]);
        Enrollment::factory()->count(35)->for($this->school)->create(['status' => EnrollmentStatus::Cancelled]);

        $this->actingAs($this->eigenaar)
            ->get('/enrollments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pending', 3)
                ->has('handled', 30)
                ->where('handledPage.total', 35)
                ->where('handledPage.hasMore', true)
            );

        $json = $this->laadMeer($this->eigenaar, '/enrollments?page=2', 'enrollments/Index', ['handled', 'handledPage'])->json();

        $this->assertCount(5, $json['props']['handled']);
        $this->assertFalse($json['props']['handledPage']['hasMore']);
    }

    protected function telQueries(callable $doe): int
    {
        // Eén keer opwarmen: de eerste aanvraag vult caches (rollen, rechten)
        // die niets met het aantal rijen te maken hebben.
        $doe();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $doe();

        $aantal = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $aantal;
    }
}
