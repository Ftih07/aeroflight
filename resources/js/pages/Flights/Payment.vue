<!-- eslint-disable vue/block-lang -->
<script setup>
import { Head } from '@inertiajs/vue3';
import { loadStripe } from '@stripe/stripe-js';
import { ref, onMounted, computed } from 'vue';
import { Button } from '@/components/ui/button';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: null });

const props = defineProps({
    flight: Object,
    return_flight: Object,
    booking: Object,
    grandTotal: Number,
    isRoundTrip: Boolean,
    clientSecret: String,
    stripeKey: String,
});

const stripeElementRef = ref(null);
const isProcessing = ref(false);
const errorMessage = ref('');
let stripe = null;
let elements = null;

// --- HELPER NAMA KOTA BANDARA ---
const allAirports = ref([]);

onMounted(async () => {
    try {
        const resAirports = await fetch(
            'https://gist.githubusercontent.com/tdreyno/4278655/raw/7b0762c09b519f40397e4c3e100b097d861f5588/airports.json',
        );

        if (resAirports.ok) {
            const dataAirports = await resAirports.json();
            allAirports.value = dataAirports.filter(
                (a) => a.code && a.code.trim() !== '',
            );
        }
    } catch (error) {
        console.error('Gagal mengambil data bandara:', error);
    }
});

const getCityName = (code) => {
    if (!allAirports.value || allAirports.value.length === 0) {
        return '';
    }

    const airport = allAirports.value.find((a) => a.code === code);

    return airport ? airport.name : '';
};

// --- CALCULATIONS UNTUK RINCIAN HARGA ---
const baggageTotal = computed(() => {
    return props.booking.passengers.reduce(
        (sum, p) => sum + Number(p.baggage_fee_usd || 0),
        0,
    );
});

// Harga dasar sekarang merujuk ke atribut starting_price yang di-append di Controller
const baseFlightTotal = computed(() => {
    let flightSum =
        Number(props.flight.starting_price || 0) *
        props.booking.passengers.length;

    if (props.isRoundTrip && props.return_flight) {
        flightSum +=
            Number(props.return_flight.starting_price || 0) *
            props.booking.passengers.length;
    }

    return flightSum;
});

const seatUpgradeTotal = computed(() => {
    // Sisa dari grandTotal - (base + baggage)
    const currentGrand = props.grandTotal || props.booking.total_amount_usd;
    
    return currentGrand - baseFlightTotal.value - baggageTotal.value;
});

// --- HELPER WAKTU & DURASI ---
const formatTime = (dateString) =>
    new Date(dateString).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });

const calculateDuration = (departure, arrival) => {
    const diffMs = new Date(arrival) - new Date(departure);
    const diffHrs = Math.floor(diffMs / (1000 * 60 * 60));
    const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    return diffHrs === 0 ? `${diffMins}m` : `${diffHrs}h ${diffMins}m`;
};

const displayFlightNumber = (airlineCode, flightNumber) => {
    const code = String(airlineCode || '').toUpperCase();
    const num = String(flightNumber || '').toUpperCase();

    return num.includes(code) || num.includes('-') ? num : `${code}-${num}`;
};

onMounted(async () => {
    stripe = await loadStripe(props.stripeKey);

    const appearance = {
        theme: 'none',
        variables: {
            fontFamily: 'ui-sans-serif, system-ui, sans-serif',
            colorBackground: 'transparent',
            colorText: 'currentColor',
            colorDanger: '#ef4444',
            borderRadius: '8px',
            spacingUnit: '4px',
            colorTextPlaceholder: '#9ca3af',
        },
        rules: {
            '.Input': {
                border: '1px solid #e5e7eb',
                backgroundColor: 'transparent',
                padding: '12px 16px',
                fontSize: '14px',
                transition: 'border-color 0.15s, box-shadow 0.15s',
            },
            '.Input:focus': {
                borderColor: '#3b82f6',
                boxShadow: '0 0 0 3px rgba(59, 130, 246, 0.15)',
                outline: 'none',
            },
            '.Label': {
                fontWeight: '500',
                fontSize: '14px',
                color: '#6b7280',
                marginBottom: '6px',
            },
            '.Input--invalid': { borderColor: '#ef4444', color: '#ef4444' },
        },
    };

    elements = stripe.elements({
        clientSecret: props.clientSecret,
        appearance,
    });

    const paymentElement = elements.create('payment', {
        layout: 'tabs',
    });

    if (stripeElementRef.value) {
        paymentElement.mount(stripeElementRef.value);
    }
});

