<?php

namespace Tests\Feature\Schools;

use App\Enums\Feature;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\ConsentDocument;
use App\Models\School;
use App\Models\User;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Features\Features;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hoe een school inschrijft en int: de wizard en wat de rest van de app eruit leest.
 */
class EnrollmentSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);
    }

    public function test_standaarden_staan_in_code_en_niet_in_de_database(): void
    {
        $instellingen = EnrollmentSettings::for($this->school);

        $this->assertNull($this->school->enrollment_settings);
        $this->assertFalse($instellingen->isCompleted());
        $this->assertTrue($instellingen->approvesManually());
        $this->assertSame(1, $instellingen->noticeMonths());
        $this->assertSame(0, $instellingen->registrationFeeCents());
        $this->assertTrue($instellingen->offers(ProductType::Proefles));
        // Overig is altijd beschikbaar: kleding en materiaal zijn geen aanbodvorm.
        $this->assertTrue($instellingen->offers(ProductType::Overig));
    }

    public function test_een_nieuwe_school_begint_bij_stap_een(): void
    {
        // Het menu-item opent meteen stap één: een overzicht van standaarden
        // die je nog nooit hebt gezien zegt niets.
        $this->actingAs($this->eigenaar)
            ->get('/instellingen/inschrijven')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('enrollment-settings/Wizard')->where('step', 1));

        $this->actingAs($this->eigenaar)
            ->get('/instellingen/inschrijven/stap/1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('enrollment-settings/Wizard')
                ->where('step', 1)
                ->where('completed', false)
                ->count('steps', 6)
            );
    }

    public function test_elke_stap_zet_meteen_een_instelling_en_de_laatste_rondt_af(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/instellingen/inschrijven/stap/1', [
                'offering_types' => ['blok', 'kamp'],
                'trial_enabled' => true,
                'trial_amount' => '7,50',
            ])
            ->assertRedirect('/instellingen/inschrijven/stap/2');

        $instellingen = EnrollmentSettings::for($this->school->refresh());

        $this->assertSame(750, $instellingen->trialAmountCents());
        // Proefles staat niet meer in de gekozen soorten, dus hij is er niet,
        // ook al staat het vinkje aan.
        $this->assertFalse($instellingen->trialEnabled());
        $this->assertFalse($instellingen->offers(ProductType::Doorlopend));
        $this->assertTrue($instellingen->offers(ProductType::Kamp));
        // De andere instellingen zijn niet aangeraakt: alleen deze stap is opgeslagen.
        $this->assertSame(['blok', 'kamp'], array_keys(array_flip($this->school->enrollment_settings['offering_types'])));
        $this->assertArrayNotHasKey('notice_months', $this->school->enrollment_settings);

        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/2', [
            'registration_fee_enabled' => true,
            'registration_fee_amount' => '25',
            'kit_enabled' => false,
            'kit_amount' => '',
        ])->assertRedirect('/instellingen/inschrijven/stap/3');

        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/3', [
            'default_payment_type' => 'installments',
            'installments' => 4,
            'installment_interval' => 'month',
            'auto_renew_block' => false,
            'notice_months' => 2,
            'approval' => 'automatic',
            'chargeback_fee_enabled' => true,
            'chargeback_fee_amount' => '7,50',
        ])->assertRedirect('/instellingen/inschrijven/stap/4');

        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/4', [
            'free_until_days' => 7,
            'retain_percent' => 25,
            'absence' => 'makeup',
        ])->assertRedirect('/instellingen/inschrijven/stap/5');

        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/5', [
            'family_enabled' => true, 'family_percent' => 10,
            'early_enabled' => false, 'early_percent' => 10, 'early_days_before' => 30,
            'volume_enabled' => false, 'volume_percent' => 10, 'volume_from_count' => 2,
            'code_enabled' => true,
            'stackable' => false,
        ])->assertRedirect('/instellingen/inschrijven/stap/6');

        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/6', [
            'waitlist' => true,
            'pay_on_placement' => true,
            'invitation_days' => 5,
            'fields' => ['kledingmaat' => 'required', 'positie' => 'required', 'niveau' => 'off', 'medisch' => 'optional'],
            'consents' => $this->toestemmingen(['avg' => true, 'beeldrecht' => true, 'gedragsregels' => false, 'medisch' => false]),
            'development' => false,
        ])->assertRedirect('/instellingen/inschrijven');

        $instellingen = EnrollmentSettings::for($this->school->refresh());

        $this->assertTrue($instellingen->isCompleted());
        $this->assertSame(2500, $instellingen->registrationFeeCents());
        $this->assertFalse($instellingen->approvesManually());
        $this->assertSame(2, $instellingen->noticeMonths());
        $this->assertSame(750, $instellingen->chargebackFeeCents());
        $this->assertSame('required', $instellingen->field('kledingmaat'));
        $this->assertSame('off', $instellingen->field('niveau'));
        $this->assertSame(['free_until_days' => 7, 'retain_percent' => 25], $instellingen->get('cancellation'));

        // De ontwikkelingslaag is een functie van de school, geen losse instelling.
        $this->assertFalse(Features::enabledFor($this->school, Feature::Ontwikkeling));

        // Toestemmingen staan in hun eigen tabel, met een versie.
        $this->assertSame(4, ConsentDocument::count());
        $this->assertTrue(ConsentDocument::where('key', 'beeldrecht')->first()->required);
        $this->assertFalse(ConsentDocument::where('key', 'medisch')->first()->required);

        // Daarna opent het overzicht, en een wijziging komt daar terug.
        $this->actingAs($this->eigenaar)
            ->get('/instellingen/inschrijven')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('enrollment-settings/Index'));

        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/4', [
            'free_until_days' => 14, 'retain_percent' => 50, 'absence' => 'none',
        ])->assertRedirect('/instellingen/inschrijven');
    }

    public function test_een_andere_tekst_is_een_nieuwe_versie_van_de_toestemming(): void
    {
        $antwoorden = fn (string $tekst) => [
            'waitlist' => true, 'pay_on_placement' => true, 'invitation_days' => 3,
            'fields' => ['kledingmaat' => 'off', 'positie' => 'required', 'niveau' => 'optional', 'medisch' => 'optional'],
            'consents' => $this->toestemmingen(['avg' => true, 'beeldrecht' => false, 'gedragsregels' => false, 'medisch' => false], $tekst),
            'development' => true,
        ];

        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/6', $antwoorden('Eerste tekst.'));
        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/6', $antwoorden('Eerste tekst.'));

        $this->assertSame(1, ConsentDocument::where('key', 'avg')->first()->version);

        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/6', $antwoorden('Andere tekst.'));

        $this->assertSame(2, ConsentDocument::where('key', 'avg')->first()->version);
    }

    public function test_de_aanbodsoorten_uit_de_instellingen_bepalen_het_aanbodformulier(): void
    {
        EnrollmentSettings::save($this->school, ['offering_types' => ['kamp']]);

        $this->actingAs($this->eigenaar)
            ->get('/aanbod/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('types', function ($types) {
                $waarden = collect($types)->pluck('value')->all();

                return $waarden === ['kamp', 'overig'];
            }));
    }

    public function test_alleen_de_eigenaar_komt_erbij_en_de_school_van_een_ander_niet(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get('/instellingen/inschrijven')->assertForbidden();
        $this->actingAs($trainer)->patch('/instellingen/inschrijven/stap/1', ['offering_types' => ['blok']])->assertForbidden();

        // Toestemmingsteksten van een andere school blijven onzichtbaar.
        $andere = School::factory()->create();
        app(Tenancy::class)->forSchool($andere, fn () => ConsentDocument::create([
            'key' => 'avg', 'title' => 'Geheim', 'body' => 'Van de buren.', 'version' => 3, 'required' => true,
        ]));

        app(Tenancy::class)->set($this->school);

        $this->assertSame(0, ConsentDocument::count());
        $this->assertSame('Privacy (AVG)', ConsentDocument::allForSchool()[0]['title']);
    }

    public function test_de_wizard_is_de_eerste_stap_van_de_checklist(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('checklist.steps.0.key', 'enrollment')
                ->where('checklist.steps.0.done', false)
            );

        EnrollmentSettings::complete($this->school);

        // Elke echte request laadt de gebruiker vers; in de test hangt er nog
        // een school van vóór het afronden aan.
        $this->actingAs($this->eigenaar->fresh())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('checklist.steps.0.done', true));
    }

    /**
     * @param  array<string, bool>  $verplicht
     * @return array<string, array{required: bool, title: string, body: string}>
     */
    protected function toestemmingen(array $verplicht, string $tekst = 'De tekst.'): array
    {
        return collect(ConsentDocument::SOORTEN)->map(fn (array $soort, string $key) => [
            'required' => $verplicht[$key],
            'title' => $soort['title'],
            'body' => $tekst,
        ])->all();
    }
}
