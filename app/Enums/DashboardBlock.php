<?php

namespace App\Enums;

/**
 * De panelen onder de kerncijfers.
 *
 * Zelfde afspraak als bij DashboardTile: dit is de enige lijst, de waarden
 * liggen vast omdat ze opgeslagen worden, en niet alles staat standaard aan.
 *
 * De opstartchecklist staat er bewust níét bij. Die verdwijnt vanzelf zodra
 * hij af is; hem uit kunnen zetten zou betekenen dat een school hem wegklikt
 * en daarna nooit meer weet wat er nog moet.
 */
enum DashboardBlock: string
{
    case QuickActions = 'quick_actions';
    case Attention = 'attention';
    case Trainings = 'trainings';
    case Birthdays = 'birthdays';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::QuickActions => 'Snelle acties',
            self::Attention => 'Vraagt om aandacht',
            self::Trainings => 'Aankomende trainingen',
            self::Birthdays => 'Verjaardagen',
            self::Finance => 'Financieel',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::QuickActions => 'De knoppen die je het vaakst nodig hebt, boven de cijfers.',
            self::Attention => 'Spelers die te lang geen rapport hebben gehad.',
            self::Trainings => 'Wat er als eerstvolgende op het rooster staat.',
            self::Birthdays => 'Wie er de komende weken jarig is.',
            self::Finance => 'Omzet, openstaand en achterstallig bij elkaar.',
        };
    }

    public function feature(): ?Feature
    {
        return match ($this) {
            self::Attention => Feature::Ontwikkeling,
            self::Finance => Feature::Betalingen,
            default => null,
        };
    }

    public function ownerOnly(): bool
    {
        return $this === self::Finance;
    }

    /** Alles behalve het financiële vak; dat is niet voor iedereen. */
    public function defaultVisible(): bool
    {
        return true;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
