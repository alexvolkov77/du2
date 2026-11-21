<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ linked: boolean; link_url: string | null }>();
const form = useForm({});
const error = ref('');

function generateLink() {
    error.value = '';
    form.post('/settings/telegram/link', {
        preserveScroll: true,
        onError: (errors) => { error.value = errors.telegram || 'Не удалось создать ссылку.'; },
    });
}
</script>

<template>
    <Head title="Привязка Telegram" />
    <main class="mx-auto w-full max-w-2xl space-y-6 p-4 pt-8 md:p-10">
        <Link href="/dashboard" class="text-sm font-medium text-[#5386e4] hover:underline">← Мои привычки</Link>
        <h1 class="text-3xl font-bold">Привязать Telegram</h1>
        <section class="space-y-5 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
            <template v-if="linked">
                <p class="font-semibold text-[#5386e4]">✓ Telegram привязан к твоему аккаунту</p>
                <p class="text-sm text-muted-foreground">Привычки синхронизируются с Mini App. Напоминания можно включить в кабинете.</p>
                <a href="https://t.me/softskillsimprovement_bot" target="_blank" rel="noopener noreferrer" class="inline-block rounded-xl bg-[#5386e4] px-5 py-3 font-semibold text-white">Открыть бота</a>
            </template>
            <template v-else>
                <p class="text-sm text-muted-foreground">Привяжи Telegram к существующему email-аккаунту. Привычки и отметки сохранятся.</p>
                <div v-if="link_url" class="space-y-3">
                    <p class="text-sm">Ссылка действует 10 минут и используется один раз.</p>
                    <a :href="link_url" target="_blank" rel="noopener noreferrer" class="inline-block rounded-xl bg-[#5386e4] px-5 py-3 font-semibold text-white">Открыть бота и подтвердить привязку</a>
                    <p class="text-sm text-muted-foreground">В Telegram нажми «Start» или «Запустить». Бот сообщит об успешной привязке. Затем обнови эту страницу.</p>
                </div>
                <form @submit.prevent="generateLink">
                    <button type="submit" :disabled="form.processing" class="rounded-xl border border-[#5386e4] px-5 py-3 font-semibold text-[#5386e4] disabled:opacity-60">
                        {{ form.processing ? 'Создаём…' : link_url ? 'Создать новую ссылку' : 'Получить ссылку для привязки' }}
                    </button>
                </form>
                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            </template>
        </section>
    </main>
</template>
