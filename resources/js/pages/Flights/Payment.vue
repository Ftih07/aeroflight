<!-- eslint-disable vue/block-lang -->
<script setup>
import { Head } from '@inertiajs/vue3';
import { loadStripe } from '@stripe/stripe-js';
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: null });

const props = defineProps({
    flight: Object,
    booking: Object,
    clientSecret: String,
    stripeKey: String,
});

const stripeElementRef = ref(null);
const isProcessing = ref(false);
const errorMessage = ref('');
let stripe = null;
let elements = null;

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
            '.Input--invalid': {
                borderColor: '#ef4444',
                color: '#ef4444',
            },
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
            class="mx-auto min-h-[80vh] max-w-5xl px-4 pt-24 pb-12 sm:px-6 md:flex-row lg:px-8"
        >
            <div class="flex flex-col gap-8 md:flex-row">
                <div class="flex-1 space-y-6">
                    <h2 class="text-2xl font-bold text-foreground">
                        Complete Payment
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        All transactions are secured and encrypted.
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
                    class="sticky top-24 h-fit w-full rounded-2xl border border-border bg-card p-6 shadow-sm md:w-96"
                >
                    <h3 class="mb-5 text-lg font-bold text-foreground">
                        Order Summary
                    </h3>

                    <div
                        class="mb-6 rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div class="mb-3 text-center">
                            <span
                                class="inline-block rounded-md bg-primary/10 px-3 py-1 text-xs font-bold tracking-widest text-primary uppercase"
                            >
                                {{ flight.airline_name || flight.airline_code }}
                            </span>
                        </div>

                        <div class="mb-4 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-lg font-black text-foreground"
                                    >{{ flight.origin_airport }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-muted-foreground"
                                    >{{ formatTime(flight.departure_at) }}</span
                                >
                            </div>

                            <div class="flex flex-col items-center px-2">
                                <span
                                    class="mb-1 text-[10px] font-bold text-muted-foreground"
                                >
                                    {{
                                        calculateDuration(
                                            flight.departure_at,
                                            flight.arrival_at,
                                        )
                                    }}
                                </span>
                                <div
                                    class="relative flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-12 bg-border"></div>
                                    <svg
                                        class="absolute h-4 w-4 bg-transparent text-muted-foreground"
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
                            </div>

                            <div class="text-right">
                                <span
                                    class="block text-lg font-black text-foreground"
                                    >{{ flight.destination_airport }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-muted-foreground"
                                    >{{ formatTime(flight.arrival_at) }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-border/50 pt-3 text-xs"
                        >
                            <span class="text-muted-foreground"
                                >Flight No.</span
                            >
                            <span class="font-mono font-bold text-foreground">{{
                                displayFlightNumber(
                                    flight.airline_code,
                                    flight.flight_number,
                                )
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="mb-6 flex flex-col gap-3 border-b border-border pb-5 text-sm"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Booking ID</span
                            >
                            <span
                                class="rounded bg-muted px-2 py-0.5 font-mono font-bold text-foreground"
                            >
                                #{{ String(booking.id).padStart(5, '0') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Passengers</span
                            >
                            <span class="font-semibold text-foreground">
                                {{ booking.passengers.length }} Person(s)
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
                                }).format(booking.total_amount_usd)
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
