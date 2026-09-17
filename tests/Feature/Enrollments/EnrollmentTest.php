<?php

namespace Tests\Feature\Enrollments;

use App\Actions\Enrollments\SubmitEnrollment;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingStatus;
use App\Enums\ParticipationStatus;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\ConsentDocument;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Participation;
use App\Models\PaymentOption;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Notifications\InschrijvingAfgewezen;
use App\Notifications\InschrijvingGoedgekeurd;
use App\Notifications\InschrijvingOntvangen;
use App\Notifications\NieuweInschrijving;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/**
 * De openbare inschrijfflow: aanbod → kind → account → toestemmingen →
 * betalen → overzicht → bevestigen, en wat er daarna bij de school gebeurt.
 */
class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Product $blok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['slug' => 'keepersschool-rob']);
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);

        $this->blok = Product::factory()->for($this->school)->blok(capaciteit: 10)->create([
            'name' => 'Keepersblok',
            'amount_cents' => 12000,
            'min_age' => 8,
            'max_age' => 12,
        ]);

        app(Tenancy::class)->forget();
    }

    protected function metBetaalprovider(): FakeGateway
    {
        $gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $gateway);

        return $gateway;
    }

    /** @return array<string, mixed> */
    protected function formulier(array $overschrijf = [], array $kind = []): array
    {
        return array_merge([
            'children' => [array_merge([
                'first_name' => 'Sem',
                'last_name' => 'de Vries',
                'date_of_birth' => now()->subYears(10)->toDateString(),
                'position' => 'keeper',
                'product_id' => $this->blok->id,
                // Buiten de scope: het formulier is openbaar, zonder actieve school.
                'payment_option_id' => PaymentOption::withoutSchoolScope()->where('product_id', $this->blok->id)->where('is_default', true)->value('id'),
            ], $kind)],
            'guardian_name' => 'Marieke de Vries',
            'guardian_email' => 'marieke@voorbeeld.nl',
            'guardian_phone' => '0612345678',
            'relationship' => 'moeder',
            'password' => 'wachtwoord123',
            'consents' => ['avg'],
            'payment_method' => 'cash',
        ], $overschrijf);
    }

    protected function instellen(array $antwoorden): void
    {
        EnrollmentSettings::save($this->school, $antwoorden);
    }

    // --- De pagina ---

    public function test_het_formulier_is_openbaar_en_toont_aanbod_met_betaalvormen(): void
    {
        app(Tenancy::class)->set($this->school);
        $this->blok->syncPaymentOptions([
            ['type' => 'eenmalig', 'amount_cents' => 12000],
            ['type' => 'termijnen', 'amount_cents' => 4000, 'installments' => 3],
        ]);
        app(Tenancy::class)->forget();

        $this->get('/inschrijven/keepersschool-rob')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('enrollments/Public')
                ->where('enrollSchool.name', $this->school->name)
                ->where('enrollSchool.logo', null)
                ->count('products', 1)
                ->where('products.0.name', 'Keepersblok')
                ->count('products.0.payment_options', 2)
                ->where('products.0.payment_options.1.description', '3 × € 40,00 per maand')
                ->where('config.guardian', null)
                ->where('config.policy.approval', 'manual')
                ->where('config.consents.0.key', 'avg')
                ->where('config.consents.0.required', true)
                ->count('config.paymentMethods', 1)
                ->where('config.paymentMethods.0.value', 'cash')
            );
    }

    public function test_een_inactieve_of_onbekende_school_geeft_404(): void
    {
        $this->school->update(['is_active' => false]);

        $this->get('/inschrijven/keepersschool-rob')->assertNotFound();
        $this->get('/inschrijven/bestaat-niet')->assertNotFound();
    }

    // --- Indienen ---

    public function test_een_nieuwe_ouder_schrijft_zijn_kind_in_en_alles_ontstaat_in_een_keer(): void
    {
        Notification::fake();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier())
            ->assertRedirect('/inschrijven/keepersschool-rob')
            ->assertSessionHas('enrollment_submitted.status', 'awaiting_approval');

        app(Tenancy::class)->set($this->school);

        // Het ouderaccount, met het wachtwoord dat de ouder koos.
        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();
        $this->assertTrue($ouder->isOuder());
        $this->assertSame($this->school->id, $ouder->school_id);
        $this->assertTrue(Hash::check('wachtwoord123', $ouder->password));

        // Het kind, gekoppeld aan de ouder, nog niet in een groep.
        $speler = Player::firstOrFail();
        $this->assertSame('Sem', $speler->first_name);
        $this->assertTrue($speler->guardians->contains($ouder));
        $this->assertSame(0, $speler->groups()->count());

        // De inschrijving wacht op de school (handmatig goedkeuren is de standaard).
        $inschrijving = Enrollment::firstOrFail();
        $this->assertSame(EnrollmentStatus::AwaitingApproval, $inschrijving->status);
        $this->assertSame($speler->id, $inschrijving->player_id);
        $this->assertSame($ouder->id, $inschrijving->guardian_user_id);

        // De order met één regel, nog niet open.
        $order = Order::firstOrFail();
        $this->assertSame(12000, $order->total_cents);
        $this->assertSame('concept', $order->status->value);
        $this->assertSame('Keepersblok voor Sem', $order->lines()->first()->description);
        $this->assertDatabaseCount('payments', 0);

        // De toestemming, met de versie van nu.
        $this->assertDatabaseHas('consents', ['user_id' => $ouder->id, 'player_id' => $speler->id, 'version' => 1]);

        Notification::assertSentTo($this->eigenaar, NieuweInschrijving::class);
        Notification::assertSentTo($ouder, InschrijvingOntvangen::class, fn (InschrijvingOntvangen $m) => $m->newAccount === true);
    }

    public function test_verplichte_toestemming_en_het_wachtwoord_zijn_verplicht(): void
    {
        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['consents' => []]))
            ->assertSessionHasErrors('consents');

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['password' => 'kort']))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_een_bestaand_e_mailadres_moet_eerst_inloggen(): void
    {
        User::factory()->for($this->school)->create(['email' => 'marieke@voorbeeld.nl']);

        // Neutraal: de melding zegt niet letterlijk dat het adres bekend is.
        $this->post('/inschrijven/keepersschool-rob', $this->formulier())
            ->assertSessionHasErrors(['guardian_account' => SubmitEnrollment::BESTAAND_ACCOUNT]);

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_de_inlogknop_brengt_je_na_het_inloggen_terug_naar_de_inschrijfpagina(): void
    {
        $ouder = User::factory()->for($this->school)->create(['email' => 'marieke@voorbeeld.nl']);
        $ouder->assignRole(Role::Ouder->value);

        $this->get('/inschrijven/keepersschool-rob?aanbod='.$this->blok->id)
            ->assertInertia(fn ($page) => $page->where('loginUrl', route('login', ['redirect' => '/inschrijven/keepersschool-rob?aanbod='.$this->blok->id])));

        $this->get('/login?redirect='.urlencode('/inschrijven/keepersschool-rob?aanbod='.$this->blok->id))->assertOk();

        $this->post('/login', ['email' => 'marieke@voorbeeld.nl', 'password' => 'password'])
            ->assertRedirect(url('/inschrijven/keepersschool-rob?aanbod='.$this->blok->id));
    }

    public function test_de_terugweg_na_inloggen_blijft_binnen_de_site(): void
    {
        $this->get('/login?redirect='.urlencode('//kwaad.example/x'))->assertOk()->assertSessionMissing('url.intended');
        $this->get('/login?redirect='.urlencode('https://kwaad.example/x'))->assertOk()->assertSessionMissing('url.intended');
    }

    public function test_een_oude_slug_stuurt_permanent_door_naar_de_nieuwe(): void
    {
        $this->school->update(['slug' => 'keepersschool-rob-nieuw']);

        $this->assertDatabaseHas('school_slug_redirects', ['slug' => 'keepersschool-rob', 'school_id' => $this->school->id]);

        $this->get('/inschrijven/keepersschool-rob?aanbod=5')
            ->assertStatus(301)
            ->assertRedirect(url('/inschrijven/keepersschool-rob-nieuw?aanbod=5'));

        // Een formulier blijft een formulier: 308 houdt de POST een POST.
        $this->postJson('/inschrijven/keepersschool-rob/overzicht', [])
            ->assertStatus(308)
            ->assertRedirect(url('/inschrijven/keepersschool-rob-nieuw/overzicht'));
        $this->post('/inschrijven/keepersschool-rob', [])->assertStatus(308);

        $this->get('/inschrijven/keepersschool-rob-nieuw')->assertOk();
        $this->get('/inschrijven/nooit-bestaan')->assertNotFound();
    }

    public function test_een_oude_slug_die_weer_in_gebruik_is_stuurt_niet_meer_door(): void
    {
        $this->school->update(['slug' => 'rob-nieuw']);

        // Een andere school neemt het oude adres over: die wint.
        $ander = School::factory()->create(['slug' => 'keepersschool-rob']);

        $this->assertDatabaseMissing('school_slug_redirects', ['slug' => 'keepersschool-rob']);
        $this->get('/inschrijven/keepersschool-rob')->assertOk()->assertInertia(fn ($page) => $page->where('enrollSchool.name', $ander->name));

        // Terug naar de oude slug: de eigen school ook, en rob-nieuw wordt het oude adres.
        $ander->update(['slug' => 'iets-anders']);
        $this->school->update(['slug' => 'keepersschool-rob']);

        $this->get('/inschrijven/rob-nieuw')->assertRedirect(url('/inschrijven/keepersschool-rob'));
    }

    public function test_een_oude_slug_van_een_inactieve_school_geeft_404(): void
    {
        $this->school->update(['slug' => 'rob-nieuw']);
        $this->school->update(['is_active' => false]);

        $this->get('/inschrijven/keepersschool-rob')->assertNotFound();
    }

    public function test_aanbod_van_een_andere_school_leeftijd_en_positie_worden_geweigerd(): void
    {
        $andere = School::factory()->create();
        $vreemd = Product::factory()->for($andere)->blok()->create();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['product_id' => $vreemd->id]))
            ->assertSessionHasErrors('children.0.product_id');

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['date_of_birth' => now()->subYears(15)->toDateString()]))
            ->assertSessionHasErrors('children.0.date_of_birth');

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['audience' => 'keeper']);
        app(Tenancy::class)->forget();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['position' => 'field']))
            ->assertSessionHasErrors('children.0.position');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_het_overzicht_zegt_vooraf_wat_je_betaalt(): void
    {
        $this->instellen(['registration_fee' => ['enabled' => true, 'amount_cents' => 2500]]);

        $this->postJson('/inschrijven/keepersschool-rob/overzicht', ['children' => $this->formulier()['children']])
            ->assertOk()
            ->assertJsonPath('total_cents', 14500)
            ->assertJsonPath('lines.0.description', 'Keepersblok voor Sem')
            ->assertJsonPath('lines.1.description', 'Eenmalig inschrijfgeld')
            ->assertJsonPath('total', '€ 145,00');
    }

    // --- Goedkeuren en betalen ---

    public function test_goedkeuren_maakt_de_rekening_en_betalen_bevestigt(): void
    {
        Notification::fake();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier());

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::firstOrFail();

        $this->actingAs($this->eigenaar)
            ->post('/enrollments/'.$inschrijving->id.'/approve')
            ->assertRedirect();

        $this->assertSame(EnrollmentStatus::AwaitingPayment, $inschrijving->refresh()->status);
        $this->assertSame('open', $inschrijving->order->status->value);

        $rekening = $inschrijving->order->payments()->firstOrFail();
        $this->assertSame(12000, $rekening->amount_cents);
        $this->assertSame('cash', $rekening->method->value);

        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();
        Notification::assertSentTo($ouder, InschrijvingGoedgekeurd::class, function (InschrijvingGoedgekeurd $m) use ($ouder) {
            $mail = $m->toMail($ouder);
            $this->assertNull($mail->actionUrl);
            $this->assertStringContainsString('contant af bij de school', implode(' ', $mail->introLines));

            return true;
        });

        // Nog niets in de groep: er is nog niet betaald.
        $this->assertSame(0, Player::firstOrFail()->groups()->count());

        // De school zet de betaling op ontvangen: nu doet het kind mee.
        $this->actingAs($this->eigenaar)->patch('/payments/'.$rekening->id, ['status' => 'paid', 'method' => 'cash']);

        $this->assertSame(EnrollmentStatus::Confirmed, $inschrijving->refresh()->status);
        $this->assertNotNull($inschrijving->confirmed_at);
        $this->assertSame('paid', $inschrijving->order->refresh()->status->value);

        $deelname = Participation::firstOrFail();
        $this->assertSame(ParticipationStatus::Confirmed, $deelname->status);
        $this->assertNotNull($deelname->purchase_id);
        // De aankoop heeft geen eigen rekening: die zat al op de order.
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_online_betalen_geeft_een_ondertekende_betaallink(): void
    {
        Notification::fake();
        $this->metBetaalprovider();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['payment_method' => 'ideal']))
            ->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $this->actingAs($this->eigenaar)->post('/enrollments/'.Enrollment::firstOrFail()->id.'/approve');

        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();

        Notification::assertSentTo($ouder, InschrijvingGoedgekeurd::class, function (InschrijvingGoedgekeurd $m) use ($ouder) {
            $mail = $m->toMail($ouder);
            $this->assertStringContainsString('/betalen/', (string) $mail->actionUrl);
            $this->assertStringContainsString('signature=', (string) $mail->actionUrl);

            return true;
        });
    }

    public function test_zonder_betaalprovider_is_online_niet_te_kiezen(): void
    {
        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['payment_method' => 'ideal']))
            ->assertSessionHasErrors('payment_method');
    }

    public function test_termijnen_geven_een_rekening_per_termijn(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);
        $this->blok->syncPaymentOptions([
            ['type' => 'eenmalig', 'amount_cents' => 12000],
            ['type' => 'termijnen', 'amount_cents' => 4000, 'installments' => 3],
        ]);
        $termijnen = $this->blok->paymentOptions()->where('type', 'termijnen')->firstOrFail();
        app(Tenancy::class)->forget();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['payment_option_id' => $termijnen->id]))
            ->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::firstOrFail();
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/approve');

        $rekeningen = $inschrijving->refresh()->order->payments()->orderBy('due_on')->get();

        $this->assertCount(3, $rekeningen);
        $this->assertSame([4000, 4000, 4000], $rekeningen->pluck('amount_cents')->all());
        $this->assertSame('Keepersblok voor Sem (3 termijnen) (termijn 1 van 3)', $rekeningen[0]->description);

        // De eerste termijn betaald: het kind doet mee; de order is pas
        // betaald als alles binnen is.
        $this->actingAs($this->eigenaar)->patch('/payments/'.$rekeningen[0]->id, ['status' => 'paid', 'method' => 'transfer']);

        $this->assertSame(EnrollmentStatus::Confirmed, $inschrijving->refresh()->status);
        $this->assertSame('open', $inschrijving->order->refresh()->status->value);
    }

    public function test_een_abonnement_betaal_je_niet_vooraf_en_ontstaat_bij_de_bevestiging(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);
        $this->blok->syncPaymentOptions([['type' => 'abonnement', 'amount_cents' => 3000, 'interval' => 'monthly']]);
        $optie = $this->blok->paymentOptions()->firstOrFail();
        app(Tenancy::class)->forget();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['payment_option_id' => $optie->id]))
            ->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::firstOrFail();

        // Niets vooraf: de order is nul.
        $this->assertSame(0, $inschrijving->order->total_cents);

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/approve');

        $this->assertSame(EnrollmentStatus::Confirmed, $inschrijving->refresh()->status);
        $this->assertDatabaseHas('subscriptions', [
            'player_id' => $inschrijving->player_id,
            'payment_option_id' => $optie->id,
            'amount_cents' => 3000,
            'status' => 'active',
        ]);
        // En het abonnement brengt zelf zijn eerste rekening voort.
        $this->assertDatabaseHas('payments', ['amount_cents' => 3000, 'order_id' => null]);
    }

    public function test_automatisch_goedkeuren_en_een_gratis_proefles_zijn_meteen_rond(): void
    {
        Notification::fake();
        $this->instellen(['approval' => 'automatic', 'trial' => ['enabled' => true, 'amount_cents' => 0]]);

        // De proefles ontstaat uit de instellingen.
        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/2', [
            'offering_types' => ['blok', 'proefles'], 'enrollment_moments' => ['before_block'],
            'training_open' => false, 'training_payment_methods' => ['online', 'cash'], 'training_requires_approval' => false,
            'trial_enabled' => true, 'trial_amount' => '',
        ]);

        app(Tenancy::class)->set($this->school);
        $proefles = Product::where('type', ProductType::Proefles->value)->firstOrFail();
        $this->assertSame(0, $proefles->amount_cents);
        app(Tenancy::class)->forget();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: [
            'product_id' => $proefles->id,
            'payment_option_id' => PaymentOption::withoutSchoolScope()->where('product_id', $proefles->id)->value('id'),
        ]))->assertSessionHas('enrollment_submitted.status', 'confirmed');

        app(Tenancy::class)->set($this->school);
        $this->assertSame(EnrollmentStatus::Confirmed, Enrollment::firstOrFail()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('participations', ['product_id' => $proefles->id, 'status' => 'confirmed']);
    }

    public function test_automatisch_goedkeuren_met_een_bedrag_wacht_op_betaling(): void
    {
        Notification::fake();
        $this->instellen(['approval' => 'automatic']);

        $this->post('/inschrijven/keepersschool-rob', $this->formulier())
            ->assertSessionHas('enrollment_submitted.status', 'awaiting_payment');

        app(Tenancy::class)->set($this->school);
        $this->assertSame(EnrollmentStatus::AwaitingPayment, Enrollment::firstOrFail()->status);
        $this->assertDatabaseHas('payments', ['amount_cents' => 12000, 'status' => 'open']);
    }

    // --- Gezin ---

    public function test_een_ingelogde_ouder_schrijft_een_tweede_kind_in_met_gezinskorting_en_zonder_inschrijfgeld(): void
    {
        Notification::fake();
        $this->instellen([
            'registration_fee' => ['enabled' => true, 'amount_cents' => 2500],
            'discounts' => ['family' => ['enabled' => true, 'percent' => 10]],
        ]);

        // Eerste kind, als nieuwe ouder: inschrijfgeld erbij, geen korting.
        $this->post('/inschrijven/keepersschool-rob', $this->formulier());

        app(Tenancy::class)->set($this->school);
        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();
        $this->assertSame(14500, Order::firstOrFail()->total_cents);

        // Het formulier kent de ouder en zijn kind.
        $this->actingAs($ouder)
            ->get('/inschrijven/keepersschool-rob')
            ->assertInertia(fn ($page) => $page
                ->where('config.guardian.email', 'marieke@voorbeeld.nl')
                ->count('config.guardian.children', 1)
            );

        // Tweede kind: gezinskorting, en het inschrijfgeld niet nog eens.
        $this->actingAs($ouder)->post('/inschrijven/keepersschool-rob', [
            'children' => [[
                'first_name' => 'Liam', 'last_name' => 'de Vries', 'date_of_birth' => now()->subYears(9)->toDateString(), 'position' => 'field',
                'product_id' => $this->blok->id, 'payment_option_id' => $this->blok->paymentOptions()->value('id'),
            ]],
            'consents' => ['avg'],
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $order = Order::orderByDesc('id')->firstOrFail();
        $regels = $order->lines()->pluck('amount_cents', 'description');

        $this->assertSame(12000, $regels['Keepersblok voor Liam']);
        $this->assertSame(-1200, $regels['Gezinskorting 10%']);
        $this->assertArrayNotHasKey('Eenmalig inschrijfgeld', $regels->all());
        $this->assertSame(10800, $order->total_cents);
        $this->assertSame(2, $ouder->children()->count());
    }

    // --- Wachtlijst ---

    public function test_vol_aanbod_wordt_een_wachtlijstplek_zonder_order(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['capacity' => 1]);
        Participation::create(['product_id' => $this->blok->id, 'player_id' => Player::factory()->for($this->school)->create()->id, 'status' => ParticipationStatus::Confirmed]);
        app(Tenancy::class)->forget();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier())
            ->assertSessionHas('enrollment_submitted.status', 'waitlist');

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::firstOrFail();

        $this->assertSame(EnrollmentStatus::Waitlist, $inschrijving->status);
        $this->assertNull($inschrijving->order_id);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('participations', ['player_id' => $inschrijving->player_id, 'status' => 'waitlist']);
    }

    // --- De inbox ---

    public function test_afwijzen_sluit_de_order_en_alleen_de_eigenaar_komt_in_de_inbox(): void
    {
        Notification::fake();
        $this->post('/inschrijven/keepersschool-rob', $this->formulier());

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::firstOrFail();

        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get('/enrollments')->assertForbidden();
        $this->actingAs($trainer)->post('/enrollments/'.$inschrijving->id.'/approve')->assertForbidden();

        $this->actingAs($this->eigenaar)
            ->get('/enrollments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('pending', 1)->where('pending.0.order_total', '€ 120,00'));

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/decline')->assertRedirect();

        $this->assertSame(EnrollmentStatus::Declined, $inschrijving->refresh()->status);
        // Het enige kind op de order: de order gaat dicht, de inschrijving hangt er los van.
        $this->assertNull($inschrijving->order_id);
        $this->assertSame('cancelled', Order::firstOrFail()->status->value);
        Notification::assertSentTo(User::where('email', 'marieke@voorbeeld.nl')->firstOrFail(), InschrijvingAfgewezen::class);
        // Afwijzen is definitief: de statusmachine laat geen goedkeuren meer toe.
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/approve')->assertSessionHasErrors('enrollment');
    }

    public function test_afwijzen_haalt_alleen_dit_kind_van_een_gedeelde_order(): void
    {
        Notification::fake();

        $optie = PaymentOption::withoutSchoolScope()->where('product_id', $this->blok->id)->where('is_default', true)->value('id');
        $gegevens = $this->formulier();
        $gegevens['children'][] = [
            'first_name' => 'Liam', 'last_name' => 'de Vries', 'date_of_birth' => now()->subYears(9)->toDateString(),
            'position' => 'field', 'product_id' => $this->blok->id, 'payment_option_id' => $optie,
        ];

        $this->post('/inschrijven/keepersschool-rob', $gegevens)->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $order = Order::firstOrFail();
        $this->assertSame(24000, $order->total_cents);

        $sem = Enrollment::where('first_name', 'Sem')->firstOrFail();
        $liam = Enrollment::where('first_name', 'Liam')->firstOrFail();

        // Sem goedgekeurd: de order gaat open met een rekening voor beide kinderen.
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$sem->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame(24000, (int) $order->payments()->sum('amount_cents'));

        // Liam afgewezen: alleen zijn regel eraf, het totaal opnieuw, de rekening opnieuw.
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$liam->id.'/decline')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertSame(EnrollmentStatus::Declined, $liam->refresh()->status);
        $this->assertNull($liam->order_id);
        $this->assertSame(12000, $order->total_cents);
        $this->assertSame('open', $order->status->value);
        $this->assertSame(['Keepersblok voor Sem'], $order->lines()->pluck('description')->all());
        $this->assertSame(12000, (int) $order->payments()->outstanding()->sum('amount_cents'));
        $this->assertSame(1, $order->payments()->where('status', 'cancelled')->count());
        $this->assertSame(EnrollmentStatus::AwaitingPayment, $sem->refresh()->status);

        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();
        Notification::assertSentTo($ouder, InschrijvingAfgewezen::class, function (InschrijvingAfgewezen $m) use ($ouder) {
            $mail = $m->toMail($ouder);
            $this->assertStringContainsString('Liam', (string) $mail->subject);

            return $m->enrollment->first_name === 'Liam';
        });

        // Wie al wacht op betaling kan niet meer worden afgewezen; dat is annuleren.
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$sem->id.'/decline')->assertSessionHasErrors('enrollment');
        $this->assertSame(EnrollmentStatus::AwaitingPayment, $sem->refresh()->status);
    }

    public function test_afwijzen_vanaf_de_wachtlijst_annuleert_de_wachtlijstplek(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['capacity' => 1]);
        Participation::create(['product_id' => $this->blok->id, 'player_id' => Player::factory()->for($this->school)->create()->id, 'status' => ParticipationStatus::Confirmed]);
        app(Tenancy::class)->forget();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier());

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::firstOrFail();

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/decline')->assertSessionHasNoErrors();

        $this->assertSame(EnrollmentStatus::Declined, $inschrijving->refresh()->status);
        $this->assertDatabaseHas('participations', ['player_id' => $inschrijving->player_id, 'status' => 'cancelled']);
        $this->assertDatabaseMissing('participations', ['player_id' => $inschrijving->player_id, 'status' => 'waitlist']);
        Notification::assertSentTo(User::where('email', 'marieke@voorbeeld.nl')->firstOrFail(), InschrijvingAfgewezen::class);
    }

    // --- Positie en meldingen ---

    public function test_zonder_positieveld_mag_de_positie_leeg_zijn(): void
    {
        Notification::fake();
        $this->instellen(['fields' => ['positie' => 'off']]);

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['position' => null]))
            ->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        // Het aanbod is voor iedereen: dan wordt het de standaard.
        $this->assertSame('keeper', Player::firstOrFail()->position->value);
    }

    public function test_zonder_positieveld_volgt_de_positie_de_doelgroep(): void
    {
        Notification::fake();
        $this->instellen(['fields' => ['positie' => 'off']]);

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['audience' => 'field']);
        app(Tenancy::class)->forget();

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['position' => null]))
            ->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $this->assertSame('field', Player::firstOrFail()->position->value);
    }

    public function test_een_verplichte_positie_moet_gekozen_worden(): void
    {
        $this->instellen(['fields' => ['positie' => 'required']]);

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['position' => null]))
            ->assertSessionHasErrors(['children.0.position' => 'Kies een positie.']);

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_meldingen_bij_een_kind_zijn_nederlands(): void
    {
        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['first_name' => '', 'last_name' => '']))
            ->assertSessionHasErrors([
                'children.0.first_name' => 'Vul de voornaam in.',
                'children.0.last_name' => 'Vul de achternaam in.',
            ]);

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['date_of_birth' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['children.0.date_of_birth' => 'De geboortedatum moet in het verleden liggen.']);

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['date_of_birth' => now()->subYears(40)->toDateString()]))
            ->assertSessionHasErrors(['children.0.date_of_birth' => 'Controleer de geboortedatum: die ligt wel erg ver terug.']);

        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['date_of_birth' => 'geen datum']))
            ->assertSessionHasErrors(['children.0.date_of_birth' => 'Vul een geldige geboortedatum in.']);

        // Nergens meer een technische veldnaam in de melding.
        $this->post('/inschrijven/keepersschool-rob', $this->formulier(kind: ['payment_option_id' => null]))
            ->assertSessionHasErrors(['children.0.payment_option_id' => 'Kies een betaalvorm.']);

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_het_overzicht_heeft_een_eigen_limiet(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/inschrijven/keepersschool-rob/overzicht', ['children' => $this->formulier()['children']])->assertOk();
        }

        $this->postJson('/inschrijven/keepersschool-rob/overzicht', ['children' => $this->formulier()['children']])->assertStatus(429);

        // Het overzicht maakt de inzendingen niet op.
        Notification::fake();
        $this->post('/inschrijven/keepersschool-rob', $this->formulier())->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_het_logo_van_de_school_staat_op_de_pagina(): void
    {
        $this->school->forceFill(['logo_path' => 'logos/rob.png'])->save();

        $this->get('/inschrijven/keepersschool-rob')
            ->assertInertia(fn ($page) => $page->where('enrollSchool.logo', fn ($url) => str_ends_with((string) $url, 'logos/rob.png')));
    }

    public function test_gesloten_en_verborgen_aanbod_staat_niet_op_de_pagina(): void
    {
        app(Tenancy::class)->set($this->school);
        Product::factory()->for($this->school)->blok()->create(['name' => 'Gesloten', 'status' => OfferingStatus::Gesloten]);
        Product::factory()->for($this->school)->blok()->create(['name' => 'Verborgen', 'is_active' => false]);
        app(Tenancy::class)->forget();

        $this->get('/inschrijven/keepersschool-rob')
            ->assertInertia(fn ($page) => $page->count('products', 1)->where('products.0.name', 'Keepersblok'));
    }

    public function test_toestemmingsteksten_van_de_school_staan_op_het_formulier(): void
    {
        app(Tenancy::class)->set($this->school);
        ConsentDocument::put('beeldrecht', 'Foto en video', 'Wij maken foto’s.', true);
        app(Tenancy::class)->forget();

        $this->get('/inschrijven/keepersschool-rob')
            ->assertInertia(fn ($page) => $page->where('config.consents.1.title', 'Foto en video')->where('config.consents.1.required', true));

        // Verplicht is verplicht.
        $this->post('/inschrijven/keepersschool-rob', $this->formulier(['consents' => ['avg']]))
            ->assertSessionHasErrors('consents');
    }
}
