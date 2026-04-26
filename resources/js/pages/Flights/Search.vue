<!-- eslint-disable vue/block-lang -->
<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, reactive, onMounted, watch } from 'vue';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: null });

const props = defineProps({
    flights: { type: Array, default: () => [] },
    returnFlights: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({
            origin: '',
            destination: '',
            date: '',
            trip_type: 'one_way',
            return_date: '',
        }),
    },
});

// --- 1. STATE MANAGEMENT DASAR ---
const tripType = ref(props.filters?.trip_type || 'one_way');
const displayOrigin = ref(props.filters?.origin || '');
const displayDestination = ref(props.filters?.destination || '');
const searchForm = ref({
    date: props.filters?.date || '',
    returnDate: props.filters?.return_date || '',
});

const recentSearches = ref([]);
const originResults = ref([]);
const destinationResults = ref([]);
const allAirports = ref([]);
const airlinesMap = ref({});
const isLoading = ref(true);

// --- 2. STATE UNTUK TIKET, FILTER & STEP ---
const selectedOutbound = ref(null);
const selectedReturn = ref(null);

// State untuk Step Selection (Round Trip)
const selectionStep = ref('outbound'); // 'outbound' atau 'return'

const activeFilters = reactive({
    sortBy: 'recommendation',
    airlines: [],
    departureTime: [],
    arrivalTime: [],
    transits: [],
    maxTransitDuration: 24,
});

// --- 3. PAGINATION STATE ---
const itemsPerPage = 5;
const currentPageOutbound = ref(1);
const currentPageReturn = ref(1);

// Reset pagination kalau filter berubah
watch(
    [activeFilters],
    () => {
        currentPageOutbound.value = 1;
        currentPageReturn.value = 1;
    },
    { deep: true },
);

const availableAirlines = computed(() => {
    const all = [...props.flights, ...props.returnFlights];
    const uniqueCodes = [...new Set(all.map((f) => f.airline_code))];

    return uniqueCodes
        .map((code) => ({
            code: code,
            name: airlinesMap.value[code] || code,
        }))
        .sort((a, b) => a.name.localeCompare(b.name));
});

onMounted(async () => {
    const savedSearches = localStorage.getItem('aero_recent_searches');

    if (savedSearches) {
        recentSearches.value = JSON.parse(savedSearches);
    }

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
        console.error(error);
    }

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
        console.error(error);
    } finally {
        isLoading.value = false;
    }
});

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
        } else {
            destinationResults.value = [];
        }

        {
            return [];
        }
    }

    const results = allAirports.value
        .filter((a) => {
            return (
                (a.code && a.code.toLowerCase().includes(term)) ||
                (a.city && a.city.toLowerCase().includes(term)) ||
                (a.name && a.name.toLowerCase().includes(term))
            );
        })
        .slice(0, 10);

    if (type === 'origin') {
        originResults.value = results;
    } else {
        destinationResults.value = results;
    }
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

const extractCleanData = (text) =>
    text.includes('(') && text.includes(')')
        ? text.split('(')[1].replace(')', '').trim()
        : text.trim();

const saveToRecent = (searchParams) => {
    let history = [...recentSearches.value];
    history = history.filter(
        (h) =>
            !(
                h.origin === searchParams.origin &&
                h.destination === searchParams.destination &&
                h.trip_type === searchParams.trip_type
            ),
    );
    history.unshift(searchParams);

    if (history.length > 5) {
        history.pop();
    }

    recentSearches.value = history;
    localStorage.setItem('aero_recent_searches', JSON.stringify(history));
};

const applyRecentSearch = (item) => {
    displayOrigin.value = item.origin;
    displayDestination.value = item.destination;
    tripType.value = item.trip_type;
    searchForm.value.date = item.date;
    searchForm.value.returnDate = item.return_date || '';
};

const clearRecentSearches = () => {
    recentSearches.value = []; // Kosongkan state Vue
    localStorage.removeItem('aero_recent_searches'); // Hapus dari browser storage
};

