<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Business Management System',
        description: 'Sign in to access your account',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Business Management System - Login" />

    <div class="flex min-h-[500px] items-center justify-center px-4">
        <div
            class="w-full max-w-md rounded-xl border border-border bg-card p-8 shadow-lg"
        >
            <!-- Header -->
            <div class="mb-8 text-center">

                <h1 class="text-2xl font-semibold tracking-tight">
                    Business Management System
                </h1>

                <p class="mt-2 text-sm text-muted-foreground">
                    Sign in to access your account
                </p>
            </div>

            <!-- Status -->
            <div
                v-if="status"
                class="mb-6 rounded-md bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700"
            >
                {{ status }}
            </div>

            <!-- Login Form -->
            <Form
                v-bind="store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="space-y-5"
            >
                <!-- Email -->
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>

                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        v-focus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="Enter your email"
                        class="h-11"
                    />

                    <InputError :message="errors.email" />
                </div>

                <!-- Password -->
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>

                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="4"
                        >
                            Forgot password?
                        </TextLink>
                    </div>

                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                    />

                    <InputError :message="errors.password" />
                </div>

                <!-- Remember -->
                <div class="flex items-center">
                    <Label
                        for="remember"
                        class="flex cursor-pointer items-center gap-2"
                    >
                        <Checkbox
                            id="remember"
                            name="remember"
                            :tabindex="3"
                        />
                        <span class="text-sm font-normal">
                            Remember me
                        </span>
                    </Label>
                </div>

                <!-- Login Button -->
                <Button
                    type="submit"
                    class="h-11 w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Signing in...' : 'Sign in' }}
                </Button>
            </Form>

            <!-- Footer -->
            <div
                class="mt-8 border-t border-border pt-5 text-center text-xs text-muted-foreground"
            >
                Powered by <a href="https://leepeapp.com/" target="_blank" class="font-medium text-primary hover:underline">Leepe Outsourcing Corp.</a>
            </div>
        </div>
    </div>
</template>