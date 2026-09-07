<?php

namespace Tests\Feature\Offerings;

use App\Enums\BillingType;
use App\Enums\ParticipationStatus;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Location;
use App\Models\Participation;
use App\Models\PaymentOption;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Het aanbod van een school: blokken, kampen, doorlopende training.
 *
 * De kern van dit onderdeel is dat een blok een gewone groep met gewone
 * trainingen oplevert. Daardoor blijven aanwezigheid, rapporten en de agenda
 * werken zoals ze altijd al deden — dat wordt hier dus ook getest.
 */
class OfferingTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Location $locatie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);

        $this->locatie = Location::create(['name' => 'Sportpark De Vliert', 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    /** Een aanmelding van een nieuwe ouder voor dit aanbod, met de standaard betaalvorm. */
    protected function aanmelding(Product $aanbod): array
    {
        return [
            'children' => [[
                'first_name' => 'Sem', 'last_name' => 'de Vries', 'date_of_birth' => '2016-04-12', 'position' => 'keeper',
                'product_id' => $aanbod->id, 'payment_option_id' => PaymentOption::withoutSchoolScope()->where('product_id', $aanbod->id)->where('is_default', true)->value('id'),
            ]],
            'guardian_name' => 'Marieke de Vries', 'guardian_email' => 'marieke@voorbeeld.nl', 'password' => 'wachtwoord123',
            'consents' => ['avg'], 'payment_method' => 'cash',
        ];
    }

    protected function blokGegevens(array $overschrijf = []): array
    {
        return array_merge([
            'name' => 'Keepersblok najaar',
            'description' => 'Zes weken keeperstraining',
            'type' => ProductType::Blok->value,
            'billing_type' => BillingType::Eenmalig->value,
            'amount' => '120,00',
            'vat_rate' => 21,
            'starts_on' => '2026-10-05', // maandag
            'ends_on' => '2026-11-09',   // zes maandagen later
            'capacity' => 12,
            'min_participants' => 6,
            'min_age' => 8,
            'max_age' => 12,
            'location_id' => $this->locatie->id,
            'status' => 'open',
            'stops_at_end' => true,
            'is_active' => true,
            'weekdays' => [1],
            'starts_at' => '18:00',
            'ends_at' => '19:30',
        ], $overschrijf);
    }

    public function test_een_blok_levert_een_groep_met_trainingen_op(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21'));

        $trainer = User::factory()->for($this->school)->create(['name' => 'Piet Trainer']);
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($this->eigenaar)
            ->post('/aanbod', $this->blokGegevens(['trainers' => [$trainer->id]]))
            ->assertRedirect('/aanbod');

        $blok = Product::firstWhere('name', 'Keepersblok najaar');

        $this->assertSame(ProductType::Blok, $blok->type);
        $this->assertSame(12000, $blok->amount_cents);
        $this->assertSame(12, $blok->capacity);

        // De groep is de knoop met de rest van de app.
        $groep = Group::firstWhere('product_id', $blok->id);
        $this->assertNotNull($groep);
        $this->assertSame('Keepersblok najaar', $groep->name);

        // Zes maandagen, met de trainer en de locatie erbij.
        $trainingen = Training::where('group_id', $groep->id)->orderBy('starts_at')->get();
        $this->assertCount(6, $trainingen);
        $this->assertSame('2026-10-05 18:00', $trainingen->first()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-09 19:30', $trainingen->last()->ends_at->format('Y-m-d H:i'));
        $this->assertSame('Sportpark De Vliert', $trainingen->first()->location);
        $this->assertTrue($trainingen->first()->trainers->contains('id', $trainer->id));
    }

    public function test_een_kamp_krijgt_de_dagen_die_je_invult(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21'));

        $this->actingAs($this->eigenaar)->post('/aanbod', $this->blokGegevens([
            'name' => 'Herfstkamp',
            'type' => ProductType::Kamp->value,
            'weekdays' => [],
            'dates' => ['2026-10-19', '2026-10-20', '2026-10-21'],
            'starts_on' => '2026-10-19',
            'ends_on' => '2026-10-21',
        ]))->assertSessionHasNoErrors();

        $kamp = Product::firstWhere('name', 'Herfstkamp');

        $this->assertCount(3, Training::where('group_id', $kamp->group->id)->get());
    }

    public function test_een_blok_zonder_datums_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/aanbod', $this->blokGegevens(['starts_on' => null, 'ends_on' => null]))
            ->assertSessionHasErrors(['starts_on', 'ends_on']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_opnieuw_roosteren_laat_geweest_trainingen_staan(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20'));

        // Een blok dat al loopt: twee maandagen geweest, vier te gaan.
        $this->actingAs($this->eigenaar)->post('/aanbod', $this->blokGegevens());

        $blok = Product::firstWhere('name', 'Keepersblok najaar');
        $groep = $blok->group;

        // Een training in het verleden erbij, alsof die al geweest is.
        $geweest = Training::factory()->for($this->school)->for($groep)->create([
            'starts_at' => CarbonImmutable::parse('2026-10-05 18:00'),
            'ends_at' => CarbonImmutable::parse('2026-10-05 19:30'),
        ]);

        $this->actingAs($this->eigenaar)->put('/aanbod/'.$blok->id, $this->blokGegevens(['ends_at' => '20:00']));

        // Wat geweest is draagt aanwezigheid; dat gooi je niet weg.
        $this->assertDatabaseHas('trainings', ['id' => $geweest->id]);
    }

    public function test_de_plekken_worden_geteld_en_niet_opgeslagen(): void
    {
        $blok = Product::factory()->for($this->school)->blok(capaciteit: 2)->create();

        $spelers = Player::factory()->count(2)->for($this->school)->create();

        foreach ($spelers as $speler) {
            Participation::create([
                'product_id' => $blok->id,
                'player_id' => $speler->id,
                'status' => ParticipationStatus::Confirmed,
            ]);
        }

        $this->assertSame(2, $blok->spotsTaken());
        $this->assertTrue($blok->isFull());
        $this->assertFalse($blok->acceptsSignups());

        // Zegt er iemand af, dan is er weer plek — zonder dat iemand een status
        // hoeft bij te werken.
        Participation::where('player_id', $spelers->first()->id)
            ->update(['status' => ParticipationStatus::Cancelled->value]);

        $this->assertSame(1, $blok->fresh()->spotsTaken());
        $this->assertFalse($blok->fresh()->isFull());
    }

    public function test_een_wachtlijstplek_telt_niet_mee(): void
    {
        $blok = Product::factory()->for($this->school)->blok(capaciteit: 1)->create();
        $speler = Player::factory()->for($this->school)->create();

        Participation::create([
            'product_id' => $blok->id,
            'player_id' => $speler->id,
            'status' => ParticipationStatus::Waitlist,
        ]);

        $this->assertSame(0, $blok->spotsTaken());
        $this->assertTrue($blok->acceptsSignups());
    }

    public function test_leeftijdsgrenzen_zeggen_wie_er_bij_past(): void
    {
        $blok = Product::factory()->for($this->school)->blok()->create(['min_age' => 8, 'max_age' => 12]);

        $this->assertFalse($blok->fitsAge(7));
        $this->assertTrue($blok->fitsAge(8));
        $this->assertTrue($blok->fitsAge(12));
        $this->assertFalse($blok->fitsAge(13));

        // Zonder grenzen mag iedereen mee, en zonder geboortedatum weet je het
        // niet — dan weiger je niemand.
        $open = Product::factory()->for($this->school)->blok()->create(['min_age' => null, 'max_age' => null]);
        $this->assertTrue($open->fitsAge(4));
        $this->assertTrue($blok->fitsAge(null));
    }

    public function test_goedkeuren_zet_de_speler_in_het_blok_en_in_de_groep(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-21'));

        $this->actingAs($this->eigenaar)->post('/aanbod', $this->blokGegevens());
        $blok = Product::firstWhere('name', 'Keepersblok najaar');

        app(Tenancy::class)->forget();
        $this->post('/inschrijven/'.$this->school->slug, $this->aanmelding($blok))->assertSessionHasNoErrors();
        app(Tenancy::class)->set($this->school);

        $inschrijving = Enrollment::firstOrFail();
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/approve');

        // Handmatig goedkeuren: eerst betalen, dan doet het kind mee.
        $this->actingAs($this->eigenaar)->patch('/payments/'.$inschrijving->refresh()->order->payments()->firstOrFail()->id, ['status' => 'paid', 'method' => 'cash']);

        $speler = Player::firstOrFail();

        // De deelname draagt de rekening; de groep draagt de trainingen.
        $deelname = Participation::firstOrFail();
        $this->assertSame($blok->id, $deelname->product_id);
        $this->assertSame(ParticipationStatus::Confirmed, $deelname->status);
        $this->assertNotNull($deelname->purchase_id);

        $this->assertTrue($speler->groups->contains('id', $blok->group->id));
        $this->assertSame(1, $blok->fresh()->spotsTaken());
    }

    public function test_een_blok_per_maand_stopt_op_de_einddatum(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-21'));

        $this->actingAs($this->eigenaar)->post('/aanbod', $this->blokGegevens([
            'billing_type' => BillingType::Maandelijks->value,
            'interval' => 'monthly',
            'amount' => '30,00',
            'stops_at_end' => true,
        ]));

        $blok = Product::firstWhere('name', 'Keepersblok najaar');

        app(Tenancy::class)->forget();
        $this->post('/inschrijven/'.$this->school->slug, $this->aanmelding($blok))->assertSessionHasNoErrors();
        app(Tenancy::class)->set($this->school);

        // Een abonnement betaal je niet vooraf: goedkeuren bevestigt meteen.
        $this->actingAs($this->eigenaar)->post('/enrollments/'.Enrollment::firstOrFail()->id.'/approve');

        // Een blok van zes weken dat na afloop blijft doorschrijven is precies
        // waar een ouder boos over wordt.
        $this->assertDatabaseHas('subscriptions', [
            'product_id' => $blok->id,
            'ends_on' => '2026-11-09 00:00:00',
        ]);
    }

    public function test_een_trainer_beheert_het_aanbod_niet(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get('/aanbod/create')->assertForbidden();
        $this->actingAs($trainer)->post('/aanbod', $this->blokGegevens())->assertForbidden();
    }

    public function test_het_oude_adres_wijst_naar_het_aanbod(): void
    {
        $this->actingAs($this->eigenaar)->get('/products')->assertRedirect('/aanbod');
    }
}
