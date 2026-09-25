<?php

namespace App\Policies;

use App\Models\ProductService;
use App\Models\User;

class ProductServicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('products-services.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProductService $productService): bool
    {
        return $user->can('products-services.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('products-services.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProductService $productService): bool
    {
        return $user->can('products-services.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProductService $productService): bool
    {
        return $user->can('products-services.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProductService $productService): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProductService $productService): bool
    {
        return false;
    }
}
