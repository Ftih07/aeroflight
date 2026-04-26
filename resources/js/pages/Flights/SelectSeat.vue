<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: undefined });

const props = defineProps<{
    booking_session: string | number;
    trip_type: string;
    passengers: any[];
    outbound_flight: any;
    outbound_seats_map: Record<string, any>;
    return_flight?: any;
    return_seats_map?: Record<string, any>;
    promos: any[];
    insurances: any[];
    availablePoints: number;
}>();

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
    if (!allAirports.value || allAirports.value.length === 0) return '';
    const airport = allAirports.value.find((a: any) => a.code === code);
    return airport ? airport.name : '';
};

// --- STATE MANAGEMENT ---
const activeFlightDirection = ref<'outbound' | 'return'>('outbound');
const activePassengerIndex = ref<number>(0);
const activeSegmentId = ref<string | number>(
    props.outbound_flight?.segments?.[0]?.id || '',
);

const selections = ref<Record<string, Record<number, Record<string, any>>>>({
    outbound: {},
    return: {},
});

props.passengers.forEach((_, i) => {
    selections.value.outbound[i] = {};
    selections.value.return[i] = {};
});

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

const toggleSeat = (seat: any) => {
    if (!seat.is_available) return;

    const pIndex = activePassengerIndex.value;
    const currentDir = activeFlightDirection.value;
    const segId = String(activeSegmentId.value);

    const alreadySelectedByOther = Object.entries(
        currentSegmentSelections.value,
    ).find(([idx, s]) => s.id === seat.id && Number(idx) !== pIndex);
    if (alreadySelectedByOther)
        return alert('Kursi ini sudah dipilih untuk penumpang lain.');

    if (selections.value[currentDir][pIndex][segId]?.id === seat.id) {
        delete selections.value[currentDir][pIndex][segId];
        return;
    }

    const passengerZeroSeat = selections.value[currentDir][0][segId];
    if (pIndex !== 0 && passengerZeroSeat) {
        if (passengerZeroSeat.class !== seat.class) {
            return alert(
                `Penumpang harus berada di kelas yang sama (${passengerZeroSeat.class.replace('_', ' ').toUpperCase()}) dengan Penumpang 1.`,
            );
        }
    }

    selections.value[currentDir][pIndex][segId] = seat;
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

const isReadyToCheckout = computed(() => {
    let isReady = true;
    for (let pIndex = 0; pIndex < props.passengers.length; pIndex++) {
        props.outbound_flight?.segments?.forEach((seg: any) => {
            if (!selections.value.outbound[pIndex][seg.id]) isReady = false;
        });
    }
    if (props.trip_type === 'round_trip' && props.return_flight) {
        for (let pIndex = 0; pIndex < props.passengers.length; pIndex++) {
            props.return_flight?.segments?.forEach((seg: any) => {
                if (!selections.value.return[pIndex][seg.id]) isReady = false;
            });
        }
    }
    return isReady;
});

// --- STATE PROMO, ASURANSI, LOYALTI ---
const inputPromoCode = ref('');
const appliedPromo = ref<any>(null);
const promoError = ref('');
const selectedInsuranceId = ref<number | null>(null);
const pointsToUse = ref<number>(0);

const applyPromo = () => {
    promoError.value = '';
    appliedPromo.value = null;
    if (!inputPromoCode.value) return;

    const foundPromo = props.promos.find(
        (p) =>
            p.code.toUpperCase() === inputPromoCode.value.toUpperCase() &&
            p.quota !== 0,
    );
    if (foundPromo) appliedPromo.value = foundPromo;
    else promoError.value = 'Invalid, expired, or fully redeemed code.';
};

const removePromo = () => {
    appliedPromo.value = null;
    inputPromoCode.value = '';
    promoError.value = '';
};

const totalSeatPrice = computed(() => {
    let total = 0;
    ['outbound', 'return'].forEach((dir) => {
        if (dir === 'return' && props.trip_type !== 'round_trip') return;
        Object.values(selections.value[dir]).forEach((pSelections: any) => {
            Object.values(pSelections).forEach((sObj: any) => {
                total += Number(sObj.additional_price_usd || 0);
            });
        });
    });
    return total;
});

const totalPriceObj = computed(() => {
    const totalOutbound = props.passengers.reduce((sum, p) => {
        return (
            sum +
            Number(props.outbound_flight?.starting_price || 0) +
            Number(p.baggage_fee_usd || 0)
        );
    }, 0);

    const totalReturn =
        props.trip_type === 'round_trip' && props.return_flight
            ? props.passengers.reduce(
                  (sum) =>
                      sum + Number(props.return_flight.starting_price || 0),
                  0,
              )
            : 0;

    const baseAndBaggageTotal = totalOutbound + totalReturn;
    let subTotal = baseAndBaggageTotal + totalSeatPrice.value;

    let insuranceTotal = 0;
    if (selectedInsuranceId.value) {
        const ins = props.insurances.find(
            (i) => i.id === selectedInsuranceId.value,
        );
        if (ins)
            insuranceTotal = Number(ins.price_usd) * props.passengers.length;
    }
    subTotal += insuranceTotal;

    let discountTotal = 0;
    if (appliedPromo.value) {
        if (appliedPromo.value.discount_type === 'percentage') {
            discountTotal =
                subTotal * (Number(appliedPromo.value.discount_value) / 100);
        } else {
            discountTotal = Number(appliedPromo.value.discount_value);
        }
    }

    let totalAfterDiscount = Math.max(0, subTotal - discountTotal);

    if (pointsToUse.value > props.availablePoints)
        pointsToUse.value = props.availablePoints;
    if (pointsToUse.value > totalAfterDiscount)
        pointsToUse.value = Math.floor(totalAfterDiscount);
    if (pointsToUse.value < 0 || !pointsToUse.value) pointsToUse.value = 0;

    const finalPay = Math.max(0, totalAfterDiscount - pointsToUse.value);

    return {
        base: baseAndBaggageTotal,
        seats: totalSeatPrice.value,
        insurance: insuranceTotal,
        discount: discountTotal,
        finalPay: finalPay,
        pointsEarned: Math.floor(finalPay),
    };
});

const calculateDuration = (departure: string, arrival: string): string => {
    const diffMs = new Date(arrival).getTime() - new Date(departure).getTime();
    if (isNaN(diffMs)) return '-';
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

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
};

const proceedToCheckout = () => {
    if (!isReadyToCheckout.value) return;

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
        promo_code: appliedPromo.value ? appliedPromo.value.code : null,
        insurance_id: selectedInsuranceId.value,
        points_used: pointsToUse.value,
    });
};
</script>

