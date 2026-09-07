<?php

namespace App\Policies;

use App\Models\ConsentDocument;
use App\Models\User;

/**
 * Toestemmingsteksten zijn van de eigenaar: wat een ouder tekent gaat namens
 * de school, en dat formuleert een trainer niet.
 */
class ConsentDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, ConsentDocument $document): bool
    {
        return $user->belongsToSameSchool($document) && $user->isEigenaar();
    }
}
