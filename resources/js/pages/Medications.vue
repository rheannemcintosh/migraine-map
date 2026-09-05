<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pill, Plus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { medications as medicationsRoute } from '@/routes';
import { store } from '@/routes/medications';

type Medication = {
    id: number;
    name: string;
    dose_amount: number;
    dose_unit: string;
    frequency: string;
    is_prescription: boolean;
    is_active: boolean;
};

type FrequencyOption = {
    value: string;
    label: string;
};

type Props = {
    medications: Medication[];
    doseUnits: string[];
    frequencies: FrequencyOption[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Medications',
                href: medicationsRoute(),
            },
        ],
    },
});

const isOpen = ref(false);

const form = useForm<{
    name: string;
    dose_amount: string;
    dose_unit: string;
    frequency: string;
    is_prescription: boolean;
    is_active: boolean;
}>({
    name: '',
    dose_amount: '',
    dose_unit: 'mg',
    frequency: 'ad_hoc',
    is_prescription: false,
    is_active: true,
});

const open = (): void => {
    form.reset();
    form.clearErrors();
    isOpen.value = true;
};

const close = (): void => {
    isOpen.value = false;
    form.reset();
    form.clearErrors();
};

const submit = (): void => {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

const formatDose = (medication: Medication): string =>
    `${medication.dose_amount} ${medication.dose_unit}`;
</script>

<template>
    <Head title="Medications" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Medications</h1>

            <Button class="cursor-pointer" @click="open">
                <Plus />
                Add medication
            </Button>
        </div>

        <div
            v-if="medications.length === 0"
            class="border-sidebar-border/70 dark:border-sidebar-border flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-10 text-center"
        >
            <Pill class="text-muted-foreground size-10" />
            <div class="space-y-1">
                <p class="font-medium">No medications yet</p>
                <p class="text-muted-foreground text-sm">
                    Add the medications you take so they can be linked to your
                    migraine patterns later.
                </p>
            </div>
            <Button variant="outline" class="cursor-pointer" @click="open">
                <Plus />
                Add your first medication
            </Button>
        </div>

        <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li
                v-for="medication in medications"
                :key="medication.id"
                class="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-2 rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-semibold">{{ medication.name }}</h2>
                    <Badge
                        :variant="
                            medication.is_active ? 'default' : 'secondary'
                        "
                    >
                        {{ medication.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                </div>
                <dl class="text-muted-foreground grid gap-1 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt>Dose</dt>
                        <dd class="text-foreground">
                            {{ formatDose(medication) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Frequency</dt>
                        <dd class="text-foreground">
                            {{ medication.frequency }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Type</dt>
                        <dd class="text-foreground">
                            {{
                                medication.is_prescription
                                    ? 'Prescription'
                                    : 'Over the counter'
                            }}
                        </dd>
                    </div>
                </dl>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add medication</DialogTitle>
                <DialogDescription>
                    Record a medication you take. Combination medicines are
                    recorded as one medication with a single dose.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        autofocus
                        placeholder="e.g. Sumatriptan"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="dose_amount">Dose</Label>
                        <Input
                            id="dose_amount"
                            v-model="form.dose_amount"
                            type="number"
                            inputmode="decimal"
                            step="any"
                            required
                            placeholder="e.g. 50"
                        />
                        <InputError :message="form.errors.dose_amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="dose_unit">Unit</Label>
                        <Select v-model="form.dose_unit">
                            <SelectTrigger id="dose_unit" class="w-full">
                                <SelectValue placeholder="Select a unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="unit in doseUnits"
                                    :key="unit"
                                    :value="unit"
                                >
                                    {{ unit }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.dose_unit" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="frequency">Frequency</Label>
                    <Select v-model="form.frequency">
                        <SelectTrigger id="frequency" class="w-full">
                            <SelectValue placeholder="Select a frequency" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in frequencies"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.frequency" />
                </div>

                <div class="grid gap-3">
                    <Label
                        for="is_prescription"
                        class="flex items-center space-x-3"
                    >
                        <Checkbox
                            id="is_prescription"
                            v-model="form.is_prescription"
                        />
                        <span>This is a prescription medication</span>
                    </Label>
                    <InputError :message="form.errors.is_prescription" />

                    <Label for="is_active" class="flex items-center space-x-3">
                        <Checkbox id="is_active" v-model="form.is_active" />
                        <span
                            >Currently active (I can still access and take
                            it)</span
                        >
                    </Label>
                    <InputError :message="form.errors.is_active" />
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
                        Save medication
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
