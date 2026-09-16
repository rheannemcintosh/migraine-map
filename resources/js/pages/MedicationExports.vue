<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Download, FileDown, Plus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import MedicationTabs from '@/components/MedicationTabs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { medicationExports, medications as medicationsRoute } from '@/routes';
import { download, store } from '@/routes/medication-exports';

type Export = {
    id: number;
    from_date: string;
    to_date: string;
    include_ad_hoc: boolean;
    include_regular: boolean;
    file_name: string;
    created_at: string | null;
};

type Props = {
    exports: Export[];
    defaultFromDate: string;
    defaultToDate: string;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Medications',
                href: medicationsRoute(),
            },
            {
                title: 'Export',
                href: medicationExports(),
            },
        ],
    },
});

type ExportForm = {
    from_date: string;
    to_date: string;
    include_ad_hoc: boolean;
    include_regular: boolean;
};

const emptyForm = (): ExportForm => ({
    from_date: props.defaultFromDate,
    to_date: props.defaultToDate,
    include_ad_hoc: true,
    include_regular: false,
});

const isOpen = ref(false);

const form = useForm<ExportForm>(emptyForm());

const open = (): void => {
    form.defaults(emptyForm());
    form.reset();
    form.clearErrors();
    isOpen.value = true;
};

const close = (): void => {
    isOpen.value = false;
    form.reset();
    form.clearErrors();
};

const validate = (): boolean => {
    form.clearErrors();

    if (form.from_date === '') {
        form.setError('from_date', 'The from date field is required.');
    }

    if (form.to_date === '') {
        form.setError('to_date', 'The to date field is required.');
    }

    if (
        form.from_date !== '' &&
        form.to_date !== '' &&
        form.from_date > form.to_date
    ) {
        form.setError(
            'to_date',
            'The to date must be the same as or after the from date.',
        );
    }

    return !form.hasErrors;
};

// The server flashes the download URL for the new export so the file can be
// fetched in place, without navigating away from this tab.
const submit = (): void => {
    if (!validate()) {
        return;
    }

    form.post(store.url(), {
        preserveScroll: true,
        onFlash: (flash) => {
            if (typeof flash.download === 'string') {
                window.location.assign(flash.download);
            }
        },
        onSuccess: () => close(),
    });
};

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const formatDate = (date: string): string =>
    dateFormatter.format(new Date(`${date}T00:00:00`));

const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const formatDateTime = (value: string | null): string =>
    value === null ? '' : dateTimeFormatter.format(new Date(value));

const includes = (item: Export): string[] => {
    const parts = ['Migraine scores'];

    if (item.include_ad_hoc) {
        parts.push('Ad-hoc medications');
    }

    if (item.include_regular) {
        parts.push('Regular medications');
    }

    return parts;
};
</script>

<template>
    <Head title="Export" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <MedicationTabs />

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Export</h1>

            <Button class="cursor-pointer" @click="open">
                <Plus />
                New export
            </Button>
        </div>

        <div
            v-if="exports.length === 0"
            class="border-sidebar-border/70 dark:border-sidebar-border flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-10 text-center"
        >
            <FileDown class="text-muted-foreground size-10" />
            <div class="space-y-1">
                <p class="font-medium">No exports yet</p>
                <p class="text-muted-foreground text-sm">
                    Export your migraine scores and medications as a CSV file to
                    keep your own records or share them with a healthcare
                    provider.
                </p>
            </div>
            <Button variant="outline" class="cursor-pointer" @click="open">
                <Plus />
                Create your first export
            </Button>
        </div>

        <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li
                v-for="item in exports"
                :key="item.id"
                class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="font-semibold">
                            {{ formatDate(item.from_date) }} &ndash;
                            {{ formatDate(item.to_date) }}
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            Created {{ formatDateTime(item.created_at) }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 shrink-0"
                        :aria-label="`Download ${item.file_name}`"
                        as-child
                    >
                        <a
                            :href="download.url(item.id)"
                            :download="item.file_name"
                        >
                            <Download class="size-4" />
                        </a>
                    </Button>
                </div>
                <ul class="flex flex-wrap gap-1">
                    <li v-for="part in includes(item)" :key="part">
                        <Badge variant="outline">{{ part }}</Badge>
                    </li>
                </ul>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>New export</DialogTitle>
                <DialogDescription>
                    Choose the dates to cover and which medications to include.
                    Your migraine scores are always included.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="from_date">From</Label>
                        <Input
                            id="from_date"
                            v-model="form.from_date"
                            type="date"
                            :max="form.to_date || undefined"
                        />
                        <InputError :message="form.errors.from_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="to_date">To</Label>
                        <Input
                            id="to_date"
                            v-model="form.to_date"
                            type="date"
                            :min="form.from_date || undefined"
                        />
                        <InputError :message="form.errors.to_date" />
                    </div>
                </div>

                <div class="grid gap-3">
                    <Label
                        for="include_ad_hoc"
                        class="flex items-center space-x-3"
                    >
                        <Checkbox
                            id="include_ad_hoc"
                            v-model="form.include_ad_hoc"
                        />
                        <span>Include medications taken (ad-hoc)</span>
                    </Label>
                    <InputError :message="form.errors.include_ad_hoc" />

                    <Label
                        for="include_regular"
                        class="flex items-center space-x-3"
                    >
                        <Checkbox
                            id="include_regular"
                            v-model="form.include_regular"
                        />
                        <span>Include medications taken (regular)</span>
                    </Label>
                    <InputError :message="form.errors.include_regular" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="cursor-pointer"
                        @click="close"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        class="cursor-pointer"
                        :disabled="form.processing"
                    >
                        <Download />
                        Export CSV
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
