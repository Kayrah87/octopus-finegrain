<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Log in" />

    <main class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm rounded-xl border border-line bg-surface p-6">
            <div class="mb-6 flex items-center gap-2">
                <img src="/favicon.svg" alt="" class="size-7" />
                <h1 class="text-lg font-semibold tracking-tight">Finegrain</h1>
            </div>

            <Form action="/login" method="post" class="space-y-4" #default="{ errors, processing }" :reset-on-error="['password']">
                <label class="block text-sm">
                    <span class="text-ink-2">Username</span>
                    <input name="username" type="text" autocomplete="username" required autofocus class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-2" :aria-invalid="!!errors.username" aria-describedby="login-error" />
                </label>
                <label class="block text-sm">
                    <span class="text-ink-2">Password</span>
                    <input name="password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-md border border-line bg-surface px-3 py-2" />
                </label>
                <p v-if="errors.username" id="login-error" class="text-sm text-critical" role="alert">{{ errors.username }}</p>
                <button type="submit" :disabled="processing" class="w-full rounded-md bg-accent px-3 py-2 font-medium text-accent-ink hover:opacity-90 disabled:opacity-60">
                    {{ processing ? 'Logging in…' : 'Log in' }}
                </button>
            </Form>
        </div>
    </main>
</template>
