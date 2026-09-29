<script setup lang="ts">
import { Trash2, Plus } from '@lucide/vue';
import FormPhone from '@/components/FormPhone.vue';
import { Button } from '@/components/ui/button';
import { CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

export interface Contact {
    name: string;
    position: string;
    department: string;
    email: string;
    phone: string;
    mobile: string;
    is_primary: boolean;
}

const props = withDefaults(
    defineProps<{
        error?: string;
    }>(),
    {
        error: '',
    },
);

const contacts = defineModel<Contact[]>({
    default: () => [],
});

const removeContact = (index: number) => {
    const wasPrimary = contacts.value[index]?.is_primary;

    contacts.value.splice(index, 1);

    // If primary contact was deleted,
    // make the first remaining contact primary.
    if (wasPrimary && contacts.value.length > 0) {
        contacts.value.forEach((contact, contactIndex) => {
            contact.is_primary = contactIndex === 0;
        });
    }
};

const makePrimary = (index: number) => {
    contacts.value.forEach((contact, contactIndex) => {
        contact.is_primary = contactIndex === index;
    });
};
</script>

<template>
    <CardContent class="space-y-4">
        <div
            v-for="(contact, contactIndex) in contacts"
            :key="contactIndex"
            class="grid gap-3 rounded-lg border p-4 lg:grid-cols-6"
        >
            <!-- Name -->
            <div class="grid gap-2 lg:col-span-2">
                <Label :for="`contact_name_${contactIndex}`"> Name </Label>

                <Input
                    :id="`contact_name_${contactIndex}`"
                    v-model="contact.name"
                />
            </div>

            <!-- Position -->
            <div class="grid gap-2">
                <Label :for="`contact_position_${contactIndex}`">
                    Position
                </Label>

                <Input
                    :id="`contact_position_${contactIndex}`"
                    v-model="contact.position"
                />
            </div>

            <!-- Department -->
            <div class="grid gap-2">
                <Label :for="`contact_department_${contactIndex}`">
                    Department
                </Label>

                <Input
                    :id="`contact_department_${contactIndex}`"
                    v-model="contact.department"
                />
            </div>

            <!-- Email -->
            <div class="grid gap-2">
                <Label :for="`contact_email_${contactIndex}`"> Email </Label>

                <Input
                    :id="`contact_email_${contactIndex}`"
                    v-model="contact.email"
                    type="email"
                />
            </div>

            <!-- Phone / Landline -->
            <div class="grid gap-2">
                <Label :for="`contact_phone_${contactIndex}`"> Landline </Label>

                <Input
                    :id="`contact_phone_${contactIndex}`"
                    v-model="contact.phone"
                    type="tel"
                />
            </div>

            <!-- Second Row -->
            <div
                class="flex flex-col gap-3 lg:col-span-6 lg:flex-row lg:items-end"
            >
                <!-- Mobile -->
                <div class="grid gap-2 lg:w-56">
                    <Label :for="`contact_mobile_${contactIndex}`">
                        Mobile
                    </Label>

                    <FormPhone
                        :id="`contact_mobile_${contactIndex}`"
                        v-model="contact.mobile"
                    />
                </div>

                <!-- Primary -->
                <div class="flex h-10 items-center gap-2">
                    <Checkbox
                        :id="`contact_primary_${contactIndex}`"
                        :model-value="contact.is_primary"
                        @update:model-value="makePrimary(contactIndex)"
                    />

                    <Label
                        :for="`contact_primary_${contactIndex}`"
                        class="cursor-pointer"
                    >
                        Primary
                    </Label>
                </div>

                <!-- Delete -->
                <div class="lg:ml-auto">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        @click="removeContact(contactIndex)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="contacts.length === 0"
            class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            No contact persons added.
        </div>

        <InputError :message="props.error" />
    </CardContent>
</template>
