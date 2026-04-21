<!-- eslint-disable vue/block-lang -->
<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AeroLayout from '@/layouts/AeroLayout.vue';

// MATIKAN LAYOUT GLOBAL BAWAAN BREEZE
defineOptions({
    // @ts-expect-error - Inertia layout typing not recognized
    layout: null,
});

const props = defineProps({
    flights: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ origin: '', destination: '', date: '' }),
    },
});

// --- 1. STATE MANAGEMENT ---
const displayOrigin = ref(props.filters?.origin || '');
const displayDestination = ref(props.filters?.destination || '');
const searchForm = ref({ date: props.filters?.date || '' });

const originResults = ref([]);
const destinationResults = ref([]);
const allAirports = ref([]);
const airlinesMap = ref({}); // Kamus maskapai (GA -> Garuda Indonesia)
const isLoading = ref(true);

// --- 2. FETCH DATA MASTER DARI GITHUB SAAT WEB DIBUKA ---
onMounted(async () => {
    // A. FETCH DATA BANDARA (Untuk Autocomplete)
    try {
        isLoading.value = true;
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
        console.error('Gagal load data bandara', error);
    }

    // B. FETCH DATA MASKAPAI (Untuk Card Penerbangan)
    try {
        const resAirlines = await fetch(
            'https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat',
        );
        const textAirlines = await resAirlines.text();

        const map = {};
        textAirlines.split('\n').forEach((line) => {
            const cols = line.split(',');

            if (cols.length > 3) {
                const name = cols[1].replace(/"/g, '');
                const iata = cols[3].replace(/"/g, '');

                if (iata && iata !== '\\N' && iata !== '-' && iata !== '') {
                    map[iata] = name;
                }
            }
        });
        airlinesMap.value = map;
    } catch (error) {
        console.error('Gagal load data maskapai:', error);
    } finally {
        isLoading.value = false;
    }
});

// --- 3. LOGIC HELPER PENCARIAN BANDARA ---
const searchAirport = (type) => {
    if (isLoading.value) {
        return [];
    }

    const term =
        type === 'origin'
            ? displayOrigin.value.toLowerCase()
            : displayDestination.value.toLowerCase();

    if (term.length < 2) {
        if (type === 'origin') {
            originResults.value = [];
        }

        if (type === 'destination') {
            destinationResults.value = [];
        }

        return;
    }

    const results = allAirports.value
        .filter((a) => {
            const iataMatch = a.code && a.code.toLowerCase().includes(term);
            const cityMatch = a.city && a.city.toLowerCase().includes(term);
            const nameMatch = a.name && a.name.toLowerCase().includes(term);

            return iataMatch || cityMatch || nameMatch;
        })
        .slice(0, 10);

    if (type === 'origin') {
        originResults.value = results;
    }

    if (type === 'destination') {
        destinationResults.value = results;
    }
};

const formatTransits = (transitIatas) => {
    if (!transitIatas || transitIatas.length === 0) {
        return '';
    }

    return transitIatas
        .map((iata) => {
            const airport = allAirports.value.find((a) => a.code === iata);

            return airport ? airport.city : iata;
        })
        .join(', ');
};

const selectAirport = (type, airport) => {
    if (type === 'origin') {
        displayOrigin.value = `${airport.city} (${airport.code})`;
        originResults.value = [];
    } else {
        displayDestination.value = `${airport.city} (${airport.code})`;
        destinationResults.value = [];
    }
};

const hideDropdowns = () => {
    setTimeout(() => {
        originResults.value = [];
        destinationResults.value = [];
    }, 200);
};

// --- 4. SUBMIT FORM KE CONTROLLER ---
const submitSearch = () => {
    // Fungsi pembersih biar yg dikirim murni kode IATA (CGK)
    const extractCleanData = (text) => {
        if (text.includes('(') && text.includes(')')) {
            return text.split('(')[1].replace(')', '').trim();
        }

        return text.trim();
    };

    router.get(
        '/flights',
        {
            origin: extractCleanData(displayOrigin.value),
            destination: extractCleanData(displayDestination.value),
            date: searchForm.value.date,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

// --- 5. HELPER TAMPILAN WAKTU & DURASI ---
const formatTime = (dateString) => {
    return new Date(dateString).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const calculateDuration = (departure, arrival) => {
    const start = new Date(departure);
    const end = new Date(arrival);
    const diffMs = end - start;
    const diffHrs = Math.floor(diffMs / (1000 * 60 * 60));
    const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    if (diffHrs === 0) {
        return `${diffMins}m`;
    }

    return `${diffHrs}h ${diffMins}m`;
};

const handleSelectFlight = (flight) => {
    if (flight.provider === 'duffel') {
        router.post('/flights/select-external', {
            flight: flight,
            aircraft_id: flight.aircraft?.id,
        });
    } else {
        router.get(`/flights/${flight.id}/seats`);
    }
};

const displayFlightNumber = (airlineCode, flightNumber) => {
    const code = String(airlineCode || '').toUpperCase();
    const num = String(flightNumber || '').toUpperCase();

    // kalau sudah format lengkap (misal QG-880), pakai langsung
    if (num.includes('-')) {
        return num;
    }

    // kalau cuma angka (Duffel), tambahin dash
    return `${code}-${num}`;
};
</script>

<template>
    <Head title="Search Flights" />

    <AeroLayout>
        <main
            class="mx-auto min-h-[80vh] max-w-5xl px-4 pt-24 pb-12 sm:px-6 lg:px-8"
        >
            <div
                class="mb-10 rounded-2xl border border-border bg-card p-6 text-left shadow-sm sm:p-8"
            >
                <h2
                    class="mb-6 flex items-center gap-2 text-xl font-bold text-foreground"
                >
                    Find Your Next Flight
                    <span
                        v-if="isLoading"
                        class="animate-pulse text-xs font-normal text-muted-foreground"
                    >
                        (Syncing Live Data...)
                    </span>
                </h2>

                <form
                    @submit.prevent="submitSearch"
                    class="flex flex-col gap-4 md:flex-row"
                >
                    <div class="relative flex-1">
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >Origin</label
                        >
                        <input
                            type="text"
                            v-model="displayOrigin"
                            @input="searchAirport('origin')"
                            @focus="searchAirport('origin')"
                            @blur="hideDropdowns"
                            :disabled="isLoading"
                            placeholder="e.g., Jakarta or CGK"
                            autocomplete="off"
                            class="aero-input w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-semibold text-foreground uppercase placeholder:font-normal placeholder:text-muted-foreground disabled:opacity-50"
                        />
                        <ul
                            v-if="originResults.length > 0"
                            class="absolute left-0 z-[9999] mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-border bg-card shadow-2xl"
                        >
                            <li
                                v-for="airport in originResults"
                                :key="airport.code"
                                @mousedown.prevent="
                                    selectAirport('origin', airport)
                                "
                                class="flex cursor-pointer items-center justify-between border-b border-border/40 px-4 py-3 transition-colors last:border-0 hover:bg-muted/50"
                            >
                                <div class="flex flex-col">
                                    <span
                                        class="text-sm font-bold text-foreground"
                                        >{{ airport.city }},
                                        {{ airport.country }}</span
                                    >
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                        >{{ airport.name }}</span
                                    >
                                </div>
                                <span
                                    class="rounded bg-primary/10 px-2 py-1 text-xs font-bold text-primary"
                                    >{{ airport.code }}</span
                                >
                            </li>
                        </ul>
                    </div>

                    <div class="relative flex-1">
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >Destination</label
                        >
                        <input
                            type="text"
                            v-model="displayDestination"
                            @input="searchAirport('destination')"
                            @focus="searchAirport('destination')"
                            @blur="hideDropdowns"
                            :disabled="isLoading"
                            placeholder="e.g., SIN"
                            autocomplete="off"
                            class="aero-input w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-semibold text-foreground uppercase placeholder:font-normal placeholder:text-muted-foreground disabled:opacity-50"
                        />
                        <ul
                            v-if="destinationResults.length > 0"
                            class="absolute left-0 z-[9999] mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-border bg-card shadow-2xl"
                        >
                            <li
                                v-for="airport in destinationResults"
                                :key="airport.code"
                                @mousedown.prevent="
                                    selectAirport('destination', airport)
                                "
                                class="flex cursor-pointer items-center justify-between border-b border-border/40 px-4 py-3 transition-colors last:border-0 hover:bg-muted/50"
                            >
                                <div class="flex flex-col">
                                    <span
                                        class="text-sm font-bold text-foreground"
                                        >{{ airport.city }},
                                        {{ airport.country }}</span
                                    >
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                        >{{ airport.name }}</span
                                    >
                                </div>
                                <span
                                    class="rounded bg-primary/10 px-2 py-1 text-xs font-bold text-primary"
                                    >{{ airport.code }}</span
                                >
                            </li>
                        </ul>
                    </div>

                    <div class="flex-1">
                        <label
                            class="mb-1.5 block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >Departure Date</label
                        >
                        <input
                            type="date"
                            v-model="searchForm.date"
                            class="aero-input w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-semibold text-foreground"
                        />
                    </div>

                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="hover:bg-primary-hover flex h-[42px] w-full items-center justify-center gap-2 rounded-lg bg-primary px-8 py-2.5 text-sm font-bold text-primary-foreground shadow-sm transition md:w-auto"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                            Search
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <div
                    v-if="flights.length === 0"
                    class="rounded-2xl border border-border bg-card py-20 text-center shadow-sm"
                >
                    <div class="mb-4 text-5xl opacity-40">✈️</div>
                    <h3 class="mb-2 text-lg font-bold text-foreground">
                        No Flights Found
                    </h3>
                    <p class="font-medium text-muted-foreground">
                        We couldn't find any flights for this route or date. Try
                        adjusting your search.
                    </p>
                </div>

                <div
                    v-for="flight in flights"
                    :key="flight.id"
                    class="group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-all hover:border-primary/40 hover:shadow-md md:flex-row"
                >
                    <div
                        class="flex flex-col items-center justify-center border-b border-border bg-muted/10 p-6 md:w-56 md:border-r md:border-b-0"
                    >
                        <div class="mb-3 flex gap-2">
                            <span
                                v-if="flight.provider === 'duffel'"
                                class="inline-block rounded border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wide text-blue-500"
                                >⚡ DUFFEL API</span
                            >
                            <span
                                v-else
                                class="inline-block rounded border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wide text-emerald-500"
                                >🏠 INTERNAL DB</span
                            >
                        </div>

                        <span
                            class="inline-block w-full max-w-[140px] truncate rounded-md border border-primary/20 bg-primary/10 px-3 py-1.5 text-center text-xs leading-tight font-bold tracking-wide text-primary shadow-sm"
                            :title="
                                airlinesMap[flight.airline_code] ||
                                flight.airline_code
                            "
                        >
                            {{
                                airlinesMap[flight.airline_code] ||
                                flight.airline_code
                            }}
                        </span>

                        <p
                            class="mt-3 text-sm font-black tracking-widest text-foreground uppercase"
                        >
                            {{
                                displayFlightNumber(
                                    flight.airline_code,
                                    flight.flight_number,
                                )
                            }}
                        </p>
                        <p
                            class="mt-1 flex items-center justify-center gap-1.5 text-xs font-medium text-muted-foreground"
                        >
                            <svg
                                class="h-3 w-3 opacity-70"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"
                                />
                            </svg>
                            {{
                                flight.aircraft
                                    ? flight.aircraft.model_name
                                    : 'Aircraft TBA'
                            }}
                        </p>
                    </div>

                    <div class="flex flex-1 flex-col justify-center p-6">
                        <div class="flex items-center justify-between gap-4">
                            <div class="text-center sm:text-left">
                                <p class="text-2xl font-black text-foreground">
                                    {{ flight.origin_airport }}
                                </p>
                                <p
                                    class="mt-1 text-sm font-semibold text-muted-foreground"
                                >
                                    {{ formatTime(flight.departure_at) }}
                                </p>
                            </div>

                            <div
                                class="flex flex-1 flex-col items-center justify-center px-2 sm:px-4"
                            >
                                <!-- Label stop / direct -->
                                <div
                                    v-if="
                                        !flight.transits ||
                                        flight.transits.length === 0
                                    "
                                    class="flex flex-col items-center"
                                >
                                    <span
                                        class="text-[10px] font-bold tracking-widest text-emerald-500 uppercase"
                                        >Direct Flight</span
                                    >
                                </div>
                                <div v-else class="flex flex-col items-center">
                                    <span
                                        class="text-[10px] font-bold tracking-widest text-amber-500 uppercase"
                                        >{{
                                            flight.transits.length
                                        }}
                                        Stop(s)</span
                                    >
                                    <span
                                        class="mt-0.5 max-w-[150px] truncate text-center text-[10px] font-medium text-muted-foreground"
                                    >
                                        via
                                        {{ formatTransits(flight.transits) }}
                                    </span>
                                </div>

                                <!-- Duration badge — sekarang IN FLOW, bukan absolute -->
                                <span
                                    class="my-1.5 rounded-full border border-border bg-background px-2 py-0.5 text-[11px] font-bold text-foreground shadow-sm"
                                >
                                    {{
                                        calculateDuration(
                                            flight.departure_at,
                                            flight.arrival_at,
                                        )
                                    }}
                                </span>

                                <!-- Garis + arrow -->
                                <div
                                    class="relative flex w-full items-center justify-center"
                                >
                                    <div
                                        class="h-[2px] w-full bg-border transition-colors group-hover:bg-primary/30"
                                    ></div>
                                    <svg
                                        class="absolute h-5 w-5 bg-card px-0.5 text-muted-foreground transition-colors group-hover:text-primary"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <div class="text-center sm:text-right">
                                <p class="text-2xl font-black text-foreground">
                                    {{ flight.destination_airport }}
                                </p>
                                <p
                                    class="mt-1 text-sm font-semibold text-muted-foreground"
                                >
                                    {{ formatTime(flight.arrival_at) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="relative hidden w-0 flex-col items-center justify-center border-l-2 border-dashed border-border md:flex"
                    >
                        <div
                            class="absolute -top-3 h-6 w-6 rounded-full border-b border-border bg-background"
                        ></div>
                        <div
                            class="absolute -bottom-3 h-6 w-6 rounded-full border-t border-border bg-background"
                        ></div>
                    </div>
                    <div
                        class="relative flex h-0 w-full items-center justify-center border-t-2 border-dashed border-border md:hidden"
                    >
                        <div
                            class="absolute -left-3 h-6 w-6 rounded-full border-r border-border bg-background"
                        ></div>
                        <div
                            class="absolute -right-3 h-6 w-6 rounded-full border-l border-border bg-background"
                        ></div>
                    </div>

                    <div
                        class="flex flex-col items-center justify-center gap-3 bg-muted/10 p-6 md:w-56"
                    >
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Start from
                        </p>
                        <p class="mb-1 text-3xl font-black text-primary">
                            {{
                                new Intl.NumberFormat('en-US', {
                                    style: 'currency',
                                    currency: 'USD',
                                }).format(flight.base_price_usd)
                            }}
                        </p>
                        <button
                            @click="handleSelectFlight(flight)"
                            class="hover:bg-primary-hover w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-primary-foreground shadow-sm transition"
                        >
                            Select Flight
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </AeroLayout>
</template>
