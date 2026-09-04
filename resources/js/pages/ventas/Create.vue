<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { store } from '@/actions/App/Http/Controllers/VentaController';
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
import { create as ventasCreate } from '@/routes/ventas';
import { Plus, Trash2 } from '@lucide/vue';

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

const selectedProductId = ref<string>('');
const quantity = ref<number>(1);
const cart = ref<CartLine[]>([]);
const validationErrors = ref<Record<string, string>>({});
const serverErrors = ref<string[]>([]);

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

const addToCart = (): void => {
    const product = selectedProduct.value;

    if (!product || quantity.value < 1) {
        return;
    }

    const existing = cart.value.find((line) => line.product.id === product.id);

    if (existing) {
        existing.quantity += quantity.value;
    } else {
        cart.value.push({ product, quantity: quantity.value });
    }

    selectedProductId.value = '';
    quantity.value = 1;
};

const removeFromCart = (productId: number): void => {
    cart.value = cart.value.filter((line) => line.product.id !== productId);
};

const canSubmit = computed<boolean>(
    () => http.customer_id !== null && cart.value.length > 0 && !http.processing,
);

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

    await http.submit(store(), {
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
};
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
                            v-model="quantity"
                            min="1"
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
                        <table class="w-full min-w-[640px] border-collapse text-sm">
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
                                    <td class="py-2 pr-3 text-right">{{ line.quantity }}</td>
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
    </div>
</template>
