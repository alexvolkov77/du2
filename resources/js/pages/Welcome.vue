<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Трекер привычек — Школа Душноты" />
    <div class="min-h-screen bg-[#fcfaf6] text-[#171717]">
        <header class="mx-auto flex max-w-5xl items-center justify-between px-6 py-7">
            <a href="/" class="font-bold tracking-tight">Школа Душноты</a>
            <div class="flex items-center gap-4 text-sm">
                <Link v-if="$page.props.auth.user" href="/dashboard" class="rounded-full bg-[#5386e4] px-5 py-2 font-semibold text-white">Мои привычки</Link>
                <template v-else>
                    <Link href="/login" class="font-medium hover:underline">Войти</Link>
                    <Link href="/register" class="rounded-full bg-[#5386e4] px-5 py-2 font-semibold text-white">Начать</Link>
                </template>
            </div>
        </header>
        <main class="mx-auto flex min-h-[75vh] max-w-5xl flex-col justify-center px-6 py-20">
            <div class="mb-6 w-fit rounded-full bg-[#edbf85] px-4 py-2 text-sm font-semibold">Трекер привычек</div>
            <h1 class="max-w-3xl text-5xl font-bold leading-tight tracking-tight md:text-7xl">Стань чуть лучше. Душни по расписанию.</h1>
            <p class="mt-8 max-w-xl text-lg text-neutral-600">Создавай привычки, отмечай выполнение и наблюдай, как маленькие действия превращаются в большую систему.</p>
            <div class="mt-10 flex flex-wrap gap-4">
                <Link :href="$page.props.auth.user ? '/dashboard' : '/register'" class="rounded-xl bg-[#5386e4] px-8 py-4 font-semibold text-white hover:opacity-90">Начать бесплатно</Link>
                <Link v-if="!$page.props.auth.user" href="/login" class="rounded-xl border border-neutral-300 px-8 py-4 font-semibold hover:bg-white">Уже есть аккаунт</Link>
            </div>
        </main>
    </div>
</template>