const formattedRecentSearches = computed(() => {
    return recentSearches.value.map((item) => ({
        ...item,

        formattedDate: item.date
            ? new Date(item.date).toLocaleDateString('id-ID', {
                  day: '2-digit',
                  month: 'short',
                  year: 'numeric',
              })
            : '',

        formattedReturnDate: item.return_date
            ? new Date(item.return_date).toLocaleDateString('id-ID', {
                  day: '2-digit',
                  month: 'short',
                  year: 'numeric',
              })
            : '',

        formattedTripType:
            item.trip_type === 'round_trip' ? 'Round Trip' : 'One Way',

        // key unik biar Vue optimal diffing
        uniqueKey: `${item.origin}-${item.destination}-${item.date}-${item.trip_type}`,
    }));
});

const submitSearch = () => {
    const searchParams = {
        origin: extractCleanData(displayOrigin.value),
        destination: extractCleanData(displayDestination.value),
        date: searchForm.value.date,
        trip_type: tripType.value,
        return_date:
            tripType.value === 'round_trip' ? searchForm.value.returnDate : '',
    };

    if (searchParams.origin && searchParams.destination) {
        saveToRecent(searchParams);
    }

    selectedOutbound.value = null;
    selectedReturn.value = null;
    selectionStep.value = 'outbound'; // Reset ke step awal

    router.get('/flights', searchParams, {
        preserveState: true,
        replace: true,
    });
};

// --- CORE LOGIC: FILTER ---
const checkTimeRange = (dateString, ranges) => {
    if (ranges.length === 0) {
        return true;
    }

    const hour = new Date(dateString).getHours();

    return ranges.some((range) => {
        if (range === '00-06') {
            return hour >= 0 && hour < 6;
        }

        if (range === '06-12') {
            return hour >= 6 && hour < 12;
        }

        if (range === '12-18') {
            return hour >= 12 && hour < 18;
        }

        if (range === '18-24') {
            return hour >= 18 && hour <= 23;
        }

        return false;
    });
};

const getTotalTransitDuration = (segments) => {
    if (!segments || segments.length <= 1) {
        return 0;
    }

    let totalMinutes = 0;

    for (let i = 0; i < segments.length - 1; i++) {
        const arrivalCurrent = new Date(segments[i].arrival_at);
        const departNext = new Date(segments[i + 1].departure_at);
        const diffMins = Math.floor(
            (departNext - arrivalCurrent) / (1000 * 60),
        );
        totalMinutes += diffMins;
    }

    return totalMinutes / 60; // Kembalikan dalam format Jam
};

const processFlights = (flightsArray) => {
    let result = flightsArray.filter((flight) => {
        if (!flight.starting_price || flight.starting_price <= 0) {
            return false;
        }

        // Ambil airline dari segmen pertama
        const mainAirline =
            flight.segments && flight.segments[0]
                ? flight.segments[0].airline_code
                : '';

        if (
            activeFilters.airlines.length > 0 &&
            !activeFilters.airlines.includes(mainAirline)
        ) {
            return false;
        }

        if (!checkTimeRange(flight.departure_at, activeFilters.departureTime)) {
            return false;
        }

        if (!checkTimeRange(flight.arrival_at, activeFilters.arrivalTime)) {
            return false;
        }

        const transitCount = flight.stop_count || 0;

        if (activeFilters.transits.length > 0) {
            const isDirect =
                transitCount === 0 && activeFilters.transits.includes('direct');
            const isOneTransit =
                transitCount === 1 &&
                activeFilters.transits.includes('1_transit');
            const isTwoPlus =
                transitCount >= 2 &&
                activeFilters.transits.includes('2_plus_transit');

            if (!isDirect && !isOneTransit && !isTwoPlus) {
                return false;
            }
        }

        if (
            transitCount > 0 &&
            getTotalTransitDuration(flight.segments) >
                activeFilters.maxTransitDuration
        ) {
            return false;
        }

        return true;
    });

    result = result.sort((a, b) => {
        const priceA = parseFloat(a.starting_price || 0);
        const priceB = parseFloat(b.starting_price || 0);
        // ... (Logic sorting lainnya tetap sama) ...

        switch (activeFilters.sortBy) {
            case 'lowest_price':
                return priceA - priceB;
            // ...
        }
    });

    return result;
};

// FULL LIST
const filteredOutbound = computed(() => processFlights(props.flights));
const filteredReturn = computed(() => processFlights(props.returnFlights));