const submitPayment = async () => {
    if (!stripe || !elements) {
        return [];
    }

    isProcessing.value = true;
    errorMessage.value = '';

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            return_url: window.location.origin + '/checkout/success',
        },
    });

    if (error) {
        errorMessage.value = error.message;
        isProcessing.value = false;
    }
};
</script>

<template>
    <Head title="Secure Payment" />

    <AeroLayout>
        <main
            class="mx-auto min-h-[80vh] max-w-6xl px-4 pt-24 pb-12 sm:px-6 md:flex-row lg:px-8"
        >
            <div class="flex flex-col gap-8 lg:flex-row">
                <div class="flex-1 space-y-6">
                    <h2 class="text-2xl font-bold text-foreground">
                        Complete Payment
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Please review your flight details and complete the
                        payment. All transactions are secured and encrypted.
                    </p>

                    <div
                        class="rounded-xl border border-border bg-card p-6 shadow-sm"
                    >
                        <div ref="stripeElementRef" class="min-h-[250px]">
                            <div
                                v-if="!stripe"
                                class="flex h-40 items-center justify-center"
                            >
                                <div
                                    class="h-6 w-6 animate-spin rounded-full border-2 border-border border-t-primary"
                                ></div>
                            </div>
                        </div>

                        <div
                            v-if="errorMessage"
                            class="mt-4 rounded-lg bg-destructive/10 p-4 text-sm font-medium text-destructive"
                        >
                            {{ errorMessage }}
                        </div>
                    </div>
                </div>

                <div
                    class="sticky top-24 h-fit w-full shrink-0 rounded-2xl border border-border bg-card p-6 shadow-sm md:w-96 lg:w-[400px]"
                >
                    <div class="mb-5 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-foreground">
                            Order Summary
                        </h3>
                        <span
                            class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-xs font-bold text-foreground shadow-sm"
                        >
                            #{{ String(booking.id).padStart(5, '0') }}
                        </span>
                    </div>

                    <div
                        class="relative mb-4 overflow-hidden rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-emerald-500"
                        ></div>
                        <div
                            class="mb-3 flex items-center justify-between border-b border-border/50 pb-3"
                        >
                            <span
                                class="inline-block rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold tracking-wider text-emerald-700 uppercase"
                                >🛫 Outbound</span
                            >
                            <span class="font-mono text-xs font-bold">{{
                                displayFlightNumber(
                                    flight.segments?.[0]?.airline_code,
                                    flight.segments?.[0]?.flight_number,
                                )
                            }}</span>
                        </div>

                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-xl font-black text-foreground"
                                    >{{ flight.origin_airport }}</span
                                >
                                <span
                                    class="text-[11px] font-semibold text-muted-foreground"
                                    >{{ formatTime(flight.departure_at) }}</span
                                >
                            </div>
                            <div class="flex flex-col items-center px-2">
                                <span
                                    v-if="
                                        !flight.stop_count ||
                                        flight.stop_count === 0
                                    "
                                    class="text-[10px] font-bold text-emerald-500 uppercase"
                                    >Direct</span
                                >
                                <span
                                    v-else
                                    class="text-[10px] font-bold text-amber-500 uppercase"
                                    >{{ flight.stop_count }} Stop(s)</span
                                >

                                <div
                                    class="relative my-1 flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-10 bg-border"></div>
                                    <svg
                                        class="absolute h-3 w-3 bg-transparent text-muted-foreground"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M14 5l7 7m0 0l-7 7m7-7H3"
                                        />
                                    </svg>
                                </div>
                                <span
                                    class="text-[10px] font-bold text-muted-foreground"
                                    >{{
                                        calculateDuration(
                                            flight.departure_at,
                                            flight.arrival_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span
                                    class="block text-xl font-black text-foreground"
                                    >{{ flight.destination_airport }}</span
                                >
                                <span
                                    class="text-[11px] font-semibold text-muted-foreground"
                                    >{{ formatTime(flight.arrival_at) }}</span
                                >
                            </div>
                        </div>

                        <div
                            v-if="flight.stop_count > 0"
                            class="mt-2 text-center text-[10px] text-muted-foreground"
                        >
                            via
                            {{
                                flight.segments
                                    ?.slice(0, -1)
                                    ?.map(
                                        (seg) =>
                                            getCityName(
                                                seg.destination_airport,
                                            ) || seg.destination_airport,
                                    )
                                    ?.join(', ')
                            }}
                        </div>
                    </div>

                    <div
                        v-if="isRoundTrip && return_flight"
                        class="relative mb-6 overflow-hidden rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-blue-500"
                        ></div>
                        <div
                            class="mb-3 flex items-center justify-between border-b border-border/50 pb-3"
                        >
                            <span
                                class="inline-block rounded-md bg-blue-100 px-2 py-0.5 text-[10px] font-bold tracking-wider text-blue-700 uppercase"
                                >🛬 Return</span
                            >
                            <span class="font-mono text-xs font-bold">{{
                                displayFlightNumber(
                                    return_flight.segments?.[0]?.airline_code,
                                    return_flight.segments?.[0]?.flight_number,
                                )
                            }}</span>
                        </div>

                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-xl font-black text-foreground"
                                    >{{ return_flight.origin_airport }}</span
                                >
                                <span
                                    class="text-[11px] font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(return_flight.departure_at)
                                    }}</span
                                >
                            </div>
                            <div class="flex flex-col items-center px-2">
                                <span
                                    v-if="
                                        !return_flight.stop_count ||
                                        return_flight.stop_count === 0
                                    "
                                    class="text-[10px] font-bold text-emerald-500 uppercase"
                                    >Direct</span
                                >
                                <span
                                    v-else
                                    class="text-[10px] font-bold text-amber-500 uppercase"
                                    >{{
                                        return_flight.stop_count
                                    }}
                                    Stop(s)</span
                                >

                                <div
                                    class="relative my-1 flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-10 bg-border"></div>
                                    <svg
                                        class="absolute h-3 w-3 bg-transparent text-muted-foreground"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M14 5l7 7m0 0l-7 7m7-7H3"
                                        />
                                    </svg>
                                </div>
                                <span
                                    class="text-[10px] font-bold text-muted-foreground"
                                    >{{
                                        calculateDuration(
                                            return_flight.departure_at,
                                            return_flight.arrival_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span
                                    class="block text-xl font-black text-foreground"
                                    >{{
                                        return_flight.destination_airport
                                    }}</span
                                >
                                <span
                                    class="text-[11px] font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(return_flight.arrival_at)
                                    }}</span
                                >
                            </div>
                        </div>

                        <div
                            v-if="return_flight.stop_count > 0"
                            class="mt-2 text-center text-[10px] text-muted-foreground"
                        >
                            via
                            {{
                                return_flight.segments
                                    ?.slice(0, -1)
                                    ?.map(
                                        (seg) =>
                                            getCityName(
                                                seg.destination_airport,
                                            ) || seg.destination_airport,
                                    )
                                    ?.join(', ')
                            }}
                        </div>
                    </div>

                    <div
                        class="mb-6 flex flex-col gap-3 border-t border-b border-border py-4 text-sm"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Flight Tickets (x{{
                                    booking.passengers.length
                                }})</span
                            >
                            <span class="font-semibold text-foreground">
                                {{
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD',
                                    }).format(baseFlightTotal)
                                }}
                            </span>
                        </div>

                        <div
                            v-if="baggageTotal > 0"
                            class="flex items-center justify-between"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Extra Baggage</span
                            >
                            <span class="text-xs font-medium text-foreground">
                                +
                                {{
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD',
                                    }).format(baggageTotal)
                                }}
                            </span>
                        </div>

                        <div
                            v-if="seatUpgradeTotal > 0"
                            class="flex items-center justify-between"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Seat Upgrades</span
                            >
                            <span class="text-xs font-medium text-foreground">
                                +
                                {{
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD',
                                    }).format(seatUpgradeTotal)
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="mb-6 flex items-end justify-between">
                        <span class="font-bold text-muted-foreground"
                            >Total to Pay</span
                        >
                        <span class="text-3xl font-black text-primary">
                            {{
                                new Intl.NumberFormat('en-US', {
                                    style: 'currency',
                                    currency: 'USD',
                                }).format(
                                    grandTotal || booking.total_amount_usd,
                                )
                            }}
                        </span>
                    </div>

                    <Button
                        @click="submitPayment"
                        class="hover:bg-primary-hover w-full bg-primary text-primary-foreground shadow-md transition-all hover:shadow-lg"
                        size="lg"
                        :disabled="isProcessing"
                    >
                        <svg
                            v-if="isProcessing"
                            class="mr-2 h-4 w-4 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                        <span v-if="isProcessing">Processing Payment...</span>
                        <span v-else class="flex items-center gap-2">
                            Pay Securely
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3"
                                />
                            </svg>
                        </span>
                    </Button>
                </div>
            </div>
        </main>
    </AeroLayout>
</template>
