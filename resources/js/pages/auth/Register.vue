<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
function submit() {
    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Регистрация" />
    <div class="mx-auto w-full max-w-md px-4 py-8">
        <h1 class="text-2xl font-bold">Создать аккаунт</h1>
        <p class="mt-2 text-sm text-muted-foreground">Начни отслеживать свои привычки</p>
        <form class="mt-8 space-y-5" @submit.prevent="submit">
            <label class="block text-sm font-medium">Имя<input v-model="form.name" required autocomplete="name" maxlength="255" class="mt-2 w-full rounded-lg border border-neutral-300 bg-transparent p-3 dark:border-neutral-700" /><span v-if="form.errors.name" class="text-red-600">{{ form.errors.name }}</span></label>
            <label class="block text-sm font-medium">Email<input v-model="form.email" type="email" required autocomplete="email" class="mt-2 w-full rounded-lg border border-neutral-300 bg-transparent p-3 dark:border-neutral-700" /><span v-if="form.errors.email" class="text-red-600">{{ form.errors.email }}</span></label>
            <label class="block text-sm font-medium">Пароль<input v-model="form.password" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-neutral-300 bg-transparent p-3 dark:border-neutral-700" /><span v-if="form.errors.password" class="text-red-600">{{ form.errors.password }}</span></label>
            <label class="block text-sm font-medium">Повтори пароль<input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-neutral-300 bg-transparent p-3 dark:border-neutral-700" /></label>
            <button :disabled="form.processing" class="w-full rounded-xl bg-[#5386e4] p-3 font-semibold text-white disabled:opacity-50">Зарегистрироваться</button>
        </form>
        <p class="mt-6 text-center text-sm">Уже зарегистрирован? <Link href="/login" class="text-[#5386e4] underline">Войти</Link></p>
    </div>
</template>
