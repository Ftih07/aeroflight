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
    outbound_seats_map: Record<string, any>; // Menampung map bersarang per segment
    return_flight?: any;
    return_seats_map?: Record<string, any>;
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

// --- 1. STATE MANAGEMENT MULTI-SEGMENT ---
const activeFlightDirection = ref<'outbound' | 'return'>('outbound');
const activePassengerIndex = ref<number>(0);
const activeSegmentId = ref<string | number>(
    props.outbound_flight?.segments?.[0]?.id || '',
);

// Struktur data baru: selections.value[arah][index_penumpang][id_segment] = objekKursi
const selections = ref<Record<string, Record<number, Record<string, any>>>>({
    outbound: {},
    return: {},
});

// Inisialisasi struktur object kosong untuk setiap penumpang
props.passengers.forEach((_, i) => {
    selections.value.outbound[i] = {};
    selections.value.return[i] = {};
});

// --- 2. COMPUTED HELPERS UNTUK UI DINAMIS ---
const currentSegments = computed(() => {
    return activeFlightDirection.value === 'outbound'
        ? props.outbound_flight?.segments || []
        : props.return_flight?.segments || [];
});

const currentSeatMap = computed(() => {
    const mapSource =
        activeFlightDirection.value === 'outbound'
            ? props.outbound_seats_map
            : props.return_seats_map;

    return mapSource ? mapSource[activeSegmentId.value] : {};
});

const currentSegmentSelections = computed(() => {
    const currentDir = activeFlightDirection.value;
    const segId = activeSegmentId.value;
    const result: Record<number, any> = {};

    for (let pIndex = 0; pIndex < props.passengers.length; pIndex++) {
        if (
            selections.value[currentDir][pIndex] &&
            selections.value[currentDir][pIndex][segId]
        ) {
            result[pIndex] = selections.value[currentDir][pIndex][segId];
        }
    }

    return result;
});

// --- 3. LOGIC PEMILIHAN KURSI (DENGAN VALIDASI KELAS & ADJACENCY) ---
const toggleSeat = (seat: any) => {
    if (!seat.is_available) {
        return;
    }

    const pIndex = activePassengerIndex.value;
    const currentDir = activeFlightDirection.value;
    const segId = String(activeSegmentId.value);

    // Cek apakah kursi ini sudah diambil penumpang lain di segment yang sama
    const alreadySelectedByOther = Object.entries(
        currentSegmentSelections.value,
    ).find(([idx, s]) => s.id === seat.id && Number(idx) !== pIndex);

    if (alreadySelectedByOther) {
        return alert('Kursi ini sudah dipilih untuk penumpang lain.');
    }

    // Jika kursi sama di-klik ulang, berarti UNSELECT
    if (selections.value[currentDir][pIndex][segId]?.id === seat.id) {
        delete selections.value[currentDir][pIndex][segId];
        
        return;
    }

    // --- VALIDASI: HARUS DALAM KELAS YANG SAMA ---
    const passengerZeroSeat = selections.value[currentDir][0][segId];

    if (pIndex !== 0 && passengerZeroSeat) {
        if (passengerZeroSeat.class !== seat.class) {
            return alert(
                `Penumpang harus berada di kelas yang sama (${passengerZeroSeat.class.replace('_', ' ').toUpperCase()}) dengan Penumpang 1.`,
            );
        }
    }

    // --- VALIDASI: KURSI HARUS DEMPETAN JIKA > 1 PENUMPANG ---
    if (pIndex > 0) {
        const selectedSeatsInThisSegment = Object.values(
            currentSegmentSelections.value,
        ).filter((s: any) => s && s.id !== seat.id);

        if (selectedSeatsInThisSegment.length > 0) {
            const currentRow = parseInt(seat.seat_code.replace(/[^0-9]/g, ''));
            const currentColChar = seat.seat_code.replace(/[0-9]/g, '');
            const colCharCode = currentColChar.charCodeAt(0);

            const isAdjacent = selectedSeatsInThisSegment.some(
                (selSeat: any) => {
                    const selRow = parseInt(
                        selSeat.seat_code.replace(/[^0-9]/g, ''),
                    );
                    const selColChar = selSeat.seat_code.replace(/[0-9]/g, '');
                    const selColCharCode = selColChar.charCodeAt(0);

                    return (
                        selRow === currentRow &&
                        Math.abs(selColCharCode - colCharCode) === 1
                    );
                },
            );

            if (!isAdjacent) {
                return alert(
                    'Posisi kursi harus bersebelahan (dempetan) dengan penumpang lainnya dalam satu baris!',
                );
            }
        }
    }

    // Lolos semua validasi, simpan kursinya
    selections.value[currentDir][pIndex][segId] = seat;

    // Auto-advance ke penumpang berikutnya JIKA belum milih kursi
    if (
        pIndex < props.passengers.length - 1 &&
        !selections.value[currentDir][pIndex + 1][segId]
    ) {
        activePassengerIndex.value = pIndex + 1;
    }
};

