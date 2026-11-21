<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type ScheduleType = 'daily' | 'weekly';
type HistoryDay = {
    date: string;
    weekday: number;
    scheduled: boolean;
    completed: boolean;
};
type Habit = {
    id: number;
    name: string;
    schedule_type: ScheduleType;
    schedule_days: number[];
    scheduled_today: boolean;
    completed_today: boolean;
    completed_7_days: number;
    scheduled_7_days: number;
    history: HistoryDay[];
    reminder_enabled: boolean;
    reminder_time: string;
};

const props = defineProps<{ habits: Habit[]; today: string; timezone: string; telegram_reminders_ready: boolean; telegram_linked: boolean }>();
const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

const weekdays = [
    { id: 1, label: 'Пн' },
    { id: 2, label: 'Вт' },
    { id: 3, label: 'Ср' },
    { id: 4, label: 'Чт' },
    { id: 5, label: 'Пт' },
    { id: 6, label: 'Сб' },
    { id: 7, label: 'Вс' },
];

const createForm = useForm({
    name: '',
    schedule_type: 'daily' as ScheduleType,
    schedule_days: [] as number[],
    timezone: browserTimezone,
});

const editForm = useForm({
    name: '',
    schedule_type: 'daily' as ScheduleType,
    schedule_days: [] as number[],
});

const activeId = ref<number | null>(null);
const editingId = ref<number | null>(null);
const actionError = ref('');

const todayHabits = computed(() => props.habits.filter((habit) => habit.scheduled_today));
const otherHabits = computed(() => props.habits.filter((habit) => !habit.scheduled_today));
const doneToday = computed(() => todayHabits.value.filter((habit) => habit.completed_today).length);
const groups = computed(() => [
    { label: 'План на сегодня', habits: todayHabits.value },
    { label: 'Не запланированы на сегодня', habits: otherHabits.value },
]);

onMounted(() => {
    if (props.timezone !== browserTimezone) {
        router.get('/dashboard', { timezone: browserTimezone }, {
            replace: true,
            preserveScroll: true,
            preserveState: true,
        });
    }
});

function toggleDay(days: number[], day: number): number[] {
    return days.includes(day)
        ? days.filter((value) => value !== day)
        : [...days, day].sort((a, b) => a - b);
}

function scheduleLabel(habit: Habit): string {
    if (habit.schedule_type === 'daily') return 'Каждый день';
    return habit.schedule_days
        .map((day) => weekdays.find((weekday) => weekday.id === day)?.label)
        .filter(Boolean)
        .join(', ');
}

function createHabit() {
    actionError.value = '';
    createForm.post('/habits', {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name', 'schedule_type', 'schedule_days'),
    });
}

function startEditing(habit: Habit) {
    editingId.value = habit.id;
    editForm.clearErrors();
    editForm.name = habit.name;
    editForm.schedule_type = habit.schedule_type;
    editForm.schedule_days = [...habit.schedule_days];
}

