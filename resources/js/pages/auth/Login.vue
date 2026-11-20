<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
const form = useForm({ email: '', password: '', remember: false });
function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Вход" />
    <div class="mx-auto w-full max-w-md px-4 py-8">
        <h1 class="text-2xl font-bold">Войти в аккаунт</h1>
        <p class="mt-2 text-sm text-muted-foreground">Продолжи работать над привычками</p>
        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <label class="block text-sm font-medium">Email<input v-model="form.email" type="email" required autocomplete="email" class="mt-2 w-full rounded-lg border border-neutral-300 bg-transparent p-3 dark:border-neutral-700" /><span v-if="form.errors.email" class="text-red-600">{{ form.errors.email }}</span></label>
            <label class="block text-sm font-medium">Пароль<input v-model="form.password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border border-neutral-300 bg-transparent p-3 dark:border-neutral-700" /><span v-if="form.errors.password" class="text-red-600">{{ form.errors.password }}</span></label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.remember" type="checkbox" />Запомнить меня</label>
            <button :disabled="form.processing" class="w-full rounded-xl bg-[#5386e4] p-3 font-semibold text-white disabled:opacity-50">Войти</button>
        </form>
        <p class="mt-6 text-center text-sm">Нет аккаунта? <Link href="/register" class="text-[#5386e4] underline">Зарегистрироваться</Link></p>
    </div>
</template>
