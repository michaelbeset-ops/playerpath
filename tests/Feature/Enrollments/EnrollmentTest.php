<?php

namespace Tests\Feature\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingStatus;
use App\Enums\ParticipationStatus;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Participation;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\InschrijvingGoedgekeurd;
use App\Notifications\NieuweInschrijving;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['slug' => 'keepersschool-rob']);
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);
    }

    /** Een aangesloten betaalprovider, zodat online betalen bestaat. */
    protected function metBetaalprovider(): FakeGateway
    {
        $gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $gateway);

        return $gateway;
    }

    /** @return array<string, mixed> */
    protected function formulier(array $overschrijf = []): array
    {
        return array_merge([
            'first_name' => 'Sem',
            'last_name' => 'de Vries',
            'date_of_birth' => '2013-04-12',
            'position' => 'keeper',
            'guardian_name' => 'Marieke de Vries',
            'guardian_email' => 'marieke@voorbeeld.nl',
            'guardian_phone' => '0612345678',
            'relationship' => 'moeder',
            'payment_method' => 'cash',
            'privacy' => true,
        ], $overschrijf);
    }

    public function test_het_formulier_is_openbaar_en_toont_de_tarieven(): void
    {
        app(Tenancy::class)->set($this->school);
        Product::factory()->for($this->school)->create(['name' => 'Keeperstraining', 'amount_cents' => 2750]);
        app(Tenancy::class)->forget();

        $this->get('/inschrijven/keepersschool-rob')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('enrollments/Public')
                ->where('school.name', $this->school->name)
                ->count('products', 1)
                ->where('products.0.amount', '€ 27,50')
                // Geen app-props op een openbare pagina.
                ->where('auth.user', null)
                ->where('nav', [])
            );
    }

    public function test_een_inactieve_of_onbekende_school_geeft_404(): void
    {
        $this->school->update(['is_active' => false]);

        $this->get('/inschrijven/keepersschool-rob')->assertNotFound();
        $this->get('/inschrijven/bestaat-niet')->assertNotFound();
    }

    public function test_een_ouder_kan_zijn_kind_inschrijven_en_de_eigenaar_krijgt_bericht(): void
    {
        Notification::fake();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier())
            ->assertRedirect('/inschrijven/keepersschool-rob')
            ->assertSessionHas('enrollment_submitted', true);

        $this->assertDatabaseHas('enrollments', [
            'school_id' => $this->school->id,
            'first_name' => 'Sem',
            'guardian_email' => 'marieke@voorbeeld.nl',
            'status' => 'pending',
        ]);

        // Er is nog geen speler: de eigenaar keurt eerst goed.
        $this->assertDatabaseCount('players', 0);

        Notification::assertSentTo($this->eigenaar, NieuweInschrijving::class);
    }

    public function test_zonder_akkoord_wordt_het_formulier_geweigerd(): void
    {
        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['privacy' => false]))
            ->assertSessionHasErrors('privacy');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_een_tarief_van_een_andere_school_wordt_geweigerd(): void
    {
        $andere = School::factory()->create();
        $vreemdPlan = Product::factory()->for($andere)->create();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['product_id' => $vreemdPlan->id]))
            ->assertSessionHasErrors('product_id');
    }

    public function test_goedkeuren_maakt_speler_ouder_en_abonnement_aan(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);
        $product = Product::factory()->for($this->school)->create(['amount_cents' => 2750]);
        $inschrijving = Enrollment::factory()->for($this->school)->create([
            'first_name' => 'Sem', 'last_name' => 'de Vries',
            'guardian_name' => 'Marieke', 'guardian_email' => 'marieke@voorbeeld.nl',
            'relationship' => 'moeder', 'product_id' => $product->id,
        ]);

        $this->actingAs($this->eigenaar)
            ->post('/enrollments/'.$inschrijving->id.'/approve')
            ->assertRedirect();

        $speler = Player::where('first_name', 'Sem')->firstOrFail();
        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();

        $this->assertSame($this->school->id, $speler->school_id);
        $this->assertSame($this->school->id, $ouder->school_id);
        $this->assertTrue($ouder->isOuder());
        $this->assertTrue($speler->guardians->contains($ouder));

        $abonnement = Subscription::firstOrFail();
        $this->assertSame($speler->id, $abonnement->player_id);
        $this->assertSame(2750, $abonnement->amount_cents);

        $inschrijving->refresh();
        $this->assertSame(EnrollmentStatus::Approved, $inschrijving->status);
        $this->assertSame($speler->id, $inschrijving->player_id);

        // De ouder kiest zelf een wachtwoord.
        Notification::assertSentTo($ouder, ResetPassword::class);
    }

    public function test_goedkeuren_koppelt_een_bestaande_ouder_van_dezelfde_school(): void
    {
        Notification::fake();

        $bestaand = User::factory()->for($this->school)->create(['email' => 'marieke@voorbeeld.nl']);
        $bestaand->assignRole(Role::Ouder->value);

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::factory()->for($this->school)->create(['guardian_email' => 'marieke@voorbeeld.nl']);

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/approve');

        $this->assertSame(1, User::where('email', 'marieke@voorbeeld.nl')->count());
        $this->assertTrue($bestaand->children()->exists());

        // Geen wachtwoordmail: dit account bestond al en heeft er een.
        Notification::assertNotSentTo($bestaand, ResetPassword::class);
        // Wel het bericht dat de inschrijving rond is.
        Notification::assertSentTo($bestaand, InschrijvingGoedgekeurd::class);
    }

    public function test_een_ouder_van_een_andere_school_blokkeert_de_goedkeuring(): void
    {
        $andere = School::factory()->create();
        User::factory()->for($andere)->create(['email' => 'marieke@voorbeeld.nl']);

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::factory()->for($this->school)->create(['guardian_email' => 'marieke@voorbeeld.nl']);

        $this->actingAs($this->eigenaar)
            ->post('/enrollments/'.$inschrijving->id.'/approve')
            ->assertSessionHasErrors('enrollment');

        $this->assertDatabaseCount('players', 0);
        $this->assertSame(EnrollmentStatus::Pending, $inschrijving->refresh()->status);
    }

    public function test_afwijzen_maakt_niets_aan(): void
    {
        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/decline')->assertRedirect();

        $this->assertSame(EnrollmentStatus::Declined, $inschrijving->refresh()->status);
        $this->assertDatabaseCount('players', 0);
    }

    public function test_alleen_de_eigenaar_ziet_en_beoordeelt_inschrijvingen(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::factory()->for($this->school)->create();

        $this->actingAs($trainer)->get('/enrollments')->assertForbidden();
        $this->actingAs($trainer)->post('/enrollments/'.$inschrijving->id.'/approve')->assertForbidden();
    }

    public function test_de_inbox_toont_alleen_de_eigen_school(): void
    {
        $andere = School::factory()->create();
        app(Tenancy::class)->set($andere);
        Enrollment::factory()->for($andere)->create(['first_name' => 'Vreemd']);

        app(Tenancy::class)->set($this->school);
        Enrollment::factory()->for($this->school)->create(['first_name' => 'Eigen']);

        $this->actingAs($this->eigenaar)
            ->get('/enrollments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('enrollments/Index')
                ->count('pending', 1)
                ->where('pending.0.child_name', fn ($naam) => str_starts_with($naam, 'Eigen'))
                ->where('formUrl', route('enroll.show', $this->school))
            );
    }

    public function test_zonder_betaalprovider_kun_je_alleen_contant_kiezen(): void
    {
        $this->get('/inschrijven/keepersschool-rob')
            ->assertInertia(fn ($page) => $page
                ->count('paymentOptions', 1)
                ->where('paymentOptions.0.value', 'cash')
            );

        // En online is dan ook niet stiekem in te sturen: dat zou een wens
        // opslaan die niemand kan inlossen.
        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['payment_method' => 'ideal']))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_met_een_betaalprovider_kun_je_online_of_incasso_kiezen(): void
    {
        $this->metBetaalprovider();

        $this->get('/inschrijven/keepersschool-rob')
            ->assertInertia(fn ($page) => $page
                ->count('paymentOptions', 3)
                ->where('paymentOptions.0.value', 'cash')
                ->where('paymentOptions.1.value', 'ideal')
                // Incasso hoort bij iets dat doorloopt; het scherm verbergt hem
                // bij een kamp of een losse training.
                ->where('paymentOptions.2.value', 'directdebit')
                ->where('paymentOptions.2.subscription_only', true)
            );

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['payment_method' => 'ideal']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('enrollments', ['payment_method' => 'ideal']);
    }

    public function test_een_los_product_wordt_een_aankoop_en_geen_abonnement(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);

        $kamp = Product::factory()->for($this->school)->create([
            'name' => 'Zomerkamp',
            'type' => ProductType::Kamp,
            'amount_cents' => 9500,
        ]);

        $inschrijving = Enrollment::factory()->for($this->school)->create([
            'product_id' => $kamp->id,
            'payment_method' => 'cash',
        ]);

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/approve');

        // Een kamp is één keer afnemen; als abonnement zou het elke maand een
        // nieuwe rekening opleveren.
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseHas('purchases', ['name' => 'Zomerkamp', 'amount_cents' => 9500]);
        $this->assertDatabaseHas('payments', ['amount_cents' => 9500, 'status' => 'open', 'method' => 'cash']);
    }

    public function test_bij_online_betalen_zit_er_een_betaallink_in_de_mail(): void
    {
        Notification::fake();
        $this->metBetaalprovider();

        app(Tenancy::class)->set($this->school);

        $product = Product::factory()->for($this->school)->create([
            'name' => 'Losse training',
            'type' => ProductType::LosseTraining,
            'amount_cents' => 1500,
        ]);

        $inschrijving = Enrollment::factory()->for($this->school)->create([
            'guardian_email' => 'marieke@voorbeeld.nl',
            'product_id' => $product->id,
            'payment_method' => 'ideal',
        ]);

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/approve');

        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();

        Notification::assertSentTo($ouder, InschrijvingGoedgekeurd::class, function (InschrijvingGoedgekeurd $melding) use ($ouder) {
            $mail = $melding->toMail($ouder);

            // De knop wijst naar de ondertekende betaalpagina: een net
            // ingeschreven ouder heeft nog geen wachtwoord.
            $this->assertStringContainsString('/betalen/', (string) $mail->actionUrl);
            $this->assertStringContainsString('signature=', (string) $mail->actionUrl);

            return true;
        });
    }

    public function test_bij_contant_staat_er_geen_betaalknop_in_de_mail(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);

        $product = Product::factory()->for($this->school)->create([
            'type' => ProductType::LosseTraining,
            'amount_cents' => 1500,
        ]);

        $inschrijving = Enrollment::factory()->for($this->school)->create([
            'guardian_email' => 'marieke@voorbeeld.nl',
            'product_id' => $product->id,
            'payment_method' => 'cash',
        ]);

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/approve');

        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();

        Notification::assertSentTo($ouder, InschrijvingGoedgekeurd::class, function (InschrijvingGoedgekeurd $melding) use ($ouder) {
            $mail = $melding->toMail($ouder);

            // Een betaalknop zou een gezin twee keer laten betalen.
            $this->assertNull($mail->actionUrl);
            $this->assertStringContainsString('bij de school zelf', implode(' ', $mail->introLines));

            return true;
        });
    }

    // --- De aanmeldpagina zelf ---

    public function test_de_aanmeldpagina_toont_alleen_aanbod_waar_je_op_kunt(): void
    {
        app(Tenancy::class)->set($this->school);

        $blok = Product::factory()->for($this->school)->blok(capaciteit: 2)->create([
            'name' => 'Keepersblok',
            'location' => 'Sportpark De Vliert',
            'min_age' => 8,
            'max_age' => 12,
        ]);

        // Vol, gesloten en onzichtbaar horen er niet te staan: iets tonen waar
        // je je niet op kunt aanmelden is een dode klik.
        $vol = Product::factory()->for($this->school)->blok(capaciteit: 1)->create(['name' => 'Vol blok']);
        Participation::create([
            'product_id' => $vol->id,
            'player_id' => Player::factory()->for($this->school)->create()->id,
            'status' => ParticipationStatus::Confirmed,
        ]);

        Product::factory()->for($this->school)->blok()->create(['name' => 'Gesloten blok', 'status' => OfferingStatus::Gesloten]);
        Product::factory()->for($this->school)->blok()->create(['name' => 'Verborgen blok', 'is_active' => false]);

        app(Tenancy::class)->forget();

        $this->get('/inschrijven/keepersschool-rob')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('enrollments/Public')
                ->count('products', 1)
                ->where('products.0.id', $blok->id)
                ->where('products.0.name', 'Keepersblok')
                ->where('products.0.location', 'Sportpark De Vliert')
                ->where('products.0.min_age', 8)
                ->where('products.0.spots_left', 2)
            );
    }

    public function test_een_link_vanaf_de_eigen_website_opent_meteen_dat_aanbod(): void
    {
        app(Tenancy::class)->set($this->school);
        $blok = Product::factory()->for($this->school)->blok()->create();
        app(Tenancy::class)->forget();

        $this->get('/inschrijven/keepersschool-rob?aanbod='.$blok->id)
            ->assertInertia(fn ($page) => $page->where('selected', $blok->id));
    }

    public function test_de_aanmeldpagina_mag_in_een_iframe_van_de_school(): void
    {
        // Bedoeld om op de eigen website te zetten. Er staat niets achter een
        // sessie, dus clickjacking valt hier niets mee te winnen.
        $this->get('/inschrijven/keepersschool-rob')->assertHeaderMissing('X-Frame-Options');

        // De rest van de app blijft dicht.
        $this->actingAs($this->eigenaar)->get('/dashboard')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_inschrijven_op_een_vol_blok_wordt_geweigerd(): void
    {
        app(Tenancy::class)->set($this->school);

        $vol = Product::factory()->for($this->school)->blok(capaciteit: 1)->create();
        Participation::create([
            'product_id' => $vol->id,
            'player_id' => Player::factory()->for($this->school)->create()->id,
            'status' => ParticipationStatus::Confirmed,
        ]);

        app(Tenancy::class)->forget();

        // Iemand met de pagina in een tabblad weet niet dat het inmiddels vol
        // is; dat hoort de server te zeggen.
        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['product_id' => $vol->id]))
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_een_kind_buiten_de_leeftijdsgrens_wordt_geweigerd(): void
    {
        app(Tenancy::class)->set($this->school);
        $blok = Product::factory()->for($this->school)->blok()->create(['min_age' => 10, 'max_age' => 14]);
        app(Tenancy::class)->forget();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier([
            'product_id' => $blok->id,
            'date_of_birth' => now()->subYears(7)->toDateString(),
        ]))->assertSessionHasErrors('date_of_birth');

        $this->assertDatabaseCount('enrollments', 0);

        // Een kind dat er wél bij past komt er gewoon door.
        $this->post('/inschrijven/keepersschool-rob', $this->formulier([
            'product_id' => $blok->id,
            'date_of_birth' => now()->subYears(11)->toDateString(),
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('enrollments', 1);
    }
}