<template>
    <Head title="Select Seats & Add-ons" />

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
                            <span class="text-sm font-black text-foreground"
                                >{{ seg.origin_airport }} ✈️
                                {{ seg.destination_airport }}</span
                            >
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
                                                    >{{
                                                        seat.seat_code.replace(
                                                            /[0-9]/g,
                                                            '',
                                                        )
                                                    }}</span
                                                >
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
                                <span
                                    class="text-sm font-bold text-foreground"
                                    >{{
                                        outbound_flight.segments?.[0]
                                            ?.airlineData?.name ||
                                        outbound_flight.segments?.[0]
                                            ?.airline_code
                                    }}</span
                                >
                                <span
                                    class="text-[10px] font-medium tracking-tight text-muted-foreground uppercase"
                                    >{{
                                        outbound_flight.segments?.[0]?.aircraft
                                            ?.model_name || 'Aircraft TBA'
                                    }}</span
                                >
                            </div>
                            <span
                                class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold"
                                >{{
                                    displayFlightNumber(
                                        outbound_flight.segments?.[0]
                                            ?.airline_code,
                                        outbound_flight.segments?.[0]
                                            ?.flight_number,
                                    )
                                }}</span
                            >
                        </div>
                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-lg font-black text-foreground"
                                    >{{ outbound_flight.origin_airport }}</span
                                >
                                <span
                                    class="mb-1 block max-w-[80px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >{{
                                        getCityName(
                                            outbound_flight.origin_airport,
                                        )
                                    }}</span
                                >
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
                                    >{{
                                        getCityName(
                                            outbound_flight.destination_airport,
                                        )
                                    }}</span
                                >
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
                                <span
                                    class="text-sm font-bold text-foreground"
                                    >{{
                                        return_flight.segments?.[0]?.airlineData
                                            ?.name ||
                                        return_flight.segments?.[0]
                                            ?.airline_code
                                    }}</span
                                >
                                <span
                                    class="text-[10px] font-medium tracking-tight text-muted-foreground uppercase"
                                    >{{
                                        return_flight.segments?.[0]?.aircraft
                                            ?.model_name || 'Aircraft TBA'
                                    }}</span
                                >
                            </div>
                            <span
                                class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold"
                                >{{
                                    displayFlightNumber(
                                        return_flight.segments?.[0]
                                            ?.airline_code,
                                        return_flight.segments?.[0]
                                            ?.flight_number,
                                    )
                                }}</span
                            >
                        </div>
                        <div class="mb-3 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-lg font-black text-foreground"
                                    >{{ return_flight.origin_airport }}</span
                                >
                                <span
                                    class="mb-1 block max-w-[80px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >{{
                                        getCityName(
                                            return_flight.origin_airport,
                                        )
                                    }}</span
                                >
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
                                    >{{
                                        getCityName(
                                            return_flight.destination_airport,
                                        )
                                    }}</span
                                >
                                <span class="text-xs text-muted-foreground">{{
                                    formatTime(return_flight.arrival_at)
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mb-6 flex flex-col gap-4 border-t border-border pt-5 text-sm"
                    >
                        <div
                            v-if="insurances && insurances.length > 0"
                            class="mb-2"
                        >
                            <p class="mb-2 font-bold text-foreground">
                                Travel Protection
                            </p>
                            <div class="space-y-2">
                                <label
                                    v-for="ins in insurances"
                                    :key="ins.id"
                                    class="flex cursor-pointer items-start gap-2 rounded-lg border border-border p-3 transition hover:bg-muted/30"
                                    :class="{
                                        'border-primary bg-primary/5':
                                            selectedInsuranceId === ins.id,
                                    }"
                                >
                                    <input
                                        type="radio"
                                        v-model="selectedInsuranceId"
                                        :value="ins.id"
                                        class="mt-1 h-4 w-4 text-primary focus:ring-primary"
                                    />
                                    <div class="flex-1">
                                        <p class="font-bold text-foreground">
                                            {{ ins.name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ ins.description }}
                                        </p>
                                    </div>
                                    <span class="font-bold text-foreground"
                                        >+${{ ins.price_usd
                                        }}<span
                                            class="text-[10px] font-normal text-muted-foreground"
                                            >/pax</span
                                        ></span
                                    >
                                </label>
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-3 transition hover:bg-muted/30"
                                >
                                    <input
                                        type="radio"
                                        v-model="selectedInsuranceId"
                                        :value="null"
                                        class="h-4 w-4 text-primary focus:ring-primary"
                                    />
                                    <span class="font-medium text-foreground"
                                        >No, I'll risk it.</span
                                    >
                                </label>
                            </div>
                        </div>

                        <div
                            class="mb-2 rounded-lg border border-border bg-muted/10 p-3"
                        >
                            <p
                                class="mb-2 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Promo Code
                            </p>
                            <div
                                v-if="appliedPromo"
                                class="flex items-center justify-between rounded bg-emerald-500/10 px-3 py-2"
                            >
                                <span
                                    class="font-mono text-xs font-bold text-emerald-600"
                                    >✓ {{ appliedPromo.code }} Applied</span
                                >
                                <button
                                    @click="removePromo"
                                    class="text-xs font-bold text-destructive hover:underline"
                                >
                                    Remove
                                </button>
                            </div>
                            <div v-else class="flex gap-2">
                                <input
                                    type="text"
                                    v-model="inputPromoCode"
                                    placeholder="Enter code"
                                    class="aero-input flex-1 rounded border border-border px-3 py-1.5 text-sm uppercase"
                                />
                                <Button
                                    @click="applyPromo"
                                    variant="secondary"
                                    size="sm"
                                    class="shrink-0"
                                    >Apply</Button
                                >
                            </div>
                            <p
                                v-if="promoError"
                                class="mt-1 text-xs text-destructive"
                            >
                                {{ promoError }}
                            </p>
                        </div>

                        <div
                            v-if="availablePoints > 0"
                            class="mb-2 rounded-lg border border-amber-200 bg-amber-50 p-4"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <span
                                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-amber-700 uppercase"
                                >
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
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    AeroPoints
                                </span>
                                <span
                                    class="text-xs font-semibold text-amber-600"
                                    >{{ availablePoints }} Available</span
                                >
                            </div>
                            <div class="flex items-center gap-3">
                                <input
                                    type="number"
                                    v-model="pointsToUse"
                                    min="0"
                                    :max="availablePoints"
                                    class="aero-input w-full flex-1 rounded border border-amber-300 bg-white px-3 py-1.5 text-sm"
                                    placeholder="0"
                                />
                                <span class="text-xs font-bold text-amber-700"
                                    >Pts</span
                                >
                            </div>
                            <p class="mt-1.5 text-[10px] text-amber-600/80">
                                1 Pts = $1.00 Discount
                            </p>
                        </div>

                        <div class="mt-2 border-t border-border pt-4">
                            <div class="mb-2 flex justify-between">
                                <span class="text-muted-foreground"
                                    >Base Ticket(s)</span
                                >
                                <span class="font-medium">{{
                                    formatCurrency(totalPriceObj.base)
                                }}</span>
                            </div>
                            <div
                                v-if="totalPriceObj.seats > 0"
                                class="mb-2 flex justify-between"
                            >
                                <span class="text-muted-foreground"
                                    >Seat Upgrades</span
                                >
                                <span class="font-medium"
                                    >+
                                    {{
                                        formatCurrency(totalPriceObj.seats)
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="totalPriceObj.insurance > 0"
                                class="mb-2 flex justify-between"
                            >
                                <span class="text-muted-foreground"
                                    >Travel Protection</span
                                >
                                <span class="font-medium"
                                    >+
                                    {{
                                        formatCurrency(totalPriceObj.insurance)
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="totalPriceObj.discount > 0"
                                class="mb-2 flex justify-between text-emerald-600"
                            >
                                <span class="font-bold">Promo Discount</span>
                                <span class="font-bold"
                                    >-
                                    {{
                                        formatCurrency(totalPriceObj.discount)
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="pointsToUse > 0"
                                class="mb-2 flex justify-between text-amber-600"
                            >
                                <span class="font-bold">Points Applied</span>
                                <span class="font-bold"
                                    >- {{ formatCurrency(pointsToUse) }}</span
                                >
                            </div>

                            <div
                                class="mt-4 flex items-end justify-between border-t border-border pt-3"
                            >
                                <span class="font-bold text-muted-foreground"
                                    >Final Total</span
                                >
                                <span class="text-3xl font-black text-primary">
                                    {{ formatCurrency(totalPriceObj.finalPay) }}
                                </span>
                            </div>
                            <p
                                class="mt-1 text-right text-[10px] font-bold text-amber-600"
                            >
                                Earn {{ totalPriceObj.pointsEarned }} AeroPoints
                            </p>
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