const switchTab = (direction: 'outbound' | 'return') => {
    activeFlightDirection.value = direction;
    activePassengerIndex.value = 0;
    const flights =
        direction === 'outbound' ? props.outbound_flight : props.return_flight;
    activeSegmentId.value = flights?.segments?.[0]?.id || '';
};

// Cek apakah semua kursi di semua segment dan semua arah sudah terisi
const isReadyToCheckout = computed(() => {
    let isReady = true;

    // Cek Outbound
    for (let pIndex = 0; pIndex < props.passengers.length; pIndex++) {
        props.outbound_flight?.segments?.forEach((seg: any) => {
            if (!selections.value.outbound[pIndex][seg.id]) {
                isReady = false;
            }
        });
    }

    // Cek Return
    if (props.trip_type === 'round_trip' && props.return_flight) {
        for (let pIndex = 0; pIndex < props.passengers.length; pIndex++) {
            props.return_flight?.segments?.forEach((seg: any) => {
                if (!selections.value.return[pIndex][seg.id]) {
                    isReady = false;
                }
            });
        }
    }

    return isReady;
});

// --- 4. PERHITUNGAN HARGA TOTAL ---
const totalSeatPrice = computed(() => {
    let total = 0;
    ['outbound', 'return'].forEach((dir) => {
        if (dir === 'return' && props.trip_type !== 'round_trip') {
            return;
        }

        Object.values(selections.value[dir]).forEach(
            (passengerSelections: any) => {
                Object.values(passengerSelections).forEach((seatObj: any) => {
                    total += Number(seatObj.additional_price_usd || 0);
                });
            },
        );
    });

    return total;
});

const totalPrice = computed(() => {
    const baseAndBaggageTotal = props.passengers.reduce((sum, p) => {
        let pTotal =
            Number(props.outbound_flight?.starting_price || 0) +
            Number(p.baggage_fee_usd || 0);

        if (props.trip_type === 'round_trip' && props.return_flight) {
            pTotal += Number(props.return_flight.starting_price || 0);
        }

        return sum + pTotal;
    }, 0);

    return baseAndBaggageTotal + totalSeatPrice.value;
});