function saveEdit() {
    if (editingId.value === null) return;
    editForm.patch(`/habits/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
}

function toggle(habit: Habit) {
    if (!habit.scheduled_today && !habit.completed_today) return;
    activeId.value = habit.id;
    actionError.value = '';
    router.visit(`/habits/${habit.id}/complete`, {
        method: habit.completed_today ? 'delete' : 'post',
        data: { timezone: browserTimezone },
        preserveScroll: true,
        onError: (errors) => { actionError.value = errors.schedule || ''; },
        onFinish: () => { activeId.value = null; },
    });
}

function saveReminder(habit: Habit, enabled: boolean, time: string) {
    actionError.value = '';
    router.patch(`/habits/${habit.id}/reminder`, {
        enabled,
        time,
        timezone: browserTimezone,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            actionError.value = errors.time || errors.enabled || 'Не удалось сохранить напоминание.';
        },
    });
}

function updateReminderTime(habit: Habit, event: Event) {
    const input = event.target as HTMLInputElement;
    saveReminder(habit, habit.reminder_enabled, input.value);
}

function remove(habit: Habit) {
    if (!confirm(`Удалить привычку «${habit.name}» и все её отметки?`)) return;
    router.delete(`/habits/${habit.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingId.value === habit.id) editingId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Мои привычки" />
    <main class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-7 p-4 pt-8 md:p-10">
        <header>
            <p class="mb-2 text-sm font-semibold text-[#5386e4]">ШКОЛА ДУШНОТЫ</p>
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">Мои привычки</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Сегодня выполнено {{ doneToday }} из {{ todayHabits.length }} запланированных.
            </p>
            <div v-if="!telegram_linked" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#5386e4]/30 bg-[#5386e4]/5 p-4">
                <p class="text-sm">Привяжи Telegram к своему аккаунту — привычки и отметки сохранятся.</p>
                <Link href="/settings/telegram" class="rounded-lg bg-[#5386e4] px-4 py-2 text-sm font-semibold text-white">Привязать Telegram</Link>
            </div>
            <p v-else-if="!telegram_reminders_ready" class="mt-3 text-sm text-muted-foreground">
                Для доставки напоминаний открой
                <a class="font-medium text-[#5386e4] underline" href="https://t.me/softskillsimprovement_bot?start=reminders" target="_blank" rel="noopener noreferrer">
                    @softskillsimprovement_bot
                </a>,
                войди в свой аккаунт через Mini App и отправь боту /start.
            </p>

            <div class="mt-5 h-3 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-800">
                <div
                    class="h-full rounded-full bg-[#5386e4] transition-all"
                    :style="{ width: `${todayHabits.length ? (doneToday / todayHabits.length) * 100 : 0}%` }"
                />
            </div>
        </header>

        <section class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
            <h2 class="mb-4 text-lg font-semibold">Добавить привычку</h2>
            <form class="space-y-4" @submit.prevent="createHabit">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input
                        v-model="createForm.name"
                        type="text"
                        maxlength="120"
                        required
                        placeholder="Например, читать 20 минут"
                        class="min-w-0 flex-1 rounded-xl border border-neutral-300 bg-transparent px-4 py-3 text-sm outline-none focus:border-[#5386e4] dark:border-neutral-700"
                    />
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="rounded-xl bg-[#5386e4] px-6 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
                    >
                        Добавить
                    </button>
                </div>

                <fieldset class="space-y-3">
                    <legend class="mb-2 text-sm font-medium">Как часто?</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input v-model="createForm.schedule_type" type="radio" value="daily" />
                            Каждый день
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="createForm.schedule_type" type="radio" value="weekly" />
                            В выбранные дни
                        </label>
                    </div>
                    <div v-if="createForm.schedule_type === 'weekly'" class="flex flex-wrap gap-2">
                        <button
                            v-for="day in weekdays"
                            :key="day.id"
                            type="button"
                            :aria-pressed="createForm.schedule_days.includes(day.id)"
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="createForm.schedule_days.includes(day.id) ? 'border-[#5386e4] bg-[#5386e4] text-white' : 'border-neutral-300 dark:border-neutral-700'"
                            @click="createForm.schedule_days = toggleDay(createForm.schedule_days, day.id)"
                        >
                            {{ day.label }}
                        </button>
                    </div>
                </fieldset>
                <p v-if="createForm.errors.name" class="text-sm text-red-600">{{ createForm.errors.name }}</p>
                <p v-if="createForm.errors.schedule_days" class="text-sm text-red-600">{{ createForm.errors.schedule_days }}</p>
            </form>
        </section>

        <div v-if="!habits.length" class="rounded-2xl border border-dashed border-neutral-300 p-10 text-center text-sm text-muted-foreground dark:border-neutral-700">
            Пока нет привычек. Добавь первую, чтобы начать.
        </div>

        <template v-else>
        <section v-for="group in groups" :key="group.label" class="space-y-3">
            <h2 class="text-xl font-semibold">{{ group.label }} <span class="text-sm font-normal text-muted-foreground">({{ group.habits.length }})</span></h2>
            <p v-if="!group.habits.length && group.label === 'План на сегодня'" class="rounded-xl border border-dashed p-5 text-sm text-muted-foreground">
                На сегодня ничего не запланировано.
            </p>
            <article
                v-for="habit in group.habits"
                :key="habit.id"
                class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="activeId === habit.id || (!habit.scheduled_today && !habit.completed_today)"
                        :aria-label="habit.completed_today ? 'Отменить выполнение' : habit.scheduled_today ? 'Отметить выполнение' : 'Не запланировано'"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 text-xl font-bold disabled:opacity-40"
                        :class="habit.completed_today ? 'border-[#5386e4] bg-[#5386e4] text-white' : 'border-neutral-300 text-neutral-400 hover:border-[#5386e4] dark:border-neutral-600'"
                        @click="toggle(habit)"
                    >
                        {{ habit.completed_today ? '✓' : '' }}
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium" :class="habit.completed_today ? 'text-muted-foreground line-through' : ''">{{ habit.name }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ scheduleLabel(habit) }} · За 7 дней: {{ habit.completed_7_days }} выполнено из {{ habit.scheduled_7_days }} запланированных
                        </p>
                    </div>
                    <button type="button" class="rounded-lg px-2 py-2 text-xs text-[#5386e4] hover:bg-neutral-100 dark:hover:bg-neutral-800" @click="startEditing(habit)">
                        Изменить
                    </button>
                    <button type="button" class="rounded-lg px-2 py-2 text-xs text-muted-foreground hover:bg-neutral-100 hover:text-red-600 dark:hover:bg-neutral-800" @click="remove(habit)">
                        Удалить
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl bg-[#5386e4]/5 px-3 py-2.5 text-sm dark:bg-[#5386e4]/10">
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            :checked="habit.reminder_enabled"
                            @change="saveReminder(habit, !habit.reminder_enabled, habit.reminder_time)"
                        />
                        Напоминать в Telegram
                    </label>
                    <label class="flex items-center gap-2">
                        <span class="text-muted-foreground">Время</span>
                        <input
                            type="time"
                            :value="habit.reminder_time"
                            class="rounded-lg border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                            @change="updateReminderTime(habit, $event)"
                        />
                    </label>
                    <span v-if="habit.reminder_enabled" class="text-xs text-muted-foreground">
                        По расписанию · {{ browserTimezone }}
                    </span>
                </div>

                <form v-if="editingId === habit.id" class="mt-4 space-y-3 rounded-xl bg-neutral-50 p-4 dark:bg-neutral-900" @submit.prevent="saveEdit">
                    <input
                        v-model="editForm.name"
                        type="text"
                        required
                        maxlength="120"
                        class="w-full rounded-lg border border-neutral-300 bg-transparent px-3 py-2 text-sm dark:border-neutral-700"
                    />
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input v-model="editForm.schedule_type" type="radio" value="daily" />
                            Каждый день
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="editForm.schedule_type" type="radio" value="weekly" />
                            В выбранные дни
                        </label>
                    </div>
                    <div v-if="editForm.schedule_type === 'weekly'" class="flex flex-wrap gap-2">
                        <button
                            v-for="day in weekdays"
                            :key="day.id"
                            type="button"
                            :aria-pressed="editForm.schedule_days.includes(day.id)"
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="editForm.schedule_days.includes(day.id) ? 'border-[#5386e4] bg-[#5386e4] text-white' : 'border-neutral-300 dark:border-neutral-700'"
                            @click="editForm.schedule_days = toggleDay(editForm.schedule_days, day.id)"
                        >
                            {{ day.label }}
                        </button>
                    </div>
                    <p v-if="editForm.errors.name" class="text-sm text-red-600">{{ editForm.errors.name }}</p>
                    <p v-if="editForm.errors.schedule_days" class="text-sm text-red-600">{{ editForm.errors.schedule_days }}</p>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-[#5386e4] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">Сохранить</button>
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="editingId = null">Отмена</button>
                    </div>
                </form>

                <div class="mt-4 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">История за 14 дней</p>
                    <div class="flex flex-wrap gap-1.5">
                        <div
                            v-for="day in habit.history"
                            :key="day.date"
                            :title="`${day.date}: ${day.completed ? 'выполнено' : day.scheduled ? 'запланировано, не выполнено' : 'не запланировано'}`"
                            :aria-label="`${day.date}: ${day.completed ? 'выполнено' : day.scheduled ? 'не выполнено' : 'не запланировано'}`"
                            class="flex h-11 w-8 flex-col items-center justify-center rounded-md text-xs"
                            :class="day.completed ? 'bg-[#5386e4] font-semibold text-white' : day.scheduled ? 'bg-[#edbf85]/60 text-neutral-900 dark:text-neutral-900' : 'bg-neutral-100 text-neutral-400 dark:bg-neutral-800'"
                        >
                            <span class="text-[10px] opacity-80">{{ day.date.slice(-2) }}</span>
                            <span class="font-bold">{{ day.completed ? '✓' : day.scheduled ? '·' : '–' }}</span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">Синий — выполнено · Бежевый — было запланировано · Серый — выходной от привычки.</p>
                </div>
            </article>
        </section>

        </template>

        <p v-if="actionError" class="text-sm text-red-600">{{ actionError }}</p>
    </main>
</template>