// PAGINATED LIST
const paginatedOutbound = computed(() => {
    const start = (currentPageOutbound.value - 1) * itemsPerPage;

    return filteredOutbound.value.slice(start, start + itemsPerPage);
});
const totalPagesOutbound = computed(() =>
    Math.ceil(filteredOutbound.value.length / itemsPerPage),
);

const paginatedReturn = computed(() => {
    const start = (currentPageReturn.value - 1) * itemsPerPage;

    return filteredReturn.value.slice(start, start + itemsPerPage);
});
const totalPagesReturn = computed(() =>
    Math.ceil(filteredReturn.value.length / itemsPerPage),
);

// --- LOGIC PEMILIHAN TIKET (AUTO-ADVANCE) ---
const toggleSelectFlight = (flight, type) => {
    if (type === 'outbound') {
        selectedOutbound.value =
            selectedOutbound.value?.id === flight.id ? null : flight;
        // Auto pindah tab kepulangan kalau buletin round-trip dan milih outbound

        if (selectedOutbound.value && tripType.value === 'round_trip') {
            setTimeout(() => {
                selectionStep.value = 'return';
            }, 300);
        }
    } else {
        selectedReturn.value =
            selectedReturn.value?.id === flight.id ? null : flight;
    }
};

const proceedToBooking = () => {
    if (tripType.value === 'one_way' && !selectedOutbound.value) {
        return alert('Please select a departure flight!');
    }

    if (
        tripType.value === 'round_trip' &&
        (!selectedOutbound.value || !selectedReturn.value)
    ) {
        return alert('Please select both departure and return flights!');
    }

    // GANTI ROUTER POST INI:
    router.post('/bookings/init', {
        trip_type: tripType.value,
        outbound_flight_id: selectedOutbound.value.id,
        return_flight_id: selectedReturn.value?.id || null,

        // PENTING: Kirim full data kalau dari Duffel, biar Controller bisa nyimpen transitnya
        outbound_flight_data:
            selectedOutbound.value.provider === 'duffel'
                ? selectedOutbound.value
                : null,
        return_flight_data:
            selectedReturn.value?.provider === 'duffel'
                ? selectedReturn.value
                : null,
    });
};

const resetFilters = () => {
    activeFilters.sortBy = 'recommendation';
    activeFilters.airlines = [];
    activeFilters.departureTime = [];
    activeFilters.arrivalTime = [];
    activeFilters.transits = [];
    activeFilters.maxTransitDuration = 24;
};

// --- HELPER UI ---
const formatTransits = (segments) => {
    if (!segments || segments.length <= 1) {
        return '';
    }

    // Ambil bandara tujuan dari semua segmen KECUALI segmen terakhir
    return segments
        .slice(0, -1)
        .map((seg) => {
            const airport = allAirports.value.find(
                (a) => a.code === seg.destination_airport,
            );

            return airport ? airport.city : seg.destination_airport;
        })
        .join(', ');
};
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
    const num = String(flightNumber || '').toUpperCase();

    return num.includes('-')
        ? num
        : `${String(airlineCode || '').toUpperCase()}-${num}`;
};

// --- HELPER NAMA KOTA BANDARA ---
const getCityName = (code) => {
    if (!allAirports.value || allAirports.value.length === 0) {
        return '';
    }

    const airport = allAirports.value.find((a) => a.code === code);

    return airport ? airport.name : ''; // Bisa diganti airport.name kalau mau nama bandara lengkap
};
</script>

