<!-- eslint-disable vue/block-lang -->
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: null });

const props = defineProps({
    flight: Object,
    selectedSeats: Array,
});

const form = useForm({
    passengers: props.selectedSeats.map((seat) => ({
        seat_code: seat.seat_code,
        seat_class: seat.class,
        seat_additional_price_usd: seat.additional_price_usd,
        title: 'Mr',
        first_name: '',
        last_name: '',
        date_of_birth: '',
        nationality: 'ID',
        passport_number: '',
        extra_baggage_kg: 0,
        baggage_fee_usd: 0,
    })),
});

// --- HELPER PERHITUNGAN HARGA ---
const totalBasePrice = computed(
    () => props.flight.base_price_usd * props.selectedSeats.length,
);

const totalSeatFees = computed(() => {
    return form.passengers.reduce(
        (sum, p) => sum + (Number(p.seat_additional_price_usd) || 0),
        0,
    );
});

const totalBaggageFees = computed(() => {
    return form.passengers.reduce(
        (sum, p) => sum + (Number(p.baggage_fee_usd) || 0),
        0,
    );
});

const totalPrice = computed(
    () => totalBasePrice.value + totalSeatFees.value + totalBaggageFees.value,
);

// --- HELPER UI ---
const baggageOptions = [
    { weight: 0, price: 0, label: 'No Extra Baggage' },
    { weight: 5, price: 15, label: '+ 5 KG Extra (+$15.00)' },
    { weight: 10, price: 25, label: '+ 10 KG Extra (+$25.00)' },
    { weight: 20, price: 40, label: '+ 20 KG Extra (+$40.00)' },
];

const updateBaggageFee = (passenger) => {
    const selectedOption = baggageOptions.find(
        (opt) => opt.weight === passenger.extra_baggage_kg,
    );
    passenger.baggage_fee_usd = selectedOption ? selectedOption.price : 0;
};

const submitCheckout = () => {
    form.post(`/flights/${props.flight.id}/checkout`);
};

// HELPER WAKTU & DURASI DARI HALAMAN SEBELUMNYA
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
</script>

