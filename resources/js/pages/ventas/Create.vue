<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { create as ventasCreate, sendToFactus, store } from '@/actions/App/Http/Controllers/VentaController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { CheckCircle2, ExternalLink, FileText, Plus, Send, Trash2, TriangleAlert } from '@lucide/vue';

type TaxPreview = {
    code: string;
    rate: number;
    is_excluded: boolean;
};

type CustomerOption = {
    id: number;
    company: string | null;
    trade_name: string | null;
    names: string | null;
    identification: string;
};

type ProductOption = {
    id: number;
    code: string;
    name: string;
    price: number;
    unit_measure_code: string;
    taxes: TaxPreview[];
};

type CartLine = {
    product: ProductOption;
    quantity: number;
};

type SalePayload = {
    customer_id: number | null;
    items: Array<{ product_id: number; quantity: number }>;
};

type CreatedInvoice = {
    id: number;
    reference_code: string;
    status: string;
    total: string;
    factus_number: string | null;
    cufe: string | null;
    qr_code: string | null;
    qr_image: string | null;
    validated_at: string | null;
    factus_errors: Record<string, string> | null;
    factus_public_url: string | null;
};

type FactusResponse = {
    message?: string;
    error?: string;
    invoice?: CreatedInvoice;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Ventas',
                href: ventasCreate(),
            },
        ],
    },
});

const props = defineProps<{
    customers: CustomerOption[];
    products: ProductOption[];
}>();

const currency = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const customerId = computed<string>({
    get: () => (http.customer_id === null ? '' : String(http.customer_id)),
    set: (value: string) => {
        http.customer_id = value === '' ? null : Number(value);
    },
});

const http = useHttp<SalePayload>({ customer_id: null, items: [] });
const factusHttp = useHttp<Record<string, never>>({});

const selectedProductId = ref<string>('');
const quantity = ref<number>(1);
const cart = ref<CartLine[]>([]);
const validationErrors = ref<Record<string, string>>({});
const serverErrors = ref<string[]>([]);

const createdInvoice = ref<CreatedInvoice | null>(null);
const factusProcessing = ref<boolean>(false);
const factusSent = ref<boolean>(false);
const factusWarnings = ref<string[]>([]);
const factusErrors = ref<string[]>([]);
const factusMessage = ref<string>('');

const selectedProduct = computed<ProductOption | null>(() => {
    if (selectedProductId.value === '') {
        return null;
    }

    const id = Number(selectedProductId.value);

    return props.products.find((product) => product.id === id) ?? null;
});

const lineSubtotal = (line: CartLine): number => line.product.price * line.quantity;

const lineTax = (line: CartLine): number => {
    const base = lineSubtotal(line);

    return line.product.taxes
        .filter((tax) => !tax.is_excluded)
        .reduce((sum, tax) => sum + (base * tax.rate) / 100, 0);
};

const lineTotal = (line: CartLine): number => lineSubtotal(line) + lineTax(line);

const subtotal = computed<number>(() => cart.value.reduce((sum, line) => sum + lineSubtotal(line), 0));
const taxTotal = computed<number>(() => cart.value.reduce((sum, line) => sum + lineTax(line), 0));
const grandTotal = computed<number>(() => subtotal.value + taxTotal.value);

const updateQuantity = (line: CartLine, value: string | number): void => {
    const parsed = Math.floor(Number(value));

    line.quantity = Number.isFinite(parsed) && parsed >= 1 ? parsed : 1;
};

const addToCart = (): void => {
    const product = selectedProduct.value;

    if (!product || quantity.value < 1) {
        return;
    }

    const existing = cart.value.find((line) => line.product.id === product.id);

    if (existing) {
        existing.quantity += Math.floor(quantity.value);
    } else {
        cart.value.push({ product, quantity: Math.floor(quantity.value) });
    }

    selectedProductId.value = '';
    quantity.value = 1;
};

