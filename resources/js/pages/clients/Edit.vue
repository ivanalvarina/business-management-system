<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/clients';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type ContactForm = {
    id?: number;
    name: string;
    position: string;
    department: string;
    email: string;
    phone: string;
    mobile: string;
    is_primary: boolean;
};

type Client = {
    id: number;
    client_code: string;
    client_name: string;
    trade_name: string | null;
    tin: string | null;
    email: string | null;
    phone: string | null;
    billing_address: string | null;
    shipping_address: string | null;
    status: 'active' | 'inactive';
    company_ids: number[];
    contacts: ContactForm[];
};

const props = defineProps<{
    client: Client;
    companies: CompanyOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Clients', href: index() },
            { title: 'Edit', href: '#' },
        ],
    },
});

const blankContact = (isPrimary = false): ContactForm => ({
    name: '',
    position: '',
    department: '',
    email: '',
    phone: '',
    mobile: '',
    is_primary: isPrimary,
});

const form = useForm({
    client_code: props.client.client_code,
    client_name: props.client.client_name,
    trade_name: props.client.trade_name ?? '',
    tin: props.client.tin ?? '',
    email: props.client.email ?? '',
    phone: props.client.phone ?? '',
    billing_address: props.client.billing_address ?? '',
    shipping_address: props.client.shipping_address ?? '',
    status: props.client.status,
    company_ids: props.client.company_ids,
    contacts:
        props.client.contacts.length > 0
            ? props.client.contacts
            : [blankContact(true)],
});

const toggleCompany = (companyId: number, checked: boolean) => {
    form.company_ids = checked
        ? [...form.company_ids, companyId]
        : form.company_ids.filter((id) => id !== companyId);
};

const addContact = () => {
    form.contacts.push(blankContact(form.contacts.length === 0));
};

const removeContact = (index: number) => {
    form.contacts.splice(index, 1);

    if (
        form.contacts.length > 0 &&
        !form.contacts.some((contact) => contact.is_primary)
    ) {
        form.contacts[0].is_primary = true;
    }
};

const makePrimary = (index: number) => {
    form.contacts = form.contacts.map((contact, contactIndex) => ({
        ...contact,
        is_primary: contactIndex === index,
    }));
};

const submit = () => {
    form.put(ClientController.update.url(props.client.id));
};
</script>

<template>
    <Head :title="`Edit ${client.client_name}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${client.client_name}`"
            :description="client.client_code"
        />

        <form
            class="grid gap-6 xl:grid-cols-[2fr_1fr]"
            @submit.prevent="submit"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Client details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="client_code">Client code</Label>
                            <Input
                                id="client_code"
                                v-model="form.client_code"
                            />
                            <InputError :message="form.errors.client_code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="client_name">Client name</Label>
                            <Input
                                id="client_name"
                                v-model="form.client_name"
                            />
                            <InputError :message="form.errors.client_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="trade_name">Trade name</Label>
                            <Input id="trade_name" v-model="form.trade_name" />
                            <InputError :message="form.errors.trade_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tin">TIN</Label>
                            <Input id="tin" v-model="form.tin" />
                            <InputError :message="form.errors.tin" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                            />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="form.phone" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="billing_address">Billing address</Label>
                            <textarea
                                id="billing_address"
                                v-model="form.billing_address"
                                class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                :message="form.errors.billing_address"
                            />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="shipping_address"
                                >Shipping address</Label
                            >
                            <textarea
                                id="shipping_address"
                                v-model="form.shipping_address"
                                class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                :message="form.errors.shipping_address"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Company access</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <label
                        v-for="company in companies"
                        :key="company.id"
                        class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                    >
                        <Checkbox
                            :model-value="form.company_ids.includes(company.id)"
                            @update:model-value="
                                toggleCompany(company.id, Boolean($event))
                            "
                        />
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                company.company_name
                            }}</span>
                            <span
                                class="block truncate text-muted-foreground"
                                >{{ company.company_code }}</span
                            >
                        </span>
                    </label>
                    <InputError :message="form.errors.company_ids" />
                </CardContent>
            </Card>

            <Card class="xl:col-span-2">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-3"
                >
                    <CardTitle>Contact persons</CardTitle>
                    <Button type="button" variant="outline" @click="addContact"
                        >Add contact</Button
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="(contact, contactIndex) in form.contacts"
                        :key="contact.id ?? contactIndex"
                        class="grid gap-3 rounded-lg border p-4 lg:grid-cols-6"
                    >
                        <div class="grid gap-2 lg:col-span-2">
                            <Label :for="`contact_name_${contactIndex}`"
                                >Name</Label
                            >
                            <Input
                                :id="`contact_name_${contactIndex}`"
                                v-model="contact.name"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`contact_position_${contactIndex}`"
                                >Position</Label
                            >
                            <Input
                                :id="`contact_position_${contactIndex}`"
                                v-model="contact.position"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`contact_department_${contactIndex}`"
                                >Department</Label
                            >
                            <Input
                                :id="`contact_department_${contactIndex}`"
                                v-model="contact.department"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`contact_email_${contactIndex}`"
                                >Email</Label
                            >
                            <Input
                                :id="`contact_email_${contactIndex}`"
                                v-model="contact.email"
                                type="email"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`contact_phone_${contactIndex}`"
                                >Phone</Label
                            >
                            <Input
                                :id="`contact_phone_${contactIndex}`"
                                v-model="contact.phone"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`contact_mobile_${contactIndex}`"
                                >Mobile</Label
                            >
                            <Input
                                :id="`contact_mobile_${contactIndex}`"
                                v-model="contact.mobile"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <Checkbox
                                :model-value="contact.is_primary"
                                @update:model-value="makePrimary(contactIndex)"
                            />
                            <span class="text-sm">Primary</span>
                        </div>
                        <div class="flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                @click="removeContact(contactIndex)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>
                    <InputError :message="form.errors.contacts" />
                </CardContent>
            </Card>

            <div class="flex gap-2 xl:col-span-2">
                <Button :disabled="form.processing">Save changes</Button>
                <Button variant="outline" as-child>
                    <Link :href="show(client.id)">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
