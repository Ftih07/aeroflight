<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: undefined });

const props = defineProps<{
    booking_session: string | number;
    trip_type: string;
    passengers: any[]; // Data dari PassengerForm sebelumnya
    outbound_flight: any;
    outbound_seats: Record<string, any[]>;
    return_flight?: any;
    return_seats?: Record<string, any[]>;
}>();

// --- HELPER NAMA KOTA BANDARA ---
const allAirports = ref<any[]>([]);

onMounted(async () => {
    try {
        const resAirports = await fetch(
            'https://gist.githubusercontent.com/tdreyno/4278655/raw/7b0762c09b519f40397e4c3e100b097d861f5588/airports.json',
        );

        if (resAirports.ok) {
            const dataAirports = await resAirports.json();
            allAirports.value = dataAirports.filter(
                (a: any) => a.code && a.code.trim() !== '',
            );
        }
    } catch (error) {
        console.error('Gagal mengambil data bandara:', error);
    }
});

const getCityName = (code: string) => {
    if (!allAirports.value || allAirports.value.length === 0) {
        return '';
    }

    const airport = allAirports.value.find((a: any) => a.code === code);

    return airport ? airport.name : '';
};

// --- 1. STATE MANAGEMENT ---
const activeFlightTab = ref<'outbound' | 'return'>('outbound');
const activePassengerIndex = ref<number>(0);

// Objek untuk menyimpan kursi { indexPenumpang: seatObject }
const outboundSelections = ref<Record<number, any>>({});
const returnSelections = ref<Record<number, any>>({});

// --- 2. COMPUTED HELPERS UNTUK UI DINAMIS ---
const currentSeatMap = computed(() =>
    activeFlightTab.value === 'outbound'
        ? props.outbound_seats
        : props.return_seats,
);

const currentSelections = computed(() =>
    activeFlightTab.value === 'outbound'
        ? outboundSelections.value
        : returnSelections.value,
);

// --- 3. LOGIC PEMILIHAN KURSI ---
const toggleSeat = (seat: any) => {
    if (!seat.is_available) {
        return [];
    }

    const selections = currentSelections.value;
    const pIndex = activePassengerIndex.value;

    const alreadySelectedByOther = Object.entries(selections).find(
        ([idx, s]) => s.id === seat.id && Number(idx) !== pIndex,
    );

    if (alreadySelectedByOther) {
        alert('Kursi ini sudah dipilih untuk penumpang lain.');

        return;
    }

    if (selections[pIndex]?.id === seat.id) {
        delete selections[pIndex];
    } else {
        selections[pIndex] = seat;

        if (pIndex < props.passengers.length - 1 && !selections[pIndex + 1]) {
            activePassengerIndex.value = pIndex + 1;
        }
    }
};

const isReadyToCheckout = computed(() => {
    const outboundDone =
        Object.keys(outboundSelections.value).length ===
        props.passengers.length;

    if (props.trip_type === 'round_trip') {
        const returnDone =
            Object.keys(returnSelections.value).length ===
            props.passengers.length;

        return outboundDone && returnDone;
    }

    return outboundDone;
});

// --- 4. PERHITUNGAN HARGA TOTAL ---
const totalSeatPrice = computed(() => {
    let total = 0;
    Object.values(outboundSelections.value).forEach(
        (seat: any) => (total += Number(seat.additional_price_usd)),
    );
    Object.values(returnSelections.value).forEach(
        (seat: any) => (total += Number(seat.additional_price_usd)),
    );

    return total;
});

const totalPrice = computed(() => {
    const baseAndBaggageTotal = props.passengers.reduce((sum, p) => {
        let pTotal =
            Number(props.outbound_flight.base_price_usd) +
            Number(p.baggage_fee_usd || 0);

        if (props.trip_type === 'round_trip' && props.return_flight) {
            pTotal += Number(props.return_flight.base_price_usd);
        }

        return sum + pTotal;
    }, 0);

    return baseAndBaggageTotal + totalSeatPrice.value;
});

