<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Auth/ForcedPasswordController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Change your password',
        description: 'Replace your temporary password to continue',
    },
});
</script>

<template>
    <Head title="Change your password" />

    <div class="flex min-h-[500px] items-center justify-center px-4">
        <div
            class="w-full max-w-md rounded-xl border border-border bg-card p-8 shadow-lg"
        >
            <div class="mb-8 space-y-2 text-center">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Change your password
                </h1>
                <p class="text-sm text-muted-foreground">
                    For security, replace your temporary password before using
                    the system.
                </p>
            </div>

            <Form
                v-bind="update.form()"
                :reset-on-error="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="space-y-5"
            >
                <div class="grid gap-2">
                    <Label for="password">New password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autofocus
                        autocomplete="new-password"
                        placeholder="Minimum 8 characters"
                        :passwordrules="passwordRules"
                    />
                    <p class="text-xs text-muted-foreground">
                        Minimum 8 characters. No special character, number, or
                        uppercase letter is required.
                    </p>
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        Confirm new password
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Repeat new password"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full"
                    :disabled="processing"
                    data-test="change-temporary-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Saving...' : 'Change password' }}
                </Button>
            </Form>
        </div>
    </div>
</template>
