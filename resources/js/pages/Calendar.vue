<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { calendar } from '@/routes';
import { store } from '@/routes/migraine-scores';

type Props = {
    year: number;
    today: string;
    scores: Record<string, number>;
};

type DayCell =
    | { kind: 'nonexistent'; key: string }
    | { kind: 'future'; key: string; date: string }
    | { kind: 'unscored'; key: string; date: string }
    | { kind: 'scored'; key: string; date: string; score: number };

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Calendar',
                href: calendar(),
            },
        ],
    },
});

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

const SCORE_OPTIONS = Array.from({ length: 11 }, (_, i) => i);

const pad = (n: number): string => String(n).padStart(2, '0');

const daysInMonth = (year: number, month: number): number =>
    new Date(year, month + 1, 0).getDate();

const rows = computed<DayCell[][]>(() =>
    Array.from({ length: 31 }, (_, dayIndex) => {
        const day = dayIndex + 1;

        return MONTHS.map((_, month): DayCell => {
            const key = `${props.year}-${pad(month + 1)}-${pad(day)}`;

            if (day > daysInMonth(props.year, month)) {
                return { kind: 'nonexistent', key };
            }

            if (key > props.today) {
                return { kind: 'future', key, date: key };
            }

            const score = props.scores[key];

            if (score !== undefined) {
                return { kind: 'scored', key, date: key, score };
            }

            return { kind: 'unscored', key, date: key };
        });
    }),
);

const cellClass: Record<DayCell['kind'], string> = {
    nonexistent: 'bg-muted/40 text-transparent',
    future: 'bg-muted text-muted-foreground/50',
    unscored:
        'cursor-pointer bg-red-500 text-white hover:bg-red-600 focus-visible:ring-2 focus-visible:ring-ring',
    scored: 'bg-emerald-500 text-white',
};

const selectedDate = ref<string | null>(null);
const isOpen = computed({
    get: () => selectedDate.value !== null,
    set: (open: boolean) => {
        if (!open) {
            close();
        }
    },
});

const form = useForm<{ date: string; score: number | null }>({
    date: '',
    score: null,
});

const openDay = (cell: DayCell): void => {
    if (cell.kind !== 'unscored') {
        return;
    }

    form.reset();
    form.clearErrors();
    form.date = cell.date;
    selectedDate.value = cell.date;
};

const close = (): void => {
    selectedDate.value = null;
    form.reset();
    form.clearErrors();
};

const submit = (): void => {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

const formattedSelectedDate = computed(() =>
    selectedDate.value
        ? new Date(`${selectedDate.value}T00:00:00`).toLocaleDateString(
              undefined,
              {
                  weekday: 'long',
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              },
          )
        : '',
);
</script>

<template>
    <Head :title="`Calendar ${year}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <Button as-child variant="outline" size="icon">
                <Link
                    :href="calendar(year - 1)"
                    :aria-label="`Previous year (${year - 1})`"
                >
                    <ChevronLeft />
                </Link>
            </Button>

            <h1 class="text-2xl font-semibold tracking-tight">{{ year }}</h1>

            <Button as-child variant="outline" size="icon">
                <Link
                    :href="calendar(year + 1)"
                    :aria-label="`Next year (${year + 1})`"
                >
                    <ChevronRight />
                </Link>
            </Button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full table-fixed border-separate border-spacing-1">
                <thead>
                    <tr>
                        <th class="w-8"></th>
                        <th
                            v-for="month in MONTHS"
                            :key="month"
                            class="text-muted-foreground text-xs font-medium"
                        >
                            {{ month }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, dayIndex) in rows" :key="dayIndex">
                        <th
                            class="text-muted-foreground text-right text-xs font-medium"
                        >
                            {{ dayIndex + 1 }}
                        </th>
                        <td v-for="cell in row" :key="cell.key" class="p-0">
                            <button
                                v-if="cell.kind === 'unscored'"
                                type="button"
                                class="flex h-7 w-full items-center justify-center rounded text-xs"
                                :class="cellClass[cell.kind]"
                                :aria-label="`Log score for ${cell.date}`"
                                :data-date="cell.date"
                                @click="openDay(cell)"
                            />
                            <div
                                v-else
                                class="flex h-7 w-full items-center justify-center rounded text-xs font-semibold"
                                :class="cellClass[cell.kind]"
                                :data-date="
                                    cell.kind === 'nonexistent'
                                        ? undefined
                                        : cell.date
                                "
                                :title="
                                    cell.kind === 'scored'
                                        ? `${cell.date}: score ${cell.score}`
                                        : undefined
                                "
                            >
                                {{ cell.kind === 'scored' ? cell.score : '' }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="text-muted-foreground flex flex-wrap gap-4 text-xs"
            aria-label="Legend"
        >
            <span class="flex items-center gap-1.5">
                <span class="size-3 rounded bg-red-500" /> Needs a score
            </span>
            <span class="flex items-center gap-1.5">
                <span class="size-3 rounded bg-emerald-500" /> Scored
            </span>
            <span class="flex items-center gap-1.5">
                <span class="bg-muted size-3 rounded" /> Future
            </span>
            <span class="flex items-center gap-1.5">
                <span class="bg-muted/40 size-3 rounded" /> Not a date
            </span>
        </div>
    </div>

    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Log migraine score</DialogTitle>
                <DialogDescription>
                    {{ formattedSelectedDate }}. Choose a score from 0 (no
                    migraine) to 10 (worst possible).
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div
                    class="grid grid-cols-6 gap-2 sm:grid-cols-11"
                    role="radiogroup"
                    aria-label="Score"
                >
                    <Button
                        v-for="option in SCORE_OPTIONS"
                        :key="option"
                        type="button"
                        role="radio"
                        :aria-checked="form.score === option"
                        :variant="form.score === option ? 'default' : 'outline'"
                        size="icon"
                        @click="form.score = option"
                    >
                        {{ option }}
                    </Button>
                </div>
                <InputError :message="form.errors.score" />
                <InputError :message="form.errors.date" />

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.score === null || form.processing"
                    >
                        Save score
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
