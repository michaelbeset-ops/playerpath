<?php

namespace Tests\Feature\Billing;

use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * De shop als catalogus: per soort gegroepeerd, met per aanbod een link die
 * direct de inschrijving van dát aanbod in gaat, en een optionele afbeelding
 * die de school bij het aanbod zet.
 */
class ShopCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $ouder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['slug' => 'keepersschool-rob']);
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);
        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        app(Tenancy::class)->set($this->school);

        $this->ouder->children()->attach(Player::factory()->for($this->school)->create()->id);
    }

    public function test_de_shop_groepeert_per_soort_en_linkt_direct_naar_de_inschrijving(): void
    {
        $kamp = Product::factory()->for($this->school)->blok(capaciteit: 5)->create(['name' => 'Zomerkamp', 'type' => ProductType::Kamp, 'location' => 'Sportpark']);
        $blok = Product::factory()->for($this->school)->blok()->create(['name' => 'Keepersblok']);
        Product::factory()->for($this->school)->rittenkaart()->create(['name' => 'Rittenkaart 10']);

        $this->actingAs($this->ouder)
            ->get('/shop')
            ->assertOk()
            ->assertInertia(function ($page) use ($kamp, $blok) {
                $groepen = collect($page->toArray()['props']['groups'])->keyBy('key');

                $this->assertSame(['Zomerkamp'], collect($groepen['kamp']['products'])->pluck('name')->all());
                $this->assertSame(['Keepersblok'], collect($groepen['blok']['products'])->pluck('name')->all());
                $this->assertSame(['Rittenkaart 10'], collect($groepen['rittenkaart']['products'])->pluck('name')->all());

                $kampRij = $groepen['kamp']['products'][0];
                $this->assertSame('/inschrijven/keepersschool-rob?aanbod='.$kamp->id, parse_url($kampRij['enroll_url'], PHP_URL_PATH).'?'.parse_url($kampRij['enroll_url'], PHP_URL_QUERY));
                $this->assertSame('Sportpark', $kampRij['location']);
                $this->assertSame(5, $kampRij['spots_left']);
                $this->assertNull($kampRij['image']);
                $this->assertSame($blok->id, $groepen['blok']['products'][0]['id']);
            });
    }

    public function test_de_eigenaar_zet_een_afbeelding_bij_een_aanbod_en_haalt_hem_weer_weg(): void
    {
        Storage::fake('public');
        $kamp = Product::factory()->for($this->school)->blok()->create(['type' => ProductType::Kamp]);

        $this->actingAs($this->eigenaar)
            ->post('/aanbod/'.$kamp->id.'/foto', ['photo' => UploadedFile::fake()->image('kamp.jpg', 2400, 1600)])
            ->assertRedirect();

        $kamp->refresh();
        $this->assertNotNull($kamp->photo_path);
        Storage::disk('public')->assertExists($kamp->photo_path);
        $this->assertStringNotContainsString((string) $kamp->id, basename($kamp->photo_path));

        // In de shop en op de inschrijfpagina staat hij erbij.
        $this->actingAs($this->ouder)->get('/shop')
            ->assertInertia(function ($page) use ($kamp) {
                $groep = collect($page->toArray()['props']['groups'])->firstWhere('key', 'kamp');
                $this->assertStringContainsString($kamp->photo_path, $groep['products'][0]['image']);
            });

        $this->get('/inschrijven/keepersschool-rob')
            ->assertInertia(fn ($page) => $page->where('products.0.image', fn ($url) => str_contains($url, $kamp->photo_path)));

        $this->actingAs($this->eigenaar)->delete('/aanbod/'.$kamp->id.'/foto')->assertRedirect();
        $this->assertNull($kamp->refresh()->photo_path);

        // Geen svg, en geen trainer.
        $this->actingAs($this->eigenaar)
            ->post('/aanbod/'.$kamp->id.'/foto', ['photo' => UploadedFile::fake()->create('kamp.svg', 10, 'image/svg+xml')])
            ->assertSessionHasErrors('photo');

        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);
        $this->actingAs($trainer)->post('/aanbod/'.$kamp->id.'/foto', ['photo' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();
    }
}