const removeFromCart = (productId: number): void => {
    cart.value = cart.value.filter((line) => line.product.id !== productId);
};

const cartHasValidQuantities = computed<boolean>(() => cart.value.every((line) => line.quantity >= 1));

const canSubmit = computed<boolean>(
    () =>
        http.customer_id !== null &&
        cart.value.length > 0 &&
        cartHasValidQuantities.value &&
        !http.processing,
);

const resetFactusState = (): void => {
    factusProcessing.value = false;
    factusSent.value = false;
    factusWarnings.value = [];
    factusErrors.value = [];
    factusMessage.value = '';
};

const createSale = async (): Promise<void> => {
    if (!canSubmit.value) {
        return;
    }

    validationErrors.value = {};
    serverErrors.value = [];

    http.items = cart.value.map((line) => ({
        product_id: line.product.id,
        quantity: line.quantity,
    }));

    resetFactusState();
    createdInvoice.value = null;

    try {
        const invoice = await http.submit(store(), {
            onSuccess: () => {
                toast.success('Venta creada correctamente');

                cart.value = [];
                selectedProductId.value = '';
                quantity.value = 1;
                http.customer_id = null;
            },
            onError: (errors) => {
                validationErrors.value = errors;
            },
            onHttpException: (response) => {
                const data = response.data as { message?: string };

                if (data?.message) {
                    serverErrors.value = [data.message];
                } else {
                    serverErrors.value = ['No se pudo crear la venta.'];
                }
            },
            onNetworkError: () => {
                serverErrors.value = ['Error de conexión. Intenta de nuevo.'];
            },
        });

        if (invoice && typeof invoice === 'object' && 'id' in invoice) {
            createdInvoice.value = invoice as CreatedInvoice;
        }
    } catch {
        // Errors are surfaced through onError / onHttpException / onNetworkError.
    }
};

const sendInvoiceToFactus = async (): Promise<void> => {
    const invoice = createdInvoice.value;

    if (!invoice || factusProcessing.value) {
        return;
    }

    factusErrors.value = [];
    factusWarnings.value = [];
    factusMessage.value = '';
    factusProcessing.value = true;

    try {
        const response = await factusHttp.post(sendToFactus(invoice.id).url, {
            onHttpException: (httpResponse) => {
                const data = httpResponse.data as Partial<FactusResponse>;

                factusErrors.value = [
                    data?.error ?? data?.message ?? 'Error al enviar la factura a Factus.',
                ];
            },
            onNetworkError: () => {
                factusErrors.value = ['Error de conexión con Factus. Intenta de nuevo.'];
            },
        });

        const data = (response ?? {}) as FactusResponse;

        if (data.invoice) {
            const result = data.invoice;

            createdInvoice.value = result;
            factusSent.value = true;
            factusMessage.value = data.message ?? '';

            if (result.factus_errors && Object.keys(result.factus_errors).length > 0) {
                factusWarnings.value = Object.values(result.factus_errors);
            }
        } else if (data.error) {
            factusErrors.value = [data.error];
        }
    } catch {
        // Errors are surfaced through the error callbacks above.
    } finally {
        factusProcessing.value = false;
    }
};

const isDraft = computed<boolean>(() => createdInvoice.value?.status === 'draft');
const isFactusSuccess = computed<boolean>(
    () =>
        factusSent.value &&
        (createdInvoice.value?.status === 'validated' || createdInvoice.value?.status === 'pending'),
);
const hasWarnings = computed<boolean>(() => factusWarnings.value.length > 0);
</script>

