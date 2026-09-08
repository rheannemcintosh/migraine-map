<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/migraine-scores';

type Medication = {
    id: number;
    name: string;
    dose: string;
};

type RecordedMedication = {
    id: number;
    name: string;
    dose: string;
    quantity: number;
};

type MedicationTaken = {
    id: number;
    quantity: number;
};

type Props = {
    date: string;
    scoreId: number | null;
    score: number | null;
    medications: Medication[];
    recorded: RecordedMedication[];
    idPrefix: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    saved: [];
}>();

const SCORE_OPTIONS = Array.from({ length: 11 }, (_, i) => i);

const form = useForm<{
    date: string;
    score: number | null;
    medications: MedicationTaken[];
}>({
    date: props.date,
    score: props.score,
    medications: props.recorded.map((taken) => ({
        id: taken.id,
        quantity: taken.quantity,
    })),
});

const isSelected = (medication: Medication): boolean =>
    form.medications.some((taken) => taken.id === medication.id);

const quantityFor = (medication: Medication): number =>
    form.medications.find((taken) => taken.id === medication.id)?.quantity ?? 1;

const toggle = (medication: Medication, checked: boolean): void => {
    form.medications = checked
        ? [...form.medications, { id: medication.id, quantity: 1 }]
        : form.medications.filter((taken) => taken.id !== medication.id);
};

const setQuantity = (medication: Medication, value: string | number): void => {
    const quantity = Math.max(1, Math.floor(Number(value)) || 1);

    form.medications = form.medications.map((taken) =>
        taken.id === medication.id ? { ...taken, quantity } : taken,
    );
};

const medicationError = computed<string | undefined>(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return Object.keys(errors)
        .filter((key) => key.startsWith('medications'))
        .map((key) => errors[key])
        .find((message) => message !== undefined);
});

const isSubmittable = computed(() => form.score !== null && !form.processing);

const submit = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: (): void => emit('saved'),
    };

    if (props.scoreId === null) {
        form.post(store.url(), options);

        return;
    }

    form.transform(({ score, medications }) => ({ score, medications })).patch(
        update.url(props.scoreId),
        options,
    );
};
</script>

<template>
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

        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">Medications taken</legend>
            <p
                v-if="medications.length === 0"
                class="text-muted-foreground text-sm"
            >
                No ad hoc medications declared yet. Add them on the Medications
                page to record them here.
            </p>
            <ul v-else class="space-y-2">
                <li
                    v-for="medication in medications"
                    :key="medication.id"
                    class="flex min-h-9 items-center gap-3"
                >
                    <Checkbox
                        :id="`${idPrefix}-${medication.id}`"
                        :model-value="isSelected(medication)"
                        @update:model-value="
                            (checked) => toggle(medication, checked === true)
                        "
                    />
                    <Label
                        :for="`${idPrefix}-${medication.id}`"
                        class="flex flex-1 flex-col items-start gap-0"
                    >
                        <span>{{ medication.name }}</span>
                        <span class="text-muted-foreground text-xs font-normal">
                            {{ medication.dose }}
                        </span>
                    </Label>
                    <Input
                        v-if="isSelected(medication)"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        step="1"
                        class="w-20"
                        :aria-label="`Number of ${medication.name} taken`"
                        :model-value="quantityFor(medication)"
                        @update:model-value="
                            (value) => setQuantity(medication, value)
                        "
                    />
                </li>
            </ul>
            <InputError :message="medicationError" />
        </fieldset>

        <slot name="actions" :submittable="isSubmittable">
            <Button type="submit" :disabled="!isSubmittable">Save</Button>
        </slot>
    </form>
</template>
