<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import VendorController from '@/actions/App/Http/Controllers/VendorController';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/vendors';
import FormTIN from '@/components/FormTIN.vue';
import FormPhone from '@/components/FormPhone.vue';
import ContactPersons from '@/components/ContactPersons.vue';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type CategoryOption = {
    id: number;
    name: string;
};

type ContactForm = {
    name: string;
    position: string;
    department: string;
    email: string;
    phone: string;
    mobile: string;
    is_primary: boolean;
};

defineProps<{
    companies: CompanyOption[];
    categories: CategoryOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Vendors', href: index() },
            { title: 'Create', href: '#' },
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
    vendor_code: '',
    vendor_name: '',
    trade_name: '',
    tin: '',
    email: '',
    phone: '',
    address: '',
    vendor_category_id: '',
    new_category_name: '',
    status: 'active',
    company_ids: [] as number[],
    contacts: [blankContact(true)],
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
    form.post(VendorController.store.url());
};
</script>

<template>
    <Head title="Create vendor" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Create vendor"
            description="Create a supplier record and assign company coverage."
        />

        <form class="grid gap-6" @submit.prevent="submit">
            <!-- Vendor details -->
            <Card>
                <CardHeader>
                    <CardTitle>Vendor details</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <!-- Row 1 -->
                        <div class="grid gap-2">
                            <Label for="vendor_code">Vendor code</Label>
                            <Input
                                id="vendor_code"
                                v-model="form.vendor_code"
                            />
                            <InputError :message="form.errors.vendor_code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="vendor_name">Vendor name</Label>
                            <Input
                                id="vendor_name"
                                v-model="form.vendor_name"
                            />
                            <InputError :message="form.errors.vendor_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="trade_name">Trade name</Label>
                            <Input id="trade_name" v-model="form.trade_name" />
                            <InputError :message="form.errors.trade_name" />
                        </div>

                        <!-- Row 2 -->
                        <div class="grid gap-2">
                            <Label for="tin">TIN</Label>
                            <FormTIN id="tin" v-model="form.tin" />
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
                            <FormPhone id="phone" v-model="form.phone" />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <!-- Row 3 -->
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
                        <div class="grid gap-2">
                            <Label for="vendor_category_id">Category</Label>
                            <select
                                id="vendor_category_id"
                                v-model="form.vendor_category_id"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Select category</option>
                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.vendor_category_id"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="new_category_name">New category</Label>
                            <Input
                                id="new_category_name"
                                v-model="form.new_category_name"
                            />
                            <InputError
                                :message="form.errors.new_category_name"
                            />
                        </div>

                        <!-- Row 4 -->
                        <div class="grid gap-2 md:col-span-3">
                            <Label for="address">Address</Label>
                            <textarea
                                id="address"
                                v-model="form.address"
                                class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.address" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Company coverage: full-width, nasa ilalim ng details -->
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between gap-3"
                >
                    <CardTitle>Company coverage</CardTitle>
                    <span class="text-sm text-muted-foreground">
                        {{
                            form.company_ids.length
                                ? `${form.company_ids.length} selected`
                                : 'None selected'
                        }}
                    </span>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label
                            v-for="company in companies"
                            :key="company.id"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 text-sm transition-colors"
                            :class="{
                                'border-foreground bg-muted/50':
                                    form.company_ids.includes(company.id),
                            }"
                        >
                            <Checkbox
                                :model-value="
                                    form.company_ids.includes(company.id)
                                "
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
                    </div>
                    <p
                        v-if="companies.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No active companies available.
                    </p>
                    <InputError :message="form.errors.company_ids" />
                </CardContent>
            </Card>

            <!-- Contact persons -->
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between gap-3"
                >
                    <CardTitle>Contact persons</CardTitle>
                    <Button type="button" variant="outline" @click="addContact"
                        >Add contact</Button
                    >
                </CardHeader>
                <ContactPersons
                    v-model="form.contacts"
                    :error="form.errors.contacts"
                />
            </Card>

            <!-- Sticky action bar -->
            <div
                class="sticky bottom-0 z-10 -mb-4 flex items-center justify-between gap-3 border-t bg-background py-4"
            >
                <div class="text-sm text-muted-foreground">
                    Coverage:
                    <span class="font-medium text-foreground">
                        {{
                            form.company_ids.length
                                ? `${form.company_ids.length} ${form.company_ids.length > 1 ? 'companies' : 'company'}`
                                : 'no companies'
                        }}
                    </span>
                    ·
                    <span class="font-medium text-foreground">
                        {{ form.contacts.length }}
                        {{ form.contacts.length > 1 ? 'contacts' : 'contact' }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="index()">Cancel</Link>
                    </Button>
                    <Button :disabled="form.processing">Create vendor</Button>
                </div>
            </div>
        </form>
    </div>
</template>