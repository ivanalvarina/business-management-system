<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2, ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { update as switchCompany } from '@/routes/current-company';
import type { CompanyContext, CompanyContextCompany } from '@/types';

const page = usePage();

const companyContext = computed(
    () => page.props.companyContext as CompanyContext,
);

const currentCompany = computed(() => companyContext.value.current);
const companies = computed(() => companyContext.value.options);

const selectCompany = (company: CompanyContextCompany) => {
    if (currentCompany.value?.id === company.id) {
        return;
    }

    router.visit(switchCompany(company.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <DropdownMenu v-if="companies.length > 0">
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="max-w-64 justify-between gap-2"
            >
                <Building2 class="size-4 shrink-0" />
                <span class="truncate">
                    {{ currentCompany?.company_name ?? 'Select company' }}
                </span>
                <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-72">
            <DropdownMenuLabel>Current company</DropdownMenuLabel>
            <DropdownMenuItem
                v-for="company in companies"
                :key="company.id"
                class="flex items-start gap-2"
                @click="selectCompany(company)"
            >
                <Building2 class="mt-0.5 size-4 text-muted-foreground" />
                <span class="min-w-0">
                    <span class="block truncate font-medium">
                        {{ company.company_name }}
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        {{ company.company_code }}
                    </span>
                </span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