// --- 5. FORMATTING HELPERS ---
const calculateDuration = (departure: string, arrival: string): string => {
    const diffMs = new Date(arrival).getTime() - new Date(departure).getTime();

    if (isNaN(diffMs)) {
        return '-';
    }

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
        return;
    }

    // Mapping ulang object selections ke array untuk dilempar ke Controller
    const finalPassengers = props.passengers.map((p, i) => {
        const outSeatsArray = Object.keys(selections.value.outbound[i]).map(
            (segId) => ({
                segment_id: segId,
                seat: selections.value.outbound[i][segId],
            }),
        );

        const retSeatsArray =
            props.trip_type === 'round_trip'
                ? Object.keys(selections.value.return[i]).map((segId) => ({
                      segment_id: segId,
                      seat: selections.value.return[i][segId],
                  }))
                : null;

        return {
            ...p,
            outbound_seats: outSeatsArray,
            return_seats: retSeatsArray,
        };
    });

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
                        class="mb-6 flex justify-center gap-4 border-b border-border pb-4"
                    >
                        <button
                            @click="switchTab('outbound')"
                            :class="[
                                'relative rounded-full px-6 py-2 font-bold transition',
                                activeFlightDirection === 'outbound'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80',
                            ]"
                        >
                            🛫 Outbound Flight
                        </button>
                        <button
                            @click="switchTab('return')"
                            :class="[
                                'relative rounded-full px-6 py-2 font-bold transition',
                                activeFlightDirection === 'return'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80',
                            ]"
                        >
                            🛬 Return Flight
                        </button>
                    </div>

                    <div class="mb-8 flex flex-wrap justify-center gap-2">
                        <button
                            v-for="(seg, idx) in currentSegments"
                            :key="seg.id"
                            @click="activeSegmentId = seg.id"
                            :class="[
                                'flex flex-col items-center rounded-lg border-2 px-4 py-2 transition-all',
                                activeSegmentId === seg.id
                                    ? 'border-primary bg-primary/10'
                                    : 'border-border bg-muted/30 hover:border-primary/30',
                            ]"
                        >
                            <span
                                class="text-xs font-bold text-muted-foreground uppercase"
                                >Flight {{ idx + 1 }}</span
                            >
                            <span class="text-sm font-black text-foreground">
                                {{ seg.origin_airport }} ✈️
                                {{ seg.destination_airport }}
                            </span>
                        </button>
                    </div>

                    <h2 class="mb-2 text-2xl font-bold text-foreground">
                        Select Seats for
                        {{
                            getCityName(
                                currentSegments.find(
                                    (s: any) => s.id === activeSegmentId,
                                )?.origin_airport,
                            )
                        }}
                        to
                        {{
                            getCityName(
                                currentSegments.find(
                                    (s: any) => s.id === activeSegmentId,
                                )?.destination_airport,
                            )
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
                                v-if="
                                    selections[activeFlightDirection][i][
                                        activeSegmentId
                                    ]
                                "
                                class="ml-2 rounded bg-primary px-1.5 py-0.5 text-xs text-primary-foreground"
                            >
                                {{
                                    selections[activeFlightDirection][i][
                                        activeSegmentId
                                    ].seat_code
                                }}
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
                                                (s: any) => s.is_exit_row,
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
                                                                currentSegmentSelections,
                                                            ).some(
                                                                (s: any) =>
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
                                                            currentSegmentSelections,
                                                        ).some(
                                                            (s: any) =>
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
                                                                    currentSegmentSelections,
                                                                ).some(
                                                                    (s: any) =>
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
                                        outbound_flight.segments?.[0]
                                            ?.airlineData?.name ||
                                        outbound_flight.segments?.[0]
                                            ?.airline_code
                                    }}
                                </span>
                                <span
                                    class="text-[10px] font-medium tracking-tight text-muted-foreground uppercase"
                                >
                                    {{
                                        outbound_flight.segments?.[0]?.aircraft
                                            ?.model_name || 'Aircraft TBA'
                                    }}
                                </span>
                            </div>
                            <span
                                class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold"
                            >
                                {{
                                    displayFlightNumber(
                                        outbound_flight.segments?.[0]
                                            ?.airline_code,
                                        outbound_flight.segments?.[0]
                                            ?.flight_number,
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
                                    v-if="
                                        !outbound_flight.stop_count ||
                                        outbound_flight.stop_count === 0
                                    "
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
                                >
                                    {{
                                        calculateDuration(
                                            outbound_flight.departure_at,
                                            outbound_flight.arrival_at,
                                        )
                                    }}
                                </span>
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
                                        return_flight.segments?.[0]?.airlineData
                                            ?.name ||
                                        return_flight.segments?.[0]
                                            ?.airline_code
                                    }}
                                </span>
                                <span
                                    class="text-[10px] font-medium tracking-tight text-muted-foreground uppercase"
                                >
                                    {{
                                        return_flight.segments?.[0]?.aircraft
                                            ?.model_name || 'Aircraft TBA'
                                    }}
                                </span>
                            </div>
                            <span
                                class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold"
                            >
                                {{
                                    displayFlightNumber(
                                        return_flight.segments?.[0]
                                            ?.airline_code,
                                        return_flight.segments?.[0]
                                            ?.flight_number,
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
                                    >{{ return_flight.stop_count }} Stop</span
                                >
                                <div class="my-1 h-[2px] w-8 bg-border"></div>
                                <span
                                    class="text-[10px] font-bold text-muted-foreground"
                                >
                                    {{
                                        calculateDuration(
                                            return_flight.departure_at,
                                            return_flight.arrival_at,
                                        )
                                    }}
                                </span>
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
                            <span class="font-semibold text-foreground">
                                +
                                {{
                                    new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD',
                                    }).format(totalSeatPrice)
                                }}
                            </span>
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
                                : 'cursor-not-allowed bg-muted text-muted-foreground'
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
