<?php

namespace App\Enums;

enum PlayerPosition: string
{
    case Keeper = 'keeper';
    case Field = 'field';

    public function label(): string
    {
        return match ($this) {
            self::Keeper => 'Keeper',
            self::Field => 'Veldspeler',
        };
    }

    /** De categorieën waarop deze positie beoordeeld wordt.
     *
     * @return list<ReportCategory>
     */
    public function categories(): array
    {
        return ReportCategory::forPosition($this);
    }

    /** @return array<string, string> waarde => Nederlands label */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