<template>
    <Head title="Search Flights" />

    <AeroLayout>
        <main
            class="mx-auto min-h-[80vh] max-w-7xl px-4 pt-24 pb-32 sm:px-6 lg:px-8"
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
                        >(Syncing Live Data...)</span
                    >
                </h2>

                <form
                    @submit.prevent="submitSearch"
                    class="flex flex-col gap-5"
                >
                    <div class="flex gap-4">
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm font-semibold"
                        >
                            <input
                                type="radio"
                                v-model="tripType"
                                value="one_way"
                                class="h-4 w-4 text-primary focus:ring-primary"
                            />
                            One-Way
                        </label>
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm font-semibold"
                        >
                            <input
                                type="radio"
                                v-model="tripType"
                                value="round_trip"
                                class="h-4 w-4 text-primary focus:ring-primary"
                            />
                            Round-Trip
                        </label>
                    </div>

                    <div class="flex flex-col gap-4 md:flex-row">
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
                                class="aero-input w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-semibold uppercase placeholder:font-normal placeholder:text-muted-foreground disabled:opacity-50"
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
                                    class="flex cursor-pointer items-center justify-between border-b border-border/40 px-4 py-3 transition-colors hover:bg-muted/50"
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
                                class="aero-input w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-semibold uppercase placeholder:font-normal placeholder:text-muted-foreground disabled:opacity-50"
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
                                    class="flex cursor-pointer items-center justify-between border-b border-border/40 px-4 py-3 transition-colors hover:bg-muted/50"
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
                                >Depart</label
                            >
                            <input
                                type="date"
                                v-model="searchForm.date"
                                class="aero-input w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-semibold"
                            />
                        </div>
                        <div v-if="tripType === 'round_trip'" class="flex-1">
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >Return</label
                            >
                            <input
                                type="date"
                                v-model="searchForm.returnDate"
                                :min="searchForm.date"
                                class="aero-input w-full rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-semibold"
                            />
                        </div>
                        <div class="flex items-end">
                            <button
                                type="submit"
                                class="hover:bg-primary-hover flex h-[42px] w-full items-center justify-center gap-2 rounded-lg bg-primary px-8 py-2.5 text-sm font-bold text-primary-foreground shadow-sm transition md:w-auto"
                            >
                                Search
                            </button>
                        </div>
                    </div>
                </form>

                <div
                    v-if="recentSearches.length > 0"
                    class="mt-6 border-t border-border/50 pt-4"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold text-muted-foreground">
                            Recent Searches:
                        </p>
                        <button
                            type="button"
                            @click="clearRecentSearches"
                            class="text-xs font-bold text-destructive transition-colors hover:text-red-700 hover:underline"
                        >
                            Clear All
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="item in formattedRecentSearches"
                            :key="item.uniqueKey"
                            @click="applyRecentSearch(item)"
                            class="flex flex-col items-start gap-1 rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-foreground transition-colors hover:bg-muted/80"
                        >
                            <!-- Route -->
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold">{{ item.origin }}</span>
                                <span class="text-[10px]">⇄</span>
                                <span class="font-bold">{{
                                    item.destination
                                }}</span>
                            </div>

                            <!-- Meta Info -->
                            <div
                                class="flex flex-wrap gap-2 text-[10px] text-muted-foreground"
                            >
                                <span>{{ item.formattedDate }}</span>

                                <span>{{ item.formattedTripType }}</span>

                                <span v-if="item.formattedReturnDate">
                                    • Return: {{ item.formattedReturnDate }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="flights.length > 0"
                class="flex flex-col gap-8 md:flex-row"
            >
                <aside class="w-full shrink-0 space-y-6 md:w-64">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold">Filters</h3>
                        <button
                            @click="resetFilters"
                            class="text-xs font-semibold text-primary hover:underline"
                        >
                            Reset
                        </button>
                    </div>

                    <div class="border-b border-border pb-4">
                        <p class="mb-3 text-sm font-bold">Sort by</p>
                        <div class="space-y-2">
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="radio"
                                    v-model="activeFilters.sortBy"
                                    value="recommendation"
                                    class="h-4 w-4 text-primary"
                                />
                                Recommendation</label
                            >
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="radio"
                                    v-model="activeFilters.sortBy"
                                    value="lowest_price"
                                    class="h-4 w-4 text-primary"
                                />
                                Lowest price</label
                            >
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="radio"
                                    v-model="activeFilters.sortBy"
                                    value="earliest_departure"
                                    class="h-4 w-4 text-primary"
                                />
                                Earliest departure</label
                            >
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="radio"
                                    v-model="activeFilters.sortBy"
                                    value="shortest_duration"
                                    class="h-4 w-4 text-primary"
                                />
                                Shortest duration</label
                            >
                        </div>
                    </div>

                    <div class="border-b border-border pb-4">
                        <p class="mb-3 text-sm font-bold">Stops</p>
                        <div class="space-y-2">
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="checkbox"
                                    v-model="activeFilters.transits"
                                    value="direct"
                                    class="h-4 w-4 rounded border-border text-primary"
                                />
                                Direct</label
                            >
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="checkbox"
                                    v-model="activeFilters.transits"
                                    value="1_transit"
                                    class="h-4 w-4 rounded border-border text-primary"
                                />
                                1 Stop</label
                            >
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="checkbox"
                                    v-model="activeFilters.transits"
                                    value="2_plus_transit"
                                    class="h-4 w-4 rounded border-border text-primary"
                                />
                                2+ Stops</label
                            >
                        </div>
                        <div
                            class="mt-4"
                            v-if="
                                activeFilters.transits.some(
                                    (t) => t !== 'direct',
                                )
                            "
                        >
                            <p
                                class="mb-1 text-xs font-semibold text-muted-foreground"
                            >
                                Max Layover Duration:
                                {{ activeFilters.maxTransitDuration }} hrs
                            </p>
                            <input
                                type="range"
                                v-model="activeFilters.maxTransitDuration"
                                min="1"
                                max="24"
                                class="w-full accent-primary"
                            />
                        </div>
                    </div>

                    <div class="border-b border-border pb-4">
                        <p class="mb-3 text-sm font-bold">Departure Time</p>
                        <div class="space-y-2">
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="checkbox"
                                    v-model="activeFilters.departureTime"
                                    value="00-06"
                                    class="h-4 w-4 rounded border-border text-primary"
                                />
                                00:00 - 06:00</label
                            >
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="checkbox"
                                    v-model="activeFilters.departureTime"
                                    value="06-12"
                                    class="h-4 w-4 rounded border-border text-primary"
                                />
                                06:00 - 12:00</label
                            >
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="checkbox"
                                    v-model="activeFilters.departureTime"
                                    value="12-18"
                                    class="h-4 w-4 rounded border-border text-primary"
                                />
                                12:00 - 18:00</label
                            >
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                ><input
                                    type="checkbox"
                                    v-model="activeFilters.departureTime"
                                    value="18-24"
                                    class="h-4 w-4 rounded border-border text-primary"
                                />
                                18:00 - 24:00</label
                            >
                        </div>
                    </div>

                    <div>
                        <p class="mb-3 text-sm font-bold">Airlines</p>
                        <div class="max-h-48 space-y-2 overflow-y-auto pr-2">
                            <label
                                v-for="airline in availableAirlines"
                                :key="airline.code"
                                class="flex cursor-pointer items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    v-model="activeFilters.airlines"
                                    :value="airline.code"
                                    class="h-4 w-4 rounded border-border text-primary"
                                />
                                <span class="truncate">{{ airline.name }}</span>
                            </label>
                        </div>
                    </div>
                </aside>

                <div class="flex-1">
                    <div
                        v-if="tripType === 'round_trip'"
                        class="mb-8 flex border-b-2 border-border/50"
                    >
                        <button
                            @click="selectionStep = 'outbound'"
                            class="relative flex-1 pb-4 text-center text-sm font-bold transition-colors sm:text-base"
                            :class="
                                selectionStep === 'outbound'
                                    ? 'text-primary'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            1. Outbound
                            <div
                                v-if="selectionStep === 'outbound'"
                                class="absolute -bottom-[2px] left-0 h-[2px] w-full bg-primary"
                            ></div>

                            <div
                                v-if="selectedOutbound"
                                class="absolute top-1/2 right-4 hidden -translate-y-1/2 items-center gap-2 rounded-full bg-emerald-100/80 px-3 py-1 text-[10px] tracking-wider text-emerald-700 uppercase sm:flex"
                            >
                                <span>✓</span>
                                {{ selectedOutbound.airline_code }}
                            </div>
                        </button>

                        <button
                            @click="selectionStep = 'return'"
                            class="relative flex-1 pb-4 text-center text-sm font-bold transition-colors sm:text-base"
                            :class="
                                selectionStep === 'return'
                                    ? 'text-blue-600'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            2. Return
                            <div
                                v-if="selectionStep === 'return'"
                                class="absolute -bottom-[2px] left-0 h-[2px] w-full bg-blue-600"
                            ></div>

                            <div
                                v-if="selectedReturn"
                                class="absolute top-1/2 right-4 hidden -translate-y-1/2 items-center gap-2 rounded-full bg-blue-100/80 px-3 py-1 text-[10px] tracking-wider text-blue-700 uppercase sm:flex"
                            >
                                <span>✓</span> {{ selectedReturn.airline_code }}
                            </div>
                        </button>
                    </div>

                    <div
                        v-if="selectionStep === 'outbound'"
                        class="animate-in space-y-4 duration-300 fade-in slide-in-from-right-4"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-bold">
                                🛫 Outbound Flights
                                <span
                                    class="ml-2 text-sm font-normal text-muted-foreground"
                                    >({{ filteredOutbound.length }} found)</span
                                >
                            </h3>
                        </div>

                        <div
                            v-if="paginatedOutbound.length === 0"
                            class="rounded-2xl border border-border bg-card py-20 text-center shadow-sm"
                        >
                            <div class="mb-4 text-5xl opacity-40">✈️</div>
                            <h3 class="mb-2 text-lg font-bold">
                                No Flights Found
                            </h3>
                        </div>

                        <div
                            v-for="flight in paginatedOutbound"
                            :key="flight.id"
                            :class="[
                                'group relative flex flex-col overflow-hidden rounded-2xl border bg-card shadow-sm transition-all md:flex-row',
                                selectedOutbound?.id === flight.id
                                    ? 'border-primary ring-1 ring-primary'
                                    : 'border-border hover:border-primary/40',
                            ]"
                        >
                            <div
                                class="flex flex-col items-center justify-center border-b border-border bg-muted/10 p-6 md:w-56 md:border-r md:border-b-0"
                            >
                                <span
                                    class="inline-block w-full max-w-[140px] truncate rounded-md border border-primary/20 bg-primary/10 px-3 py-1.5 text-center text-xs font-bold text-primary"
                                >
                                    {{
                                        airlinesMap[
                                            flight.segments?.[0]?.airline_code
                                        ] || flight.segments?.[0]?.airline_code
                                    }}
                                </span>
                                <p
                                    class="mt-3 text-sm font-black tracking-widest uppercase"
                                >
                                    {{
                                        displayFlightNumber(
                                            flight.segments?.[0]?.airline_code,
                                            flight.segments?.[0]?.flight_number,
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                class="flex flex-1 flex-col justify-center p-6"
                            >
                                <div
                                    class="flex items-center justify-between gap-4"
                                >
                                    <div class="text-center sm:text-left">
                                        <p
                                            class="text-2xl font-black text-foreground"
                                        >
                                            {{ flight.origin_airport }}
                                        </p>
                                        <p
                                            class="mb-1 max-w-[100px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{
                                                getCityName(
                                                    flight.origin_airport,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            {{
                                                formatTime(flight.departure_at)
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        class="flex flex-1 flex-col items-center justify-center px-2"
                                    >
                                        <span
                                            v-if="
                                                !flight.stop_count ||
                                                flight.stop_count === 0
                                            "
                                            class="text-[10px] font-bold text-emerald-500 uppercase"
                                            >Direct</span
                                        >
                                        <div
                                            v-else
                                            class="flex flex-col items-center"
                                        >
                                            <span
                                                class="text-[10px] font-bold text-amber-500 uppercase"
                                                >{{
                                                    flight.stop_count
                                                }}
                                                Stop(s)</span
                                            >
                                            <span
                                                class="mt-0.5 max-w-[150px] truncate text-center text-[10px] text-muted-foreground"
                                                >via
                                                {{
                                                    formatTransits(
                                                        flight.segments,
                                                    )
                                                }}</span
                                            >
                                        </div>

                                        <span
                                            class="my-1.5 rounded-full border border-border bg-background px-2 py-0.5 text-[11px] font-bold shadow-sm"
                                        >
                                            {{
                                                calculateDuration(
                                                    flight.departure_at,
                                                    flight.arrival_at,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <div class="text-center sm:text-right">
                                        <p
                                            class="text-2xl font-black text-foreground"
                                        >
                                            {{ flight.destination_airport }}
                                        </p>
                                        <p
                                            class="mb-1 ml-auto max-w-[100px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{
                                                getCityName(
                                                    flight.destination_airport,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            {{ formatTime(flight.arrival_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-col items-center justify-center gap-3 bg-muted/10 p-6 md:w-56"
                            >
                                <p
                                    class="mb-1 text-2xl font-black text-primary"
                                >
                                    ${{ flight.starting_price }}
                                </p>
                                <button
                                    @click="
                                        toggleSelectFlight(flight, 'outbound')
                                    "
                                    :class="[
                                        'w-full rounded-lg px-4 py-2 text-sm font-bold shadow-sm transition-colors',
                                        selectedOutbound?.id === flight.id
                                            ? 'bg-primary text-primary-foreground'
                                            : 'border border-border bg-background hover:bg-muted',
                                    ]"
                                >
                                    {{
                                        selectedOutbound?.id === flight.id
                                            ? '✓ Selected'
                                            : 'Select Outbound'
                                    }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="totalPagesOutbound > 1"
                            class="mt-6 flex justify-center gap-2"
                        >
                            <button
                                @click="currentPageOutbound--"
                                :disabled="currentPageOutbound === 1"
                                class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold disabled:opacity-50"
                            >
                                Prev
                            </button>
                            <span
                                class="px-4 py-2 text-sm font-bold text-muted-foreground"
                                >Page {{ currentPageOutbound }} of
                                {{ totalPagesOutbound }}</span
                            >
                            <button
                                @click="currentPageOutbound++"
                                :disabled="
                                    currentPageOutbound === totalPagesOutbound
                                "
                                class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold disabled:opacity-50"
                            >
                                Next
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="
                            selectionStep === 'return' &&
                            tripType === 'round_trip'
                        "
                        class="animate-in space-y-4 duration-300 fade-in slide-in-from-left-4"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-bold">
                                🛬 Return Flights
                                <span
                                    class="ml-2 text-sm font-normal text-muted-foreground"
                                    >({{ filteredReturn.length }} found)</span
                                >
                            </h3>
                        </div>

                        <div
                            v-if="paginatedReturn.length === 0"
                            class="rounded-2xl border border-border bg-card py-20 text-center shadow-sm"
                        >
                            <div class="mb-4 text-5xl opacity-40">✈️</div>
                            <h3 class="mb-2 text-lg font-bold">
                                No Flights Found
                            </h3>
                        </div>

                        <div
                            v-for="flight in paginatedReturn"
                            :key="flight.id"
                            :class="[
                                'group relative flex flex-col overflow-hidden rounded-2xl border bg-card shadow-sm transition-all md:flex-row',
                                selectedReturn?.id === flight.id
                                    ? 'border-blue-600 ring-1 ring-blue-600'
                                    : 'border-border hover:border-blue-600/40',
                            ]"
                        >
                            <div
                                class="flex flex-col items-center justify-center border-b border-border bg-muted/10 p-6 md:w-56 md:border-r md:border-b-0"
                            >
                                <span
                                    class="inline-block w-full max-w-[140px] truncate rounded-md border border-blue-600/20 bg-blue-600/10 px-3 py-1.5 text-center text-xs font-bold text-blue-700"
                                >
                                    {{
                                        airlinesMap[
                                            flight.segments?.[0]?.airline_code
                                        ] || flight.segments?.[0]?.airline_code
                                    }}
                                </span>
                                <p
                                    class="mt-3 text-sm font-black tracking-widest uppercase"
                                >
                                    {{
                                        displayFlightNumber(
                                            flight.segments?.[0]?.airline_code,
                                            flight.segments?.[0]?.flight_number,
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                class="flex flex-1 flex-col justify-center p-6"
                            >
                                <div
                                    class="flex items-center justify-between gap-4"
                                >
                                    <div class="text-center sm:text-left">
                                        <p class="text-2xl font-black">
                                            {{ flight.origin_airport }}
                                        </p>
                                        <p
                                            class="mb-1 max-w-[100px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{
                                                getCityName(
                                                    flight.origin_airport,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm font-semibold text-muted-foreground"
                                        >
                                            {{
                                                formatTime(flight.departure_at)
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex flex-1 flex-col items-center justify-center px-2"
                                    >
                                        <span
                                            v-if="
                                                !flight.stop_count ||
                                                flight.stop_count === 0
                                            "
                                            class="text-[10px] font-bold text-emerald-500 uppercase"
                                            >Direct</span
                                        >
                                        <div
                                            v-else
                                            class="flex flex-col items-center"
                                        >
                                            <span
                                                class="text-[10px] font-bold text-amber-500 uppercase"
                                                >{{
                                                    flight.stop_count
                                                }}
                                                Stop(s)</span
                                            >
                                            <span
                                                class="mt-0.5 max-w-[150px] truncate text-center text-[10px] text-muted-foreground"
                                                >via
                                                {{
                                                    formatTransits(
                                                        flight.segments,
                                                    )
                                                }}</span
                                            >
                                        </div>

                                        <span
                                            class="my-1.5 rounded-full border border-border bg-background px-2 py-0.5 text-[11px] font-bold shadow-sm"
                                        >
                                            {{
                                                calculateDuration(
                                                    flight.departure_at,
                                                    flight.arrival_at,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <div class="text-center sm:text-right">
                                        <p class="text-2xl font-black">
                                            {{ flight.destination_airport }}
                                        </p>
                                        <p
                                            class="mb-1 ml-auto max-w-[100px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{
                                                getCityName(
                                                    flight.destination_airport,
                                                )
                                            }}
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
                                class="flex flex-col items-center justify-center gap-3 bg-muted/10 p-6 md:w-56"
                            >
                                <p
                                    class="mb-1 text-2xl font-black text-blue-600"
                                >
                                    ${{ flight.starting_price }}
                                </p>
                                <button
                                    @click="
                                        toggleSelectFlight(flight, 'return')
                                    "
                                    :class="[
                                        'w-full rounded-lg px-4 py-2 text-sm font-bold shadow-sm transition-colors',
                                        selectedReturn?.id === flight.id
                                            ? 'bg-blue-600 text-white'
                                            : 'border border-border bg-background hover:bg-muted',
                                    ]"
                                >
                                    {{
                                        selectedReturn?.id === flight.id
                                            ? '✓ Selected'
                                            : 'Select Return'
                                    }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="totalPagesReturn > 1"
                            class="mt-6 flex justify-center gap-2"
                        >
                            <button
                                @click="currentPageReturn--"
                                :disabled="currentPageReturn === 1"
                                class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold disabled:opacity-50"
                            >
                                Prev
                            </button>
                            <span
                                class="px-4 py-2 text-sm font-bold text-muted-foreground"
                                >Page {{ currentPageReturn }} of
                                {{ totalPagesReturn }}</span
                            >
                            <button
                                @click="currentPageReturn++"
                                :disabled="
                                    currentPageReturn === totalPagesReturn
                                "
                                class="rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold disabled:opacity-50"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <div
            v-if="selectedOutbound"
            class="fixed right-0 bottom-0 left-0 z-[999] border-t border-border bg-card p-4 shadow-[0_-10px_40px_rgba(0,0,0,0.1)]"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-6">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Total Price
                        </p>
                        <p class="text-3xl font-black text-primary">
                            ${{
                                (
                                    parseFloat(
                                        selectedOutbound.starting_price,
                                    ) +
                                    (selectedReturn
                                        ? parseFloat(
                                              selectedReturn.starting_price,
                                          )
                                        : 0)
                                ).toFixed(2)
                            }}
                        </p>
                    </div>
                    <div
                        class="hidden border-l border-border pl-6 text-sm font-medium sm:block"
                    >
                        <p>
                            <span class="inline-block w-4">🛫</span>
                            {{ selectedOutbound.origin_airport }} →
                            {{ selectedOutbound.destination_airport }}
                        </p>
                        <p v-if="tripType === 'round_trip' && selectedReturn">
                            <span class="inline-block w-4">🛬</span>
                            {{ selectedReturn.origin_airport }} →
                            {{ selectedReturn.destination_airport }}
                        </p>
                        <p
                            v-else-if="tripType === 'round_trip'"
                            class="mt-1 text-xs font-semibold text-amber-500"
                        >
                            Select your return ticket to continue
                        </p>
                    </div>
                </div>
                <button
                    @click="proceedToBooking"
                    :disabled="tripType === 'round_trip' && !selectedReturn"
                    class="hover:bg-primary-hover rounded-lg bg-primary px-8 py-3.5 text-sm font-bold text-primary-foreground shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Continue Entering Data
                </button>
            </div>
        </div>
    </AeroLayout>
</template>
