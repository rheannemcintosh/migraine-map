<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Clock, Pencil, Pill, Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { medications as medicationsRoute } from '@/routes';
import { store, update } from '@/routes/medications';

type Schedule = {
    id: number;
    time_of_day: string | null;
    time: string | null;
    quantity: number;
    label: string;
};

type Ingredient = {
    id: number;
    name: string | null;
    dose_amount: number;
    dose_unit: string;
};

type Medication = {
    id: number;
    name: string;
    // Every ingredient with its dose, e.g. "Aspirin 300 mg, Caffeine 45 mg".
    dose: string;
    ingredients: Ingredient[];
    frequency: string;
    is_prescription: boolean;
    is_active: boolean;
    schedules: Schedule[];
};

type Option = {
    value: string;
    label: string;
};

type Props = {
    medications: Medication[];
    doseUnits: string[];
    frequencies: Option[];
    timesOfDay: Option[];
};

const props = defineProps<Props>();

const frequencyLabel = (value: string): string =>
    props.frequencies.find((option) => option.value === value)?.label ?? value;

// A dose is due either during a named period or at a specific clock time,
// and is made up of one or more units (e.g. two tablets).
type ScheduleInput = {
    time_of_day: string | null;
    time: string | null;
    // `<Input type="number">` makes v-model store a number, or '' when the field is empty.
    quantity: number | '';
};

// Sentinel value in the dose `Select` for "a specific clock time".
const SPECIFIC_TIME = 'time';

const newDose = (): ScheduleInput => ({
    time_of_day: 'morning',
    time: null,
    quantity: 1,
});

const doseKind = (dose: ScheduleInput): string =>
    dose.time_of_day ?? SPECIFIC_TIME;

const setDoseKind = (dose: ScheduleInput, kind: string): void => {
    if (kind === SPECIFIC_TIME) {
        dose.time_of_day = null;
        dose.time = dose.time ?? '08:00';
    } else {
        dose.time_of_day = kind;
        dose.time = null;
    }
};

// An active ingredient and its dose. A single-ingredient medication leaves
// the name empty; compound medications name each ingredient.
type IngredientInput = {
    name: string;
    // `<Input type="number">` makes v-model store a number, or '' when the field is empty.
    dose_amount: number | '';
    dose_unit: string;
};

const newIngredient = (): IngredientInput => ({
    name: '',
    dose_amount: '',
    dose_unit: 'mg',
});

type MedicationForm = {
    name: string;
    ingredients: IngredientInput[];
    frequency: string;
    is_prescription: boolean;
    is_active: boolean;
    schedules: ScheduleInput[];
};

const emptyForm = (): MedicationForm => ({
    name: '',
    ingredients: [newIngredient()],
    frequency: 'ad_hoc',
    is_prescription: false,
    is_active: true,
    schedules: [],
});

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

const form = useForm<MedicationForm>(emptyForm());

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

const validate = (target: typeof form): boolean => {
    target.clearErrors();

    if (target.name.trim() === '') {
        target.setError('name', 'The name field is required.');
    }

    const isCompound = target.ingredients.length > 1;

    target.ingredients.forEach((ingredient, index) => {
        if (isCompound && ingredient.name.trim() === '') {
            target.setError(
                `ingredients.${index}.name` as keyof MedicationForm,
                'Enter a name for this ingredient.',
            );
        }

        if (ingredient.dose_amount === '') {
            target.setError(
                `ingredients.${index}.dose_amount` as keyof MedicationForm,
                'The dose amount field is required.',
            );
        } else if (ingredient.dose_amount <= 0) {
            target.setError(
                `ingredients.${index}.dose_amount` as keyof MedicationForm,
                'The dose amount field must be greater than 0.',
            );
        }
    });

    target.schedules.forEach((dose, index) => {
        if (dose.time_of_day === null && (dose.time ?? '') === '') {
            target.setError(
                `schedules.${index}.time` as keyof MedicationForm,
                'Enter a time for this dose.',
            );
        }

        if (
            dose.quantity === '' ||
            dose.quantity < 1 ||
            !Number.isInteger(dose.quantity)
        ) {
            target.setError(
                `schedules.${index}.quantity` as keyof MedicationForm,
                'Enter how many units make up this dose.',
            );
        }
    });

    return !target.hasErrors;
};

