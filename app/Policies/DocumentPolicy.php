<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function download(User $user, Document $document): bool
    {
        return $user->can('documents.download') && $this->canViewParent($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('documents.delete') && $this->canViewParent($user, $document);
    }

    public function view(User $user, Document $document): bool
    {
        return $this->download($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.upload');
    }

    public function update(User $user, Document $document): bool
    {
        return false;
    }

    public function restore(User $user, Document $document): bool
    {
        return false;
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }

    private function canViewParent(User $user, Document $document): bool
    {
        $parent = $document->documentable;

        return $parent instanceof Model && $user->can('view', $parent);
    }
}
