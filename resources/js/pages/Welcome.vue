<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head title="Migraine Map" />

    <div
        class="bg-background text-foreground flex min-h-svh flex-col items-center p-6 lg:p-8"
    >
        <header class="w-full max-w-4xl">
            <nav class="flex items-center justify-end gap-4 text-sm">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="border-input hover:bg-accent inline-block rounded-md border px-5 py-1.5 leading-normal"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="hover:bg-accent inline-block rounded-md border border-transparent px-5 py-1.5 leading-normal"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="border-input hover:bg-accent inline-block rounded-md border px-5 py-1.5 leading-normal"
                    >
                        Register
                    </Link>
                </template>
            </nav>
        </header>

        <main
            class="flex w-full max-w-4xl flex-1 flex-col items-center justify-center gap-6 text-center"
        >
            <AppLogoIcon class="text-primary size-16" />
            <h1 class="text-4xl font-semibold tracking-tight">Migraine Map</h1>
            <p class="text-muted-foreground text-lg">
                Track your triggers. See your patterns.
            </p>
        </main>
    </div>
</template>