<template>
    <Head title="Passenger Details" />

    <AeroLayout>
        <main
            class="mx-auto min-h-[80vh] max-w-5xl px-4 pt-24 pb-12 sm:px-6 md:flex-row lg:px-8"
        >
            <div class="flex flex-col gap-8 md:flex-row">
                <div class="flex-1 space-y-6">
                    <h2 class="text-2xl font-bold text-foreground">
                        Passenger Details
                    </h2>
                    <p class="mb-4 text-sm text-muted-foreground">
                        Please enter the details exactly as they appear on your
                        passport/ID.
                    </p>

                    <form @submit.prevent="submitCheckout" class="space-y-6">
                        <div
                            v-for="(passenger, index) in form.passengers"
                            :key="passenger.seat_code"
                            class="rounded-xl border border-border bg-card p-6 shadow-sm"
                        >
                            <div
                                class="mb-4 flex items-center justify-between border-b border-border pb-4"
                            >
                                <h3 class="text-lg font-bold text-foreground">
                                    Passenger {{ index + 1 }}
                                </h3>
                                <span
                                    class="rounded bg-primary/10 px-3 py-1 text-sm font-bold text-primary"
                                >
                                    Seat: {{ passenger.seat_code }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium text-muted-foreground"
                                    >
                                        Title
                                        <span class="text-destructive">*</span>
                                    </label>
                                    <select
                                        v-model="passenger.title"
                                        required
                                        class="aero-input w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                                    >
                                        <option value="Mr">Mr.</option>
                                        <option value="Mrs">Mrs.</option>
                                        <option value="Ms">Ms.</option>
                                        <option value="Miss">Miss</option>
                                    </select>
                                </div>

                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium text-muted-foreground"
                                    >
                                        Date of Birth
                                        <span class="text-destructive">*</span>
                                    </label>
                                    <Input
                                        type="date"
                                        v-model="passenger.date_of_birth"
                                        required
                                        class="aero-input bg-background text-foreground"
                                    />
                                </div>

                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium text-muted-foreground"
                                    >
                                        First Name
                                        <span class="text-destructive">*</span>
                                    </label>
                                    <Input
                                        v-model="passenger.first_name"
                                        required
                                        placeholder="e.g. Naufal"
                                        class="aero-input bg-background text-foreground"
                                    />
                                </div>

                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium text-muted-foreground"
                                    >
                                        Last Name
                                        <span class="text-destructive">*</span>
                                    </label>
                                    <Input
                                        v-model="passenger.last_name"
                                        required
                                        placeholder="e.g. Fathi"
                                        class="aero-input bg-background text-foreground"
                                    />
                                </div>

                                <div class="md:col-span-2">
                                    <label
                                        class="mb-1 block text-sm font-medium text-muted-foreground"
                                    >
                                        Passport / ID Number
                                    </label>
                                    <Input
                                        v-model="passenger.passport_number"
                                        placeholder="Optional for domestic, required for international"
                                        class="aero-input bg-background text-foreground"
                                    />
                                </div>

                                <div
                                    class="mt-2 rounded-lg border border-primary/20 bg-primary/5 p-4 md:col-span-2"
                                >
                                    <div
                                        class="mb-3 flex items-center justify-between"
                                    >
                                        <div>
                                            <h4
                                                class="text-sm font-bold text-foreground"
                                            >
                                                Baggage Allowance
                                            </h4>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                Your ticket includes free
                                                checked baggage.
                                            </p>
                                        </div>
                                        <span
                                            class="rounded bg-primary px-3 py-1 text-xs font-bold text-primary-foreground shadow-sm"
                                        >
                                            {{ flight.free_baggage_kg }} KG FREE
                                        </span>
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1 block text-sm font-medium text-muted-foreground"
                                        >
                                            Extra Baggage (Optional)
                                        </label>
                                        <select
                                            v-model="passenger.extra_baggage_kg"
                                            @change="
                                                updateBaggageFee(passenger)
                                            "
                                            class="aero-input w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:ring-primary"
                                        >
                                            <option
                                                v-for="option in baggageOptions"
                                                :key="option.weight"
                                                :value="option.weight"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div
                    class="sticky top-24 h-fit w-full rounded-2xl border border-border bg-card p-6 shadow-sm md:w-96"
                >
                    <h3 class="mb-5 text-lg font-bold text-foreground">
                        Booking Summary
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
                        <div
                            class="mt-1 flex items-center justify-between text-xs"
                        >
                            <span class="text-muted-foreground">Aircraft</span>
                            <span class="font-medium text-foreground">{{
                                flight.aircraft?.model_name || 'TBA'
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="mb-6 flex flex-col gap-3 border-b border-border pb-5 text-sm"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Tickets (x{{ selectedSeats.length }})</span
                            >
                            <span class="font-semibold text-foreground">
                                {{
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD',
                                    }).format(totalBasePrice)
                                }}
                            </span>
                        </div>

                        <div
                            v-if="totalSeatFees > 0"
                            class="flex items-center justify-between"
                        >
                            <span
                                class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 text-muted-foreground"
                                >Seat Upgrades</span
                            >
                            <span class="font-semibold text-foreground">
                                +
                                {{
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD',
                                    }).format(totalSeatFees)
                                }}
                            </span>
                        </div>

                        <div
                            v-if="totalBaggageFees > 0"
                            class="flex items-center justify-between"
                        >
                            <span
                                class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 text-muted-foreground"
                                >Extra Baggage</span
                            >
                            <span class="font-semibold text-foreground">
                                +
                                {{
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD',
                                    }).format(totalBaggageFees)
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="mb-6 flex items-end justify-between">
                        <span class="font-bold text-muted-foreground"
                            >Total Price</span
                        >
                        <span class="text-3xl font-black text-primary">
                            {{
                                new Intl.NumberFormat('en-US', {
                                    style: 'currency',
                                    currency: 'USD',
                                }).format(totalPrice)
                            }}
                        </span>
                    </div>

                    <Button
                        @click="submitCheckout"
                        class="hover:bg-primary-hover w-full bg-primary text-primary-foreground shadow-md transition-all hover:shadow-lg"
                        size="lg"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing">Processing...</span>
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

                    <p class="mt-4 text-center text-xs text-muted-foreground">
                        By clicking pay, you agree to our Terms & Conditions.
                    </p>
                </div>
            </div>
        </main>
    </AeroLayout>
</template>
