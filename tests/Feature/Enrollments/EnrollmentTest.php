<?php

namespace Tests\Feature\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\NieuweInschrijving;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            'payment_method' => 'directdebit',
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
        Notification::assertNothingSent();
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
}
