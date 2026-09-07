<?php

namespace App\Models\Concerns;

use App\Support\Status\TransitionException;
use BackedEnum;

/**
 * Een model met een `status`-kolom die alleen via toegestane overgangen
 * verandert. `transitionTo()` is de enige nette weg; wie `status` met de hand
 * zet omzeilt de machine, en dat is precies wat er niet moet gebeuren.
 */
trait HasStatusMachine
{
    /**
     * @param  array<string, mixed>  $extra  velden die met de overgang meegaan (bijv. handled_at)
     */
    public function transitionTo(BackedEnum $naar, array $extra = []): static
    {
        $van = $this->status;

        if ($van !== null && $van !== $naar && ! $van->canTransitionTo($naar)) {
            throw new TransitionException(sprintf(
                '%s kan niet van "%s" naar "%s".',
                class_basename($this),
                $van->label(),
                $naar->label(),
            ));
        }

        $this->forceFill(['status' => $naar, ...$extra])->save();

        return $this;
    }

    public function canTransitionTo(BackedEnum $naar): bool
    {
        return $this->status === null || $this->status->canTransitionTo($naar);
    }
}
