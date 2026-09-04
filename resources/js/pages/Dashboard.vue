<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { document as ventasDocument } from '@/routes/ventas';
import { BarChart3, CheckCircle2, FileText, TrendingUp, XCircle } from '@lucide/vue';

type Kpis = {
    totalFacturado: number;
    facturasValidadas: number;
    facturasRechazadas: number;
    ticketPromedio: number;
};

type StatusDistribution = {
    draft: number;
    pending: number;
    validated: number;
    rejected: number;
};

type FactusStats = {
    successful: number;
    total: number;
    successRate: number;
};

type RecentInvoice = {
    id: number;
    displayNumber: string;
    customerName: string;
    total: number;
    status: string;
    statusLabel: string;
    createdAt: string;
    invoiceId: number;
};

type TopProduct = {
    productName: string;
    quantity: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<{
    kpis: Kpis;
    statusDistribution: StatusDistribution;
    factusStats: FactusStats;
    recentInvoices: RecentInvoice[];
    topProducts: TopProduct[];
}>();

const currency = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const totalStatuses = computed<number>(() => {
    const d = props.statusDistribution;
    return d.draft + d.pending + d.validated + d.rejected;
});

const donutSegments = computed(() => {
    const total = totalStatuses.value;
    if (total === 0) {
        return [];
    }

    const segments = [
        { key: 'draft', label: 'Borrador', color: 'var(--color-muted-foreground)', count: props.statusDistribution.draft },
        { key: 'pending', label: 'Pendiente', color: 'hsl(38, 92%, 50%)', count: props.statusDistribution.pending },
        { key: 'validated', label: 'Validada', color: 'hsl(142, 71%, 45%)', count: props.statusDistribution.validated },
        { key: 'rejected', label: 'Rechazada', color: 'hsl(0, 84%, 60%)', count: props.statusDistribution.rejected },
    ];

    let cumulative = 0;

    return segments
        .filter((s) => s.count > 0)
        .map((s) => {
            const percentage = (s.count / total) * 100;
            const start = cumulative;
            cumulative += percentage;

            return {
                ...s,
                percentage,
                start,
                end: cumulative,
            };
        });
});

const donutGradient = computed<string>(() => {
    if (donutSegments.value.length === 0) {
        return 'conic-gradient(var(--color-muted) 0% 100%)';
    }

    const stops = donutSegments.value
        .map((s) => `${s.color} ${s.start}% ${s.end}%`)
        .join(', ');

    return `conic-gradient(${stops})`;
});

const statusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'validated': return 'default';
        case 'rejected': return 'destructive';
        case 'draft': return 'secondary';
        case 'pending': return 'outline';
        default: return 'secondary';
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            title="Dashboard"
            description="Resumen de ventas y facturación"
        />

        <!-- KPIs -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total facturado</CardTitle>
                    <TrendingUp class="text-muted-foreground size-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ currency.format(kpis.totalFacturado) }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Facturas validadas</CardTitle>
                    <CheckCircle2 class="text-muted-foreground size-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ kpis.facturasValidadas }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Facturas rechazadas</CardTitle>
                    <XCircle class="text-muted-foreground size-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ kpis.facturasRechazadas }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Ticket promedio</CardTitle>
                    <BarChart3 class="text-muted-foreground size-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ currency.format(kpis.ticketPromedio) }}</div>
                </CardContent>
            </Card>
        </div>

        <!-- Status Distribution + Factus -->
        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Status Distribution -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Estado de las facturas</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="totalStatuses === 0" class="text-muted-foreground py-4 text-center text-sm">
                        No hay facturas registradas.
                    </div>

                    <div v-else class="flex items-center gap-6">
                        <!-- Donut chart (CSS) -->
                        <div class="relative size-32 shrink-0">
                            <div
                                class="size-full rounded-full"
                                :style="{ background: donutGradient }"
                            />
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="bg-card flex size-16 items-center justify-center rounded-full shadow-sm">
                                    <span class="text-lg font-bold">{{ totalStatuses }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="flex flex-col gap-2 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full bg-muted-foreground" />
                                <span class="text-muted-foreground">Borrador</span>
                                <span class="ml-auto font-medium">{{ statusDistribution.draft }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full" style="background: hsl(38, 92%, 50%)" />
                                <span class="text-muted-foreground">Pendiente</span>
                                <span class="ml-auto font-medium">{{ statusDistribution.pending }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full" style="background: hsl(142, 71%, 45%)" />
                                <span class="text-muted-foreground">Validada</span>
                                <span class="ml-auto font-medium">{{ statusDistribution.validated }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full" style="background: hsl(0, 84%, 60%)" />
                                <span class="text-muted-foreground">Rechazada</span>
                                <span class="ml-auto font-medium">{{ statusDistribution.rejected }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Factus Integration -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Integración Factus</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="factusStats.total === 0" class="text-muted-foreground py-4 text-center text-sm">
                        Sin solicitudes registradas.
                    </div>

                    <div v-else class="space-y-4">
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl font-bold">{{ factusStats.successRate }}%</span>
                            <span class="text-muted-foreground text-sm">éxito</span>
                        </div>
                        <p class="text-muted-foreground text-sm">
                            {{ factusStats.successful }} solicitudes exitosas de {{ factusStats.total }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Recent Invoices -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Últimas facturas</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="recentInvoices.length === 0" class="text-muted-foreground py-4 text-center text-sm">
                    No hay facturas registradas.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse text-sm">
                        <thead>
                            <tr class="text-muted-foreground border-b text-left">
                                <th class="pb-2 pr-4 font-medium">Factura</th>
                                <th class="pb-2 pr-4 font-medium">Cliente</th>
                                <th class="pb-2 pr-4 text-right font-medium">Total</th>
                                <th class="pb-2 pr-4 font-medium">Estado</th>
                                <th class="pb-2 pr-4 font-medium">Fecha</th>
                                <th class="pb-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invoice in recentInvoices" :key="invoice.id" class="border-b">
                                <td class="py-2.5 pr-4 font-medium">{{ invoice.displayNumber }}</td>
                                <td class="py-2.5 pr-4 text-muted-foreground">{{ invoice.customerName }}</td>
                                <td class="py-2.5 pr-4 text-right">{{ currency.format(invoice.total) }}</td>
                                <td class="py-2.5 pr-4">
                                    <Badge :variant="statusBadgeVariant(invoice.status)">
                                        {{ invoice.statusLabel }}
                                    </Badge>
                                </td>
                                <td class="py-2.5 pr-4 text-muted-foreground">{{ invoice.createdAt }}</td>
                                <td class="py-2.5 text-right">
                                    <a
                                        v-if="invoice.status === 'validated'"
                                        :href="ventasDocument(invoice.invoiceId).url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary text-sm hover:underline"
                                    >
                                        Ver factura
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Top Products -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Productos más vendidos</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="topProducts.length === 0" class="text-muted-foreground py-4 text-center text-sm">
                    No hay productos vendidos.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="(product, index) in topProducts"
                        :key="index"
                        class="flex items-center justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-muted-foreground flex size-7 items-center justify-center rounded-full border text-xs font-medium">
                                {{ index + 1 }}
                            </span>
                            <span class="text-sm font-medium">{{ product.productName }}</span>
                        </div>
                        <span class="text-muted-foreground text-sm">
                            {{ product.quantity }} {{ product.quantity === 1 ? 'unidad' : 'unidades' }}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