<template>
    <Head title="Nueva Venta" />

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            title="Nueva Venta"
            description="Selecciona un cliente y agrega los productos de la venta"
        />

        <AlertError v-if="serverErrors.length" title="No se pudo crear la venta." :errors="serverErrors" />

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Datos de la venta</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="customer">Cliente</Label>
                        <Select v-model="customerId">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Selecciona un cliente" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="customer in props.customers"
                                    :key="customer.id"
                                    :value="String(customer.id)"
                                >
                                    {{ customer.company ?? customer.trade_name ?? customer.names }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError class="mt-2" :message="validationErrors.customer_id" />
                    </div>

                    <Separator />

                    <div class="grid gap-2">
                        <Label for="product">Producto</Label>
                        <Select v-model="selectedProductId">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Selecciona un producto" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="product in props.products"
                                    :key="product.id"
                                    :value="String(product.id)"
                                >
                                    {{ product.name }} — {{ currency.format(product.price) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="quantity">Cantidad</Label>
                        <Input
                            id="quantity"
                            type="number"
                            v-model.number="quantity"
                            min="1"
                            step="1"
                            class="w-full"
                        />
                    </div>

                    <div
                        v-if="selectedProduct"
                        class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                    >
                        <span>Precio unitario:</span>
                        <strong class="text-foreground">{{ currency.format(selectedProduct.price) }}</strong>
                        <Badge v-if="selectedProduct.taxes.length" variant="secondary">
                            {{ selectedProduct.taxes.length }} impuesto(s)
                        </Badge>
                    </div>

                    <div class="flex items-center justify-end">
                        <Button
                            type="button"
                            :disabled="!selectedProduct || quantity < 1"
                            @click="addToCart"
                        >
                            <Plus />
                            Agregar al carrito
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Carrito</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="cart.length === 0" class="text-muted-foreground py-4 text-sm">
                        No hay productos agregados.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[680px] border-collapse text-sm">
                            <thead>
                                <tr class="text-muted-foreground border-b text-left">
                                    <th class="py-2 pr-3 font-medium">Producto</th>
                                    <th class="py-2 pr-3 text-right font-medium">Precio unit.</th>
                                    <th class="py-2 pr-3 text-right font-medium">Cantidad</th>
                                    <th class="py-2 pr-3 text-right font-medium">Subtotal</th>
                                    <th class="py-2 pr-3 text-right font-medium">Impuesto</th>
                                    <th class="py-2 pr-3 text-right font-medium">Total</th>
                                    <th class="py-2 text-right font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="line in cart"
                                    :key="line.product.id"
                                    class="border-b"
                                >
                                    <td class="py-2 pr-3">
                                        <div class="font-medium">{{ line.product.name }}</div>
                                        <div class="text-muted-foreground text-xs">
                                            {{ line.product.code }} · {{ line.product.unit_measure_code }}
                                        </div>
                                    </td>
                                    <td class="py-2 pr-3 text-right">{{ currency.format(line.product.price) }}</td>
                                    <td class="py-2 pr-3 text-right">
                                        <Input
                                            type="number"
                                            min="1"
                                            step="1"
                                            class="ml-auto w-20"
                                            :model-value="line.quantity"
                                            @update:model-value="updateQuantity(line, $event)"
                                        />
                                    </td>
                                    <td class="py-2 pr-3 text-right">{{ currency.format(lineSubtotal(line)) }}</td>
                                    <td class="py-2 pr-3 text-right">{{ currency.format(lineTax(line)) }}</td>
                                    <td class="py-2 pr-3 text-right font-medium">{{ currency.format(lineTotal(line)) }}</td>
                                    <td class="py-2 text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            type="button"
                                            @click="removeFromCart(line.product.id)"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="cart.length" class="mt-4 space-y-1 border-t pt-4 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span>{{ currency.format(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Impuestos</span>
                            <span>{{ currency.format(taxTotal) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <span>Total</span>
                            <span>{{ currency.format(grandTotal) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Button
                data-test="create-sale-button"
                type="button"
                :disabled="!canSubmit"
                @click="createSale"
            >
                <Spinner v-if="http.processing" class="size-4" />
                <template v-else>Crear Venta</template>
            </Button>
        </div>

        <AlertError v-if="factusErrors.length" title="No se pudo enviar la factura a Factus." :errors="factusErrors" />

        <Card v-if="createdInvoice" data-test="sale-result-card">
            <CardHeader>
                <CardTitle>Resultado de la venta</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="text-muted-foreground">Referencia:</span>
                    <strong>{{ createdInvoice.reference_code }}</strong>
                    <Badge v-if="isDraft" variant="secondary">Borrador</Badge>
                    <Badge v-else-if="isFactusSuccess" variant="default">Validada</Badge>
                    <Badge v-else-if="createdInvoice.status === 'rejected'" variant="destructive">
                        Rechazada
                    </Badge>
                    <Badge v-else variant="secondary">{{ createdInvoice.status }}</Badge>
                </div>

                <div class="grid gap-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Total</span>
                        <strong>{{ currency.format(Number(createdInvoice.total)) }}</strong>
                    </div>
                </div>

                <AlertError
                    v-if="createdInvoice.status === 'rejected'"
                    title="La factura fue rechazada por Factus."
                    :errors="factusErrors.length ? factusErrors : ['La factura fue rechazada. Revisa el estado en tu registro de auditoría.']"
                />

                <div v-if="isFactusSuccess" class="space-y-4 border-t pt-4">
                    <div class="flex items-center gap-2 text-sm font-medium text-foreground">
                        <CheckCircle2 class="size-4 text-green-600" />
                        {{ factusMessage || 'Factura enviada y validada correctamente.' }}
                    </div>

                    <div class="grid gap-1 text-sm sm:grid-cols-2">
                        <div class="flex justify-between gap-2">
                            <span class="text-muted-foreground">Número Factus</span>
                            <strong>{{ createdInvoice.factus_number ?? '—' }}</strong>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-muted-foreground">Estado</span>
                            <strong>{{ createdInvoice.factus_number ? 'Validada' : 'Pendiente' }}</strong>
                        </div>
                        <div v-if="createdInvoice.validated_at" class="flex justify-between gap-2">
                            <span class="text-muted-foreground">Fecha de validación</span>
                            <strong>{{ new Date(createdInvoice.validated_at).toLocaleString('es-CO') }}</strong>
                        </div>
                    </div>

                    <div v-if="createdInvoice.cufe" class="grid gap-1 text-sm">
                        <span class="text-muted-foreground">CUFE</span>
                        <code class="break-all rounded bg-muted p-2 text-xs">{{ createdInvoice.cufe }}</code>
                    </div>

                    <div v-if="createdInvoice.qr_image" class="flex items-start gap-4">
                        <img
                            :src="createdInvoice.qr_image"
                            alt="Código QR de la factura"
                            class="size-32 rounded border bg-white"
                        />
                        <a
                            v-if="createdInvoice.qr_code"
                            :href="createdInvoice.qr_code"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-sm text-primary"
                        >
                            Consultar en la DIAN
                            <ExternalLink class="size-4" />
                        </a>
                    </div>

                    <div v-if="createdInvoice.factus_number" class="flex flex-wrap items-center gap-3 border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <a
                                :href="`/ventas/${createdInvoice.id}/document`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <FileText class="size-4" />
                                Ver factura
                            </a>
                        </Button>
                    </div>

                    <div
                        v-if="hasWarnings"
                        class="flex items-start gap-2 rounded border border-amber-300 bg-amber-50 p-3 text-sm"
                    >
                        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600" />
                        <div>
                            <p class="font-medium text-amber-800">
                                La factura se validó, pero Factus reportó advertencias:
                            </p>
                            <ul class="list-inside list-disc text-amber-700">
                                <li v-for="(warning, index) in factusWarnings" :key="index">
                                    {{ warning }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div v-if="isDraft" class="flex justify-end border-t pt-4">
                    <Button
                        data-test="send-to-factus-button"
                        type="button"
                        :disabled="factusProcessing"
                        @click="sendInvoiceToFactus"
                    >
                        <Spinner v-if="factusProcessing" class="size-4" />
                        <template v-else>
                            <Send />
                            Enviar a Factus
                        </template>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
