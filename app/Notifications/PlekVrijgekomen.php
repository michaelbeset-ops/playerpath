<?php

namespace App\Notifications;

use App\Models\Player;
use App\Models\Product;
use App\Notifications\Concerns\SendsFromSchool;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Er is een plek vrijgekomen" — het bericht waar een gezin op wacht.
 *
 * Gaat altijd uit, ook per mail: iemand die op een wachtlijst staat kijkt niet
 * elke dag in de app. Dat is precies waarom hij op een wachtlijst staat.
 */
class PlekVrijgekomen extends Notification implements ShouldQueue
{
    use Queueable, SendsFromSchool;

    public function __construct(
        public Product $product,
        public Player $player,
        public ?string $payUrl = null,
        public ?CarbonInterface $deadline = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $bericht = $this->schoolMail($notifiable)
            ->subject('Er is een plek vrij: '.$this->product->name)
            ->greeting('Goed nieuws')
            ->line("{$this->player->first_name} heeft een plek bij **{$this->product->name}**.");

        if ($this->product->starts_on !== null) {
            $bericht->line('Het begint op '.$this->product->starts_on->translatedFormat('j F Y').'.');
        }

        // Uitgenodigd vanaf de wachtlijst: betalen vóór de deadline, anders
        // gaat de plek naar de volgende.
        if ($this->payUrl !== null && $this->deadline !== null) {
            $bericht->line('Betaal vóór **'.$this->deadline->translatedFormat('j F').'** om de plek vast te leggen; daarna gaat hij naar de volgende op de wachtlijst.')
                ->action('Nu betalen', $this->payUrl);
        } elseif ($this->deadline !== null) {
            $bericht->line('Bevestig vóór **'.$this->deadline->translatedFormat('j F').'** bij de school; daarna gaat de plek naar de volgende op de wachtlijst.');
        } elseif ($this->product->amount_cents > 0) {
            $bericht->line('De rekening staat klaar; je ziet bij Mijn abonnement hoe je betaalt.')
                ->action('Bekijk je betalingen', route('billing.index'));
        }

        return $bericht->salutation($this->schoolSalutation($notifiable));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'plek_vrijgekomen',
            'product_id' => $this->product->id,
            'player_id' => $this->player->id,
            'title' => "{$this->player->first_name} heeft een plek bij {$this->product->name}",
            'url' => '/billing',
        ];
    }
}
