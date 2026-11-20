<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Habit = { id: number; name: string; completed_today: boolean; completed_7_days: number };
const props = defineProps<{ habits: Habit[]; today: string }>();
const form = useForm({ name: '' });
const activeId = ref<number | null>(null);
const completedCount = computed(() => props.habits.filter(habit => habit.completed_today).length);
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

function createHabit() {
    form.post('/habits', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function toggle(habit: Habit) {
    activeId.value = habit.id;
    router.visit(`/habits/${habit.id}/complete`, {
        method: habit.completed_today ? 'delete' : 'post',
        data: { timezone },
        preserveScroll: true,
        onFinish: () => { activeId.value = null; },
    });
}

function remove(habit: Habit) {
    if (!confirm(`Удалить привычку «${habit.name}»?`)) return;
    router.delete(`/habits/${habit.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Мои привычки" />
    <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-7 p-4 pt-8 md:p-10">
        <div>
            <p class="mb-2 text-sm font-semibold text-[#5386e4]">ШКОЛА ДУШНОТЫ</p>
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">Мои привычки</h1>
            <p class="mt-2 text-sm text-muted-foreground">Маленькие шаги каждый день. Сегодня выполнено {{ completedCount }} из {{ habits.length }}.</p>
            <div class="mt-5 h-3 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-800">
                <div class="h-full rounded-full bg-[#5386e4] transition-all" :style="{ width: `${habits.length ? completedCount / habits.length * 100 : 0}%` }"></div>
            </div>
        </div>

        <section class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
            <h2 class="mb-3 text-lg font-semibold">Добавить привычку</h2>
            <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="createHabit">
                <input v-model="form.name" type="text" maxlength="120" required placeholder="Например, читать 20 минут" class="min-w-0 flex-1 rounded-xl border border-neutral-300 bg-transparent px-4 py-3 text-sm outline-none focus:border-[#5386e4] dark:border-neutral-700" />
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-[#5386e4] px-6 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">Добавить</button>
            </form>
            <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</p>
        </section>

        <section>
            <h2 class="mb-4 text-xl font-semibold">Сегодня</h2>
            <div v-if="!habits.length" class="rounded-2xl border border-dashed border-neutral-300 p-10 text-center text-sm text-muted-foreground dark:border-neutral-700">Пока нет привычек. Добавь первую, чтобы начать.</div>
            <div v-else class="grid gap-3">
                <article v-for="habit in habits" :key="habit.id" class="flex items-center gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
                    <button type="button" :disabled="activeId === habit.id" :aria-label="habit.completed_today ? 'Отменить выполнение' : 'Отметить выполнение'" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 text-xl font-bold transition disabled:opacity-50" :class="habit.completed_today ? 'border-[#5386e4] bg-[#5386e4] text-white' : 'border-neutral-300 text-neutral-400 hover:border-[#5386e4] dark:border-neutral-600'" @click="toggle(habit)">{{ habit.completed_today ? '✓' : '' }}</button>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium" :class="habit.completed_today ? 'text-muted-foreground line-through' : ''">{{ habit.name }}</div>
                        <div class="mt-1 text-xs text-muted-foreground">За 7 дней: {{ habit.completed_7_days }} из 7</div>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-2 text-xs text-muted-foreground hover:bg-neutral-100 hover:text-red-600 dark:hover:bg-neutral-800" @click="remove(habit)">Удалить</button>
                </article>
            </div>
        </section>
    </main>
</template>
