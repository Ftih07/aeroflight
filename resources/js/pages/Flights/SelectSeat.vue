<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import AeroLayout from '@/layouts/AeroLayout.vue';

// MATIKAN LAYOUT GLOBAL BAWAAN BREEZE
defineOptions({
    // @ts-expect-error - Inertia layout typing not recognized
    layout: null,
});

const props = defineProps<{
    flight: any;
    groupedSeats: Record<string, any[]>;
}>();

// Menyimpan ID kursi yang dipilih user
const selectedSeats = ref<any[]>([]);

// Fungsi ketika kursi diklik
const toggleSeat = (seat: any) => {
    if (!seat.is_available) {
        return; // Kalau udah dipesan orang, abaikan
    }

    const index = selectedSeats.value.findIndex((s) => s.id === seat.id);

    if (index === -1) {
        selectedSeats.value.push(seat);
    } else {
        selectedSeats.value.splice(index, 1);
    }
};

// Hitung total harga otomatis
const totalPrice = computed(() => {
    const baseTotal = props.flight.base_price_usd * selectedSeats.value.length;

    const additionalTotal = selectedSeats.value.reduce(
        (sum, seat) => sum + Number(seat.additional_price_usd),
        0,
    );

    return baseTotal + additionalTotal;
});

// Fungsi untuk menghitung durasi
const calculateDuration = (departure: string, arrival: string): string => {
    const start = new Date(departure);
    const end = new Date(arrival);

    const diffMs = end.getTime() - start.getTime();

    const diffHrs = Math.floor(diffMs / (1000 * 60 * 60));
    const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    if (diffHrs === 0) {
        return `${diffMins}m`;
    }

    return `${diffHrs}h ${diffMins}m`;
};