const ingredientError = (
    target: typeof form,
    index: number,
): string | undefined => {
    const errors = target.errors as Record<string, string | undefined>;

    return (
        errors[`ingredients.${index}.name`] ??
        errors[`ingredients.${index}.dose_amount`] ??
        errors[`ingredients.${index}.dose_unit`] ??
        errors[`ingredients.${index}`]
    );
};

const addIngredient = (target: typeof form): void => {
    target.ingredients.push(newIngredient());
};

// Removing down to a single ingredient turns the medication back into a
// simple one, whose only ingredient is unnamed.
const removeIngredient = (target: typeof form, index: number): void => {
    target.ingredients.splice(index, 1);

    if (target.ingredients.length === 1) {
        target.ingredients[0].name = '';
    }
};

const scheduleError = (
    target: typeof form,
    index: number,
): string | undefined => {
    const errors = target.errors as Record<string, string | undefined>;

    return (
        errors[`schedules.${index}.time`] ??
        errors[`schedules.${index}.time_of_day`] ??
        errors[`schedules.${index}.quantity`] ??
        errors[`schedules.${index}`]
    );
};

const submit = (): void => {
    if (!validate(form)) {
        return;
    }

    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

const editingId = ref<number | null>(null);

const editForm = useForm<MedicationForm>(emptyForm());

const isEditOpen = computed({
    get: () => editingId.value !== null,
    set: (value: boolean) => {
        if (!value) {
            closeEdit();
        }
    },
});

const openEdit = (medication: Medication): void => {
    editForm.reset();
    editForm.clearErrors();
    editForm.name = medication.name;
    editForm.ingredients = medication.ingredients.map((ingredient) => ({
        name: ingredient.name ?? '',
        dose_amount: ingredient.dose_amount,
        dose_unit: ingredient.dose_unit,
    }));
    editForm.frequency = medication.frequency;
    editForm.is_prescription = medication.is_prescription;
    editForm.is_active = medication.is_active;
    editForm.schedules = medication.schedules.map((schedule) => ({
        time_of_day: schedule.time_of_day,
        time: schedule.time,
        quantity: schedule.quantity,
    }));
    editingId.value = medication.id;
};

const closeEdit = (): void => {
    editingId.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitEdit = (): void => {
    if (editingId.value === null || !validate(editForm)) {
        return;
    }

    editForm.patch(update.url(editingId.value), {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
};

// Prefix the dose with its quantity only when more than one unit is taken.
// A compound medication has no single dose, so only the quantity is shown.
const formatScheduledDose = (
    medication: Medication,
    schedule: Schedule,
): string => {
    if (schedule.quantity <= 1) {
        return medication.dose;
    }

    return medication.ingredients.length > 1
        ? `${schedule.quantity} x`
        : `${schedule.quantity} x ${medication.dose}`;
};
</script>

<template>
    <Head title="Medications" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <MedicationTabs />

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
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                medication.is_active ? 'default' : 'secondary'
                            "
                        >
                            {{ medication.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 cursor-pointer"
                            :aria-label="`Edit ${medication.name}`"
                            @click="openEdit(medication)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                    </div>
                </div>
                <dl class="text-muted-foreground grid gap-1 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt>
                            {{
                                medication.ingredients.length > 1
                                    ? 'Ingredients'
                                    : 'Dose'
                            }}
                        </dt>
                        <dd class="text-foreground text-right">
                            {{ medication.dose }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Frequency</dt>
                        <dd class="text-foreground">
                            {{ frequencyLabel(medication.frequency) }}
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
                    <div class="flex justify-between gap-2">
                        <dt>Schedule</dt>
                        <dd class="text-foreground text-right">
                            <span v-if="medication.schedules.length === 0">
                                Not scheduled
                            </span>
                            <ul v-else class="flex flex-wrap justify-end gap-1">
                                <li
                                    v-for="schedule in medication.schedules"
                                    :key="schedule.id"
                                >
                                    <Badge variant="outline" class="gap-1">
                                        <Clock
                                            v-if="schedule.time !== null"
                                            class="size-3"
                                        />
                                        {{ schedule.label }}
                                        <span
                                            v-if="schedule.quantity > 1"
                                            class="text-muted-foreground"
                                        >
                                            &middot;
                                            {{
                                                formatScheduledDose(
                                                    medication,
                                                    schedule,
                                                )
                                            }}
                                        </span>
                                    </Badge>
                                </li>
                            </ul>
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
                    Record a medication you take. For combination medicines, add
                    each active ingredient with its own dose.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        autofocus
                        placeholder="e.g. Sumatriptan"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label>{{
                            form.ingredients.length > 1 ? 'Ingredients' : 'Dose'
                        }}</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="cursor-pointer"
                            @click="addIngredient(form)"
                        >
                            <Plus />
                            Add ingredient
                        </Button>
                    </div>
                    <div
                        v-for="(ingredient, index) in form.ingredients"
                        :key="index"
                        class="grid gap-1"
                    >
                        <div class="flex items-start gap-2">
                            <Input
                                v-if="form.ingredients.length > 1"
                                v-model="ingredient.name"
                                type="text"
                                class="flex-1"
                                placeholder="e.g. Paracetamol"
                                :aria-label="`Ingredient ${index + 1} name`"
                            />
                            <Input
                                :id="`ingredient-${index}-dose_amount`"
                                v-model="ingredient.dose_amount"
                                type="number"
                                inputmode="decimal"
                                step="any"
                                class="w-24"
                                placeholder="e.g. 50"
                                :aria-label="`Ingredient ${index + 1} dose amount`"
                            />
                            <Select v-model="ingredient.dose_unit">
                                <SelectTrigger
                                    class="w-28"
                                    :aria-label="`Ingredient ${index + 1} dose unit`"
                                >
                                    <SelectValue placeholder="Unit" />
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
                            <Button
                                v-if="form.ingredients.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 cursor-pointer"
                                :aria-label="`Remove ingredient ${index + 1}`"
                                @click="removeIngredient(form, index)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                        <InputError :message="ingredientError(form, index)" />
                    </div>
                    <InputError :message="form.errors.ingredients" />
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

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label>Schedule</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="cursor-pointer"
                            @click="form.schedules.push(newDose())"
                        >
                            <Plus />
                            Add dose
                        </Button>
                    </div>
                    <p
                        v-if="form.schedules.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        No scheduled doses. Add one for each time of day this
                        medication is taken.
                    </p>
                    <div
                        v-for="(dose, index) in form.schedules"
                        :key="index"
                        class="grid gap-1"
                    >
                        <div class="flex items-center gap-2">
                            <Select
                                :model-value="doseKind(dose)"
                                @update:model-value="
                                    setDoseKind(dose, String($event))
                                "
                            >
                                <SelectTrigger
                                    :id="`dose-${index}`"
                                    class="flex-1"
                                    :aria-label="`Dose ${index + 1} time of day`"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in timesOfDay"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                    <SelectItem :value="SPECIFIC_TIME">
                                        Specific time
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                v-if="dose.time_of_day === null"
                                :model-value="dose.time ?? ''"
                                type="time"
                                @update:model-value="dose.time = String($event)"
                                class="w-32"
                                :aria-label="`Dose ${index + 1} time`"
                            />
                            <Input
                                v-model="dose.quantity"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                step="1"
                                class="w-16"
                                :aria-label="`Dose ${index + 1} quantity`"
                                title="Units per dose"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 cursor-pointer"
                                :aria-label="`Remove dose ${index + 1}`"
                                @click="form.schedules.splice(index, 1)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                        <InputError :message="scheduleError(form, index)" />
                    </div>
                    <InputError :message="form.errors.schedules" />
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

    <Dialog v-model:open="isEditOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Edit medication</DialogTitle>
                <DialogDescription>
                    Update the details of this medication. Changes are saved to
                    your medication list.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid gap-2">
                    <Label for="edit-name">Name</Label>
                    <Input
                        id="edit-name"
                        v-model="editForm.name"
                        type="text"
                        autofocus
                        placeholder="e.g. Sumatriptan"
                    />
                    <InputError :message="editForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label>{{
                            editForm.ingredients.length > 1
                                ? 'Ingredients'
                                : 'Dose'
                        }}</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="cursor-pointer"
                            @click="addIngredient(editForm)"
                        >
                            <Plus />
                            Add ingredient
                        </Button>
                    </div>
                    <div
                        v-for="(ingredient, index) in editForm.ingredients"
                        :key="index"
                        class="grid gap-1"
                    >
                        <div class="flex items-start gap-2">
                            <Input
                                v-if="editForm.ingredients.length > 1"
                                v-model="ingredient.name"
                                type="text"
                                class="flex-1"
                                placeholder="e.g. Paracetamol"
                                :aria-label="`Ingredient ${index + 1} name`"
                            />
                            <Input
                                :id="`edit-ingredient-${index}-dose_amount`"
                                v-model="ingredient.dose_amount"
                                type="number"
                                inputmode="decimal"
                                step="any"
                                class="w-24"
                                placeholder="e.g. 50"
                                :aria-label="`Ingredient ${index + 1} dose amount`"
                            />
                            <Select v-model="ingredient.dose_unit">
                                <SelectTrigger
                                    class="w-28"
                                    :aria-label="`Ingredient ${index + 1} dose unit`"
                                >
                                    <SelectValue placeholder="Unit" />
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
                            <Button
                                v-if="editForm.ingredients.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 cursor-pointer"
                                :aria-label="`Remove ingredient ${index + 1}`"
                                @click="removeIngredient(editForm, index)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                        <InputError
                            :message="ingredientError(editForm, index)"
                        />
                    </div>
                    <InputError :message="editForm.errors.ingredients" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-frequency">Frequency</Label>
                    <Select v-model="editForm.frequency">
                        <SelectTrigger id="edit-frequency" class="w-full">
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
                    <InputError :message="editForm.errors.frequency" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label>Schedule</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="cursor-pointer"
                            @click="editForm.schedules.push(newDose())"
                        >
                            <Plus />
                            Add dose
                        </Button>
                    </div>
                    <p
                        v-if="editForm.schedules.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        No scheduled doses. Add one for each time of day this
                        medication is taken.
                    </p>
                    <div
                        v-for="(dose, index) in editForm.schedules"
                        :key="index"
                        class="grid gap-1"
                    >
                        <div class="flex items-center gap-2">
                            <Select
                                :model-value="doseKind(dose)"
                                @update:model-value="
                                    setDoseKind(dose, String($event))
                                "
                            >
                                <SelectTrigger
                                    :id="`edit-dose-${index}`"
                                    class="flex-1"
                                    :aria-label="`Dose ${index + 1} time of day`"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in timesOfDay"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                    <SelectItem :value="SPECIFIC_TIME">
                                        Specific time
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                v-if="dose.time_of_day === null"
                                :model-value="dose.time ?? ''"
                                type="time"
                                @update:model-value="dose.time = String($event)"
                                class="w-32"
                                :aria-label="`Dose ${index + 1} time`"
                            />
                            <Input
                                v-model="dose.quantity"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                step="1"
                                class="w-16"
                                :aria-label="`Dose ${index + 1} quantity`"
                                title="Units per dose"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 cursor-pointer"
                                :aria-label="`Remove dose ${index + 1}`"
                                @click="editForm.schedules.splice(index, 1)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                        <InputError :message="scheduleError(editForm, index)" />
                    </div>
                    <InputError :message="editForm.errors.schedules" />
                </div>

                <div class="grid gap-3">
                    <Label
                        for="edit-is_prescription"
                        class="flex items-center space-x-3"
                    >
                        <Checkbox
                            id="edit-is_prescription"
                            v-model="editForm.is_prescription"
                        />
                        <span>This is a prescription medication</span>
                    </Label>
                    <InputError :message="editForm.errors.is_prescription" />

                    <Label
                        for="edit-is_active"
                        class="flex items-center space-x-3"
                    >
                        <Checkbox
                            id="edit-is_active"
                            v-model="editForm.is_active"
                        />
                        <span
                            >Currently active (I can still access and take
                            it)</span
                        >
                    </Label>
                    <InputError :message="editForm.errors.is_active" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="cursor-pointer"
                        @click="closeEdit"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        class="cursor-pointer"
                        :disabled="editForm.processing"
                    >
                        Save changes
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