// --- 5. FORMATTING HELPERS ---
const calculateDuration = (departure: string, arrival: string): string => {
    const diffMs = new Date(arrival).getTime() - new Date(departure).getTime();
    const diffHrs = Math.floor(diffMs / (1000 * 60 * 60));
    const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    return diffHrs === 0 ? `${diffMins}m` : `${diffHrs}h ${diffMins}m`;
};

const formatTime = (dateString: string): string => {
    const date = new Date(dateString);

    return isNaN(date.getTime())
        ? '-'
        : date.toLocaleTimeString('en-GB', {
              hour: '2-digit',
              minute: '2-digit',
              hour12: false,
          });
};

const displayFlightNumber = (airlineCode: string, flightNumber: string) => {
    const num = String(flightNumber || '').toUpperCase();

    return num.includes('-')
        ? num
        : `${String(airlineCode || '').toUpperCase()}-${num}`;
};

// --- 6. SUBMIT KE CHECKOUT ---
const proceedToCheckout = () => {
    if (!isReadyToCheckout.value) {
        return [];
    }

    const finalPassengers = props.passengers.map((p, i) => ({
        ...p,
        outbound_seat: outboundSelections.value[i],
        return_seat:
            props.trip_type === 'round_trip' ? returnSelections.value[i] : null,
    }));

    router.post(`/bookings/${props.booking_session}/checkout`, {
        passengers: finalPassengers,
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
                    <div
                        v-if="trip_type === 'round_trip'"
                        class="mb-8 flex justify-center gap-4 border-b border-border pb-4"
                    >
                        <button
                            @click="
                                activeFlightTab = 'outbound';
                                activePassengerIndex = 0;
                            "
                            :class="[
                                'relative rounded-full px-6 py-2 font-bold transition',
                                activeFlightTab === 'outbound'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80',
                            ]"
                        >
                            🛫 Outbound Flight
                            <div
                                v-if="
                                    Object.keys(outboundSelections).length ===
                                    passengers.length
                                "
                                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 text-[10px] text-white"
                            >
                                ✓
                            </div>
                        </button>
                        <button
                            @click="
                                activeFlightTab = 'return';
                                activePassengerIndex = 0;
                            "
                            :class="[
                                'relative rounded-full px-6 py-2 font-bold transition',
                                activeFlightTab === 'return'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80',
                            ]"
                        >
                            🛬 Return Flight
                            <div
                                v-if="
                                    Object.keys(returnSelections).length ===
                                    passengers.length
                                "
                                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 text-[10px] text-white"
                            >
                                ✓
                            </div>
                        </button>
                    </div>

                    <h2 class="mb-2 text-2xl font-bold text-foreground">
                        Select Seats for
                        {{
                            activeFlightTab === 'outbound'
                                ? 'Outbound'
                                : 'Return'
                        }}
                    </h2>
                    <p class="mb-6 text-sm text-muted-foreground">
                        Premium seats offer extra legroom and priority boarding.
                    </p>

                    <div class="mb-8 flex flex-wrap justify-center gap-3">
                        <button
                            v-for="(p, i) in passengers"
                            :key="i"
                            @click="activePassengerIndex = i"
                            :class="[
                                'rounded-lg border-2 px-4 py-2 text-sm font-bold transition-all',
                                activePassengerIndex === i
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-background text-muted-foreground hover:border-primary/50',
                            ]"
                        >
                            Passenger {{ i + 1 }}: {{ p.first_name }}
                            <span
                                v-if="currentSelections[i]"
                                class="ml-2 rounded bg-primary px-1.5 py-0.5 text-xs text-primary-foreground"
                            >
                                {{ currentSelections[i].seat_code }}
                            </span>
                        </button>
                    </div>

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
                                    ) in currentSeatMap"
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
                                                        : Object.values(
                                                                currentSelections,
                                                            ).some(
                                                                (s) =>
                                                                    s.id ===
                                                                    seat.id,
                                                            )
                                                          ? '-translate-y-1 transform border-primary bg-primary shadow-lg shadow-primary/30'
                                                          : seat.class ===
                                                              'first_class'
                                                            ? 'cursor-pointer border-purple-400 bg-purple-100 hover:-translate-y-0.5 hover:border-purple-500'
                                                            : seat.class ===
                                                                'business'
                                                              ? 'cursor-pointer border-amber-400 bg-amber-100 hover:-translate-y-0.5 hover:border-amber-500'
                                                              : 'cursor-pointer border-primary/20 bg-background hover:-translate-y-0.5 hover:border-primary',
                                                ]"
                                            >
                                                <div
                                                    class="mt-1 h-2.5 w-6 rounded-full transition-colors sm:mt-1.5 sm:h-3 sm:w-8"
                                                    :class="[
                                                        Object.values(
                                                            currentSelections,
                                                        ).some(
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
                                                            : Object.values(
                                                                    currentSelections,
                                                                ).some(
                                                                    (s) =>
                                                                        s.id ===
                                                                        seat.id,
                                                                )
                                                              ? 'text-primary-foreground'
                                                              : seat.class ===
                                                                  'first_class'
                                                                ? 'text-purple-700'
                                                                : seat.class ===
                                                                    'business'
                                                                  ? 'text-amber-700'
                                                                  : 'text-foreground',
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
                        class="mt-4 flex flex-wrap justify-center gap-4 text-sm font-medium text-muted-foreground sm:gap-6"
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
                            <span>Business</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-5 w-5 rounded-md border-2 border-purple-400 bg-purple-100"
                            ></div>
                            <span>First Class</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-5 w-5 rounded-md border-2 border-primary bg-primary"
                            ></div>
                            <span>Selected</span>
                        </div>
                    </div>
                </div>

                <div
                    class="h-fit w-full rounded-2xl border border-border bg-card p-6 shadow-sm md:w-96 lg:sticky lg:top-24"
                >
                    <h3 class="mb-6 text-lg font-bold text-foreground">
                        Flight Summary
                    </h3>

                    <div
                        class="relative mb-4 overflow-hidden rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-emerald-500"
                        ></div>

                        <div
                            class="mb-3 flex items-start justify-between border-b border-border/50 pb-3"
                        >
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-foreground">
                                    {{
                                        outbound_flight.airline_name ||
                                        outbound_flight.airline_code
                                    }}
                                </span>
                                <span
                                    class="text-[10px] font-medium tracking-tight text-muted-foreground uppercase"
                                >
                                    {{
                                        outbound_flight.aircraft?.model_name ||
                                        'Aircraft TBA'
                                    }}
                                </span>
                            </div>
                            <span
                                class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold"
                            >
                                {{
                                    displayFlightNumber(
                                        outbound_flight.airline_code,
                                        outbound_flight.flight_number,
                                    )
                                }}
                            </span>
                        </div>

                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-lg font-black text-foreground"
                                    >{{ outbound_flight.origin_airport }}</span
                                >
                                <span
                                    class="mb-1 block max-w-[80px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        getCityName(
                                            outbound_flight.origin_airport,
                                        )
                                    }}
                                </span>
                                <span class="text-xs text-muted-foreground">{{
                                    formatTime(outbound_flight.departure_at)
                                }}</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <span
                                    v-if="outbound_flight.stop_count === 0"
                                    class="text-[10px] font-bold text-emerald-500 uppercase"
                                    >Direct</span
                                >
                                <span
                                    v-else
                                    class="text-[10px] font-bold text-amber-500 uppercase"
                                    >{{ outbound_flight.stop_count }} Stop</span
                                >
                                <div class="my-1 h-[2px] w-8 bg-border"></div>
                                <span
                                    class="text-[10px] font-bold text-muted-foreground"
                                    >{{
                                        calculateDuration(
                                            outbound_flight.departure_at,
                                            outbound_flight.arrival_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span
                                    class="block text-lg font-black text-foreground"
                                    >{{
                                        outbound_flight.destination_airport
                                    }}</span
                                >
                                <span
                                    class="mb-1 ml-auto block max-w-[80px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        getCityName(
                                            outbound_flight.destination_airport,
                                        )
                                    }}
                                </span>
                                <span class="text-xs text-muted-foreground">{{
                                    formatTime(outbound_flight.arrival_at)
                                }}</span>
                            </div>
                        </div>

                        <div
                            class="mt-3 flex justify-between rounded border border-border bg-background p-2 text-xs shadow-sm"
                        >
                            <span class="font-medium text-muted-foreground"
                                >Seats Selected:</span
                            >
                            <span
                                class="font-bold"
                                :class="
                                    Object.keys(outboundSelections).length ===
                                    passengers.length
                                        ? 'text-emerald-500'
                                        : 'text-primary'
                                "
                            >
                                {{ Object.keys(outboundSelections).length }} /
                                {{ passengers.length }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="trip_type === 'round_trip' && return_flight"
                        class="relative mb-6 overflow-hidden rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-blue-500"
                        ></div>

                        <div
                            class="mb-3 flex items-start justify-between border-b border-border/50 pb-3"
                        >
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-foreground">
                                    {{
                                        return_flight.airline_name ||
                                        return_flight.airline_code
                                    }}
                                </span>
                                <span
                                    class="text-[10px] font-medium tracking-tight text-muted-foreground uppercase"
                                >
                                    {{
                                        return_flight.aircraft?.model_name ||
                                        'Aircraft TBA'
                                    }}
                                </span>
                            </div>
                            <span
                                class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold"
                            >
                                {{
                                    displayFlightNumber(
                                        return_flight.airline_code,
                                        return_flight.flight_number,
                                    )
                                }}
                            </span>
                        </div>

                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-lg font-black text-foreground"
                                    >{{ return_flight.origin_airport }}</span
                                >
                                <span
                                    class="mb-1 block max-w-[80px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        getCityName(
                                            return_flight.origin_airport,
                                        )
                                    }}
                                </span>
                                <span class="text-xs text-muted-foreground">{{
                                    formatTime(return_flight.departure_at)
                                }}</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <span
                                    v-if="return_flight.stop_count === 0"
                                    class="text-[10px] font-bold text-emerald-500 uppercase"
                                    >Direct</span
                                >
                                <span
                                    v-else
                                    class="text-[10px] font-bold text-amber-500 uppercase"
                                    >{{ return_flight.stop_count }} Stop</span
                                >
                                <div class="my-1 h-[2px] w-8 bg-border"></div>
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
                                    class="block text-lg font-black text-foreground"
                                    >{{
                                        return_flight.destination_airport
                                    }}</span
                                >
                                <span
                                    class="mb-1 ml-auto block max-w-[80px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        getCityName(
                                            return_flight.destination_airport,
                                        )
                                    }}
                                </span>
                                <span class="text-xs text-muted-foreground">{{
                                    formatTime(return_flight.arrival_at)
                                }}</span>
                            </div>
                        </div>

                        <div
                            class="mt-3 flex justify-between rounded border border-border bg-background p-2 text-xs shadow-sm"
                        >
                            <span class="font-medium text-muted-foreground"
                                >Seats Selected:</span
                            >
                            <span
                                class="font-bold"
                                :class="
                                    Object.keys(returnSelections).length ===
                                    passengers.length
                                        ? 'text-emerald-500'
                                        : 'text-blue-600'
                                "
                            >
                                {{ Object.keys(returnSelections).length }} /
                                {{ passengers.length }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="mb-6 flex flex-col gap-3 border-t border-border pt-5 text-sm"
                    >
                        <div
                            v-if="totalSeatPrice > 0"
                            class="flex items-center justify-between"
                        >
                            <span class="text-muted-foreground"
                                >Seat Selection Fees</span
                            >
                            <span class="font-semibold text-foreground"
                                >+
                                {{
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD',
                                    }).format(totalSeatPrice)
                                }}</span
                            >
                        </div>
                        <div class="mt-2 flex items-end justify-between">
                            <span class="font-bold text-muted-foreground"
                                >Final Total</span
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
                    </div>

                    <Button
                        @click="proceedToCheckout"
                        :disabled="!isReadyToCheckout"
                        class="w-full shadow-md transition-all disabled:opacity-50"
                        size="lg"
                        :class="
                            isReadyToCheckout
                                ? 'hover:bg-primary-hover bg-primary text-primary-foreground hover:shadow-lg'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{
                            isReadyToCheckout
                                ? 'Proceed to Payment'
                                : 'Select all seats to continue'
                        }}
                    </Button>
                </div>
            </div>
        </main>
    </AeroLayout>
</template>
