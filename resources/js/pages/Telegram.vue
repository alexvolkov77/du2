<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ configured: boolean; linked: boolean }>();
const page = usePage();
const hasAccount = computed(() => Boolean((page.props.auth as { user?: unknown })?.user));
const loading = ref(false);
const isMiniApp = ref(false);
const error = ref('');
const available = ref(false);

type TelegramWebApp = { initData: string; ready: () => void; expand: () => void };
function webApp(): TelegramWebApp | undefined {
    return (window as unknown as { Telegram?: { WebApp?: TelegramWebApp } }).Telegram?.WebApp;
}

function signIn(create = false) {
    const initData = webApp()?.initData;
    if (!initData || !props.configured) {
        error.value = 'Открой приложение через Telegram после настройки бота.';
        return;
    }

    error.value = '';
    loading.value = true;
    router.post('/telegram/session', { init_data: initData, create_if_missing: create }, {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.telegram !== 'ACCOUNT_NOT_LINKED') {
                error.value = errors.telegram || errors.init_data || 'Ошибка входа через Telegram.';
            }
        },
        onFinish: () => { loading.value = false; },
    });
}

onMounted(() => {
    const tg = webApp();
    isMiniApp.value = !!tg;
    if (!tg?.initData) return;
    available.value = true;
    tg.ready();
    tg.expand();
    if (props.configured && !hasAccount.value) signIn(false);
});
</script>

<template>
    <Head title="Telegram — Школа Душноты" />
    <div class="min-h-screen bg-[#fcfaf6] px-5 py-12 text-[#171717]">
        <main class="mx-auto max-w-md space-y-6">
            <p class="text-sm font-semibold text-[#5386e4]">ШКОЛА ДУШНОТЫ</p>
            <h1 class="text-3xl font-bold">Трекер привычек</h1>
            <p class="text-neutral-600">Одни привычки на сайте и в Telegram.</p>

            <div v-if="!configured" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
                Для входа через Telegram нужно настроить токен бота на сервере.
            </div>
            <div v-else-if="!available" class="rounded-xl border bg-white p-4 text-sm">
                Открой мини-приложение из <a href="https://t.me/softskillsimprovement_bot" class="font-semibold text-[#5386e4]">@softskillsimprovement_bot</a>.
            </div>

            <template v-if="hasAccount">
                <div v-if="linked" class="rounded-xl bg-white p-4">Telegram уже привязан к этому аккаунту.</div>
                <button v-else-if="available" :disabled="loading" @click="signIn(false)"
                    class="w-full rounded-xl bg-[#5386e4] px-5 py-4 font-semibold text-white disabled:opacity-60">
                    {{ loading ? 'Проверяем…' : 'Привязать Telegram к текущему аккаунту' }}
                </button>
                <Link href="/dashboard" class="block rounded-xl border bg-white px-5 py-4 text-center font-semibold">Мои привычки</Link>
            </template>
            <template v-else-if="available && configured">
                <button :disabled="loading" @click="signIn(true)"
                    class="w-full rounded-xl bg-[#5386e4] px-5 py-4 font-semibold text-white disabled:opacity-60">
                    {{ loading ? 'Проверяем…' : 'Начать через Telegram' }}
                </button>
                <Link href="/telegram/email" class="block rounded-xl border bg-white px-5 py-4 text-center font-semibold">
                    Уже есть аккаунт на сайте — войти по email
                </Link>
                <p class="text-sm text-neutral-600">Если привычки уже есть на сайте, сначала войди по email — Telegram привяжется к этому аккаунту.</p>
            </template>

            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            <p v-if="isMiniApp && !available" class="text-sm text-neutral-600">Данные запуска Telegram пока недоступны.</p>
        </main>
    </div>
</template>