// Format Waktu (Contoh: 14:30)
const formatTime = (dateString: string): string => {
    const date = new Date(dateString);

    if (isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
};

// Lanjut ke halaman pembayaran
const proceedToBooking = () => {
    router.post(`/flights/${props.flight.id}/book`, {
        // Ganti namanya jadi selected_seats dan kirim utuh object-nya
        selected_seats: selectedSeats.value,
    });
};
</script>

<template>
    <Head title="Select Seats" />

    <AeroLayout>
        <main
            class="mx-auto min-h-[80vh] max-w-[1400px] px-4 pt-24 pb-12 sm:px-6 lg:px-8"
        >
            <div class="flex flex-col gap-8 md:flex-row">
                <div
                    class="flex-1 rounded-2xl border border-border bg-card p-6 text-center shadow-sm sm:p-10"
                >
                    <h2 class="mb-2 text-2xl font-bold text-foreground">
                        Select Your Seats
                    </h2>
                    <p class="mb-10 text-sm text-muted-foreground">
                        Front seats offer extra legroom.
                    </p>

                    <div
                        class="custom-scrollbar w-full overflow-x-auto pt-4 pb-8"
                    >
                        <div
                            class="mx-auto flex min-w-max flex-col items-center px-4"
                        >
                            <div
                                class="mb-[-20px] h-24 w-64 rounded-t-[100px] border-4 border-b-0 border-border bg-muted/20"
                            ></div>

                            <div
                                class="relative rounded-[40px] border-4 border-border bg-muted/20 px-6 py-10 sm:px-12 sm:py-14"
                            >
                                <template
                                    v-for="(
                                        seatsInRow, rowNumber
                                    ) in groupedSeats"
                                    :key="rowNumber"
                                >
                                    <div
                                        v-if="
                                            seatsInRow.some(
                                                (s) => s.is_exit_row,
                                            )
                                        "
                                        class="my-4 flex items-center justify-between opacity-80"
                                    >
                                        <div
                                            class="h-1 w-full rounded-full bg-red-500/20"
                                        ></div>
                                        <span
                                            class="mx-4 text-[10px] font-black tracking-[0.2em] text-red-500"
                                            >EXIT</span
                                        >
                                        <div
                                            class="h-1 w-full rounded-full bg-red-500/20"
                                        ></div>
                                    </div>

                                    <div
                                        class="mb-5 flex items-center justify-center gap-1.5 sm:gap-2"
                                    >
                                        <div
                                            class="mr-2 flex w-4 justify-center text-xs font-bold text-muted-foreground sm:mr-4 sm:w-6"
                                        >
                                            {{ rowNumber }}
                                        </div>

                                        <template
                                            v-for="(seat, index) in seatsInRow"
                                            :key="seat.id || 'aisle_' + index"
                                        >
                                            <div
                                                v-if="seat.is_aisle_space"
                                                class="w-4 shrink-0 sm:w-8"
                                            ></div>

                                            <button
                                                v-else
                                                @click="toggleSeat(seat)"
                                                :disabled="!seat.is_available"
                                                class="group relative flex h-12 w-10 shrink-0 flex-col items-center justify-start rounded-t-xl rounded-b-md border-2 transition-all duration-200 sm:h-14 sm:w-12"
                                                :class="[
                                                    !seat.is_available
                                                        ? 'cursor-not-allowed border-border bg-muted opacity-40'
                                                        : selectedSeats.some(
                                                                (s) =>
                                                                    s.id ===
                                                                    seat.id,
                                                            )
                                                          ? '-translate-y-1 transform border-primary bg-primary shadow-lg shadow-primary/30'
                                                          : seat.class ===
                                                              'first_class'
                                                            ? 'cursor-pointer border-purple-400 bg-purple-100 shadow-sm hover:-translate-y-0.5 hover:border-purple-500 hover:bg-purple-200'
                                                            : seat.class ===
                                                                'business'
                                                              ? 'cursor-pointer border-amber-400 bg-amber-100 shadow-sm hover:-translate-y-0.5 hover:border-amber-500 hover:bg-amber-200'
                                                              : 'cursor-pointer border-primary/20 bg-background hover:-translate-y-0.5 hover:border-primary hover:shadow-md',
                                                ]"
                                            >
                                                <div
                                                    class="mt-1 h-2.5 w-6 rounded-full transition-colors sm:mt-1.5 sm:h-3 sm:w-8"
                                                    :class="[
                                                        selectedSeats.some(
                                                            (s) =>
                                                                s.id ===
                                                                seat.id,
                                                        )
                                                            ? 'bg-background/20'
                                                            : seat.class ===
                                                                    'first_class' &&
                                                                seat.is_available
                                                              ? 'bg-purple-300'
                                                              : seat.class ===
                                                                      'business' &&
                                                                  seat.is_available
                                                                ? 'bg-amber-300'
                                                                : 'bg-muted',
                                                    ]"
                                                ></div>

                                                <span
                                                    class="mt-auto mb-1.5 text-xs font-bold sm:mb-2 sm:text-sm"
                                                    :class="[
                                                        !seat.is_available
                                                            ? 'text-muted-foreground'
                                                            : selectedSeats.some(
                                                                    (s) =>
                                                                        s.id ===
                                                                        seat.id,
                                                                )
                                                              ? 'text-primary-foreground'
                                                              : seat.class ===
                                                                  'first_class'
                                                                ? 'text-purple-700 group-hover:text-purple-900'
                                                                : seat.class ===
                                                                    'business'
                                                                  ? 'text-amber-700 group-hover:text-amber-900'
                                                                  : 'text-foreground group-hover:text-primary',
                                                    ]"
                                                >
                                                    {{
                                                        seat.seat_code.replace(
                                                            /[0-9]/g,
                                                            '',
                                                        )
                                                    }}
                                                </span>
                                            </button>
                                        </template>

                                        <div
                                            class="ml-2 flex w-4 justify-center text-xs font-bold text-muted-foreground sm:ml-4 sm:w-6"
                                        >
                                            {{ rowNumber }}
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-10 flex flex-wrap justify-center gap-4 text-sm font-medium text-muted-foreground sm:gap-6"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="h-5 w-5 rounded-md border-2 border-primary/20 bg-background"
                            ></div>
                            <span>Economy</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-5 w-5 rounded-md border-2 border-amber-400 bg-amber-100"
                            ></div>
                            <span>Business (+ $50)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-5 w-5 rounded-md border-2 border-purple-400 bg-purple-100"
                            ></div>
                            <span>First Class (+ $150)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-5 w-5 rounded-md border-2 border-primary bg-primary shadow-sm"
                            ></div>
                            <span>Selected</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-5 w-5 rounded-md border-2 border-border bg-muted opacity-40"
                            ></div>
                            <span>Unavailable</span>
                        </div>
                    </div>
                </div>

                <div
                    class="h-fit w-full rounded-2xl border border-border bg-card p-6 shadow-sm md:w-96"
                >
                    <h3 class="mb-6 text-lg font-bold text-foreground">
                        Flight Summary
                    </h3>

                    <div
                        class="mb-4 flex flex-col items-center justify-center rounded-xl border border-primary/10 bg-primary/5 p-4 text-center"
                    >
                        <span
                            class="mb-2 inline-block rounded-md bg-primary/10 px-3 py-1 text-xs font-bold tracking-widest text-primary uppercase"
                        >
                            {{ flight.airline_name || flight.airline_code }}
                        </span>
                        <p class="font-bold text-foreground">
                            {{ flight.aircraft?.model_name || 'Aircraft TBA' }}
                        </p>
                    </div>

                    <div class="mb-6 rounded-xl bg-muted/30 p-4 text-sm">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-lg font-bold text-foreground"
                                    >{{ flight.origin_airport }}</span
                                >
                                <span class="text-xs text-muted-foreground">{{
                                    formatTime(flight.departure_at)
                                }}</span>
                            </div>

                            <div class="flex flex-col items-center px-4">
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
                                    class="block text-lg font-bold text-foreground"
                                    >{{ flight.destination_airport }}</span
                                >
                                <span class="text-xs text-muted-foreground">{{
                                    formatTime(flight.arrival_at)
                                }}</span>
                            </div>
                        </div>

                        <div class="my-3 border-t border-border/50"></div>

                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-muted-foreground"
                            >
                                <span>Flight Number</span>
                                <span
                                    class="font-mono font-semibold text-foreground"
                                    >{{ flight.flight_number }}</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between text-muted-foreground"
                            >
                                <span>Flight Type</span>
                                <span
                                    v-if="
                                        !flight.transits ||
                                        flight.transits.length === 0
                                    "
                                    class="text-xs font-semibold tracking-wider text-emerald-500 uppercase"
                                    >Direct Flight</span
                                >
                                <span
                                    v-else
                                    class="text-xs font-semibold tracking-wider text-amber-500 uppercase"
                                    >{{ flight.transits.length }} Stop(s)</span
                                >
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedSeats.length > 0"
                        class="animate-in border-t border-border pt-6 duration-300 fade-in slide-in-from-bottom-2"
                    >
                        <h4 class="mb-3 text-sm font-semibold text-foreground">
                            Selected Seats ({{ selectedSeats.length }})
                        </h4>
                        <div class="mb-6 flex flex-wrap gap-2">
                            <span
                                v-for="seat in selectedSeats"
                                :key="seat.id"
                                class="rounded-md border border-primary/20 bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary transition-all hover:bg-primary hover:text-primary-foreground"
                            >
                                {{ seat.seat_code }}
                            </span>
                        </div>

                        <div class="mb-6 flex items-end justify-between">
                            <span
                                class="text-sm font-medium text-muted-foreground"
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
                            @click="proceedToBooking"
                            class="hover:bg-primary-hover w-full bg-primary text-primary-foreground shadow-md transition-all hover:shadow-lg"
                            size="lg"
                        >
                            Continue to Passenger Details
                        </Button>
                    </div>

                    <div
                        v-else
                        class="border-t border-border pt-6 text-center text-sm text-muted-foreground"
                    >
                        <div
                            class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-muted"
                        >
                            <svg
                                class="h-6 w-6 opacity-50"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                />
                            </svg>
                        </div>
                        Please select at least one seat to continue.
                    </div>
                </div>
            </div>
        </main>
    </AeroLayout>
</template>
