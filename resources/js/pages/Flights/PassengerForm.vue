<!-- eslint-disable vue/block-lang -->
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: null });

const props = defineProps({
    booking_session: [String, Number],
    outbound_flight: Object,
    return_flight: Object,
    trip_type: String,
    baggage_addons: { type: Array, default: () => [] }, // Prop baru dari database
});

const form = useForm({
    passengers: [
        {
            title: 'Mr',
            first_name: '',
            last_name: '',
            date_of_birth: '',
            nationality: 'ID',
            passport_number: '',
            extra_baggage_kg: 0,
            baggage_fee_usd: 0,
        },
    ],
});

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

// --- HELPER MANAJEMEN PENUMPANG ---
const addPassenger = () => {
    form.passengers.push({
        title: 'Mr',
        first_name: '',
        last_name: '',
        date_of_birth: '',
        nationality: 'ID',
        passport_number: '',
        extra_baggage_kg: 0,
        baggage_fee_usd: 0,
    });
};

const removePassenger = (index) => {
    if (form.passengers.length > 1) {
        form.passengers.splice(index, 1);
    }
};

// --- HELPER BAGGAGE DINAMIS ---
// Susun opsi bagasi gabungan (Default maskapai + DEFAULT general)
// --- HELPER BAGGAGE DINAMIS ---
const baggageOptions = computed(() => {
    const options = [
        {
            weight_kg: 0,
            price_usd: 0,
            label: 'No Extra Baggage (Included Free)',
        },
    ];

    // Gunakan airline_code dari segmen pertama
    const mainAirline =
        props.outbound_flight.segments?.[0]?.airline_code || 'DEFAULT';

    const validAddons = props.baggage_addons.filter(
        (b) => b.airline_code === mainAirline || b.airline_code === 'DEFAULT',
    );

    // Hilangkan duplikat weight jika maskapai punya addon khusus yang sama beratnya dengan DEFAULT
    const uniqueAddons = [];
    const weights = new Set();
    validAddons.forEach((b) => {
        if (!weights.has(b.weight_kg)) {
            weights.add(b.weight_kg);
            uniqueAddons.push(b);
        }
    });

    // Urutkan dari teringan ke terberat
    uniqueAddons
        .sort((a, b) => a.weight_kg - b.weight_kg)
        .forEach((b) => {
            options.push({
                weight_kg: b.weight_kg,
                price_usd: b.price_usd,
                label: `+ ${b.weight_kg} KG Extra (+$${b.price_usd})`,
            });
        });

    return options;
});

const updateBaggageFee = (passenger) => {
    const selectedOption = baggageOptions.value.find(
        (opt) => opt.weight_kg == passenger.extra_baggage_kg,
    );
    passenger.baggage_fee_usd = selectedOption ? selectedOption.price_usd : 0;
};

// --- HELPER PERHITUNGAN HARGA ---
const basePricePerPerson = computed(() => {
    let price = Number(props.outbound_flight.starting_price || 0);

    if (props.trip_type === 'round_trip' && props.return_flight) {
        price += Number(props.return_flight.starting_price || 0);
    }

    return price;
});

const totalBasePrice = computed(
    () => basePricePerPerson.value * form.passengers.length,
);
const totalBaggageFees = computed(() =>
    form.passengers.reduce(
        (sum, p) => sum + (Number(p.baggage_fee_usd) || 0),
        0,
    ),
);
const totalPrice = computed(
    () => totalBasePrice.value + totalBaggageFees.value,
);

// --- SUBMIT ACTION ---
const submitToSeatSelection = () => {
    form.post(`/bookings/${props.booking_session}/seats`);
};

// --- HELPER WAKTU, DURASI & UI ---
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

// --- VALIDASI FORM (CEK SEMUA PENUMPANG) ---
const isFormValid = computed(() => {
    // Kalau belum ada penumpang sama sekali, jangan dilanjutin
    if (!form.passengers || form.passengers.length === 0) {
        return false;
    }

    // Looping semua penumpang. Kalau ada 1 aja yang firstname/lastname/dob kosong, return false
    for (let i = 0; i < form.passengers.length; i++) {
        const p = form.passengers[i];

        if (!p.first_name || p.first_name.trim() === '') {
            return false;
        }

        if (!p.last_name || p.last_name.trim() === '') {
            return false;
        }

        if (!p.date_of_birth || p.date_of_birth === '') {
            return false;
        }
    }

    // Kalau semua lewat pengecekan di atas, berarti form valid
    return true;
});
</script>

<template>
    <Head title="Passenger Details" />

    <AeroLayout>
        <main
            class="mx-auto min-h-[80vh] max-w-7xl px-4 pt-24 pb-12 sm:px-6 md:flex-row lg:px-8"
        >
            <div class="flex flex-col gap-8 lg:flex-row">
                <div class="flex-1 space-y-6">
                    <div class="mb-6 flex items-end justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-foreground">
                                Passenger Details
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Please enter the details exactly as they appear
                                on your passport/ID.
                            </p>
                        </div>
                    </div>

                    <form
                        @submit.prevent="submitToSeatSelection"
                        class="space-y-6"
                    >
                        <div
                            v-for="(passenger, index) in form.passengers"
                            :key="index"
                            class="relative rounded-xl border border-border bg-card p-6 shadow-sm"
                        >
                            <button
                                v-if="form.passengers.length > 1"
                                @click.prevent="removePassenger(index)"
                                class="absolute top-6 right-6 text-xs font-bold text-destructive hover:text-red-700"
                            >
                                ✕ Remove
                            </button>

                            <div
                                class="mb-4 flex items-center justify-between border-b border-border pr-16 pb-4"
                            >
                                <h3 class="text-lg font-bold text-foreground">
                                    Passenger {{ index + 1 }}
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium text-muted-foreground"
                                        >Title
                                        <span class="text-destructive"
                                            >*</span
                                        ></label
                                    >
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
                                        >Date of Birth
                                        <span class="text-destructive"
                                            >*</span
                                        ></label
                                    >
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
                                        >First Name
                                        <span class="text-destructive"
                                            >*</span
                                        ></label
                                    >
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
                                        >Last Name
                                        <span class="text-destructive"
                                            >*</span
                                        ></label
                                    >
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
                                        >Passport / ID Number</label
                                    >
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
                                        class="mb-4 flex flex-col gap-2 border-b border-primary/10 pb-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <h4
                                                class="text-sm font-bold text-foreground"
                                            >
                                                Baggage Allowance
                                            </h4>
                                            <p
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                Your ticket includes free cabin
                                                & checked baggage.
                                            </p>
                                        </div>
                                        <div class="flex gap-2">
                                            <span
                                                class="rounded border border-border bg-background px-2 py-1 text-[10px] font-bold text-foreground shadow-sm"
                                            >
                                                🎒 Cabin:
                                                {{
                                                    outbound_flight
                                                        .segments?.[0]
                                                        ?.classes?.[0]
                                                        ?.cabin_baggage_kg || 7
                                                }}
                                                KG
                                            </span>
                                            <span
                                                class="rounded bg-primary px-2 py-1 text-[10px] font-bold text-primary-foreground shadow-sm"
                                            >
                                                🧳 Checked:
                                                {{
                                                    outbound_flight
                                                        .segments?.[0]
                                                        ?.classes?.[0]
                                                        ?.free_baggage_kg || 20
                                                }}
                                                KG
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Need more space? (Optional)</label
                                        >
                                        <select
                                            v-model="passenger.extra_baggage_kg"
                                            @change="
                                                updateBaggageFee(passenger)
                                            "
                                            class="aero-input w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:ring-primary"
                                        >
                                            <option
                                                v-for="option in baggageOptions"
                                                :key="option.weight_kg"
                                                :value="option.weight_kg"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button
                            @click.prevent="addPassenger"
                            type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-primary/40 bg-primary/5 py-4 text-sm font-bold text-primary transition-colors hover:bg-primary/10"
                        >
                            <span>+</span> Add Another Passenger
                        </button>
                    </form>
                </div>

                <div
                    class="sticky top-24 h-fit w-full shrink-0 rounded-2xl border border-border bg-card p-6 shadow-sm lg:w-[420px]"
                >
                    <h3 class="mb-5 text-lg font-bold text-foreground">
                        Flight Details
                    </h3>

                    <div
                        class="relative mb-4 overflow-hidden rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-emerald-500"
                        ></div>
                        <div class="mb-3 flex items-center justify-between">
                            <p
                                class="rounded bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wider text-emerald-600 uppercase"
                            >
                                🛫 Outbound Flight
                            </p>
                            <span class="font-mono text-[10px] font-bold">{{
                                displayFlightNumber(
                                    outbound_flight.segments?.[0]?.airline_code,
                                    outbound_flight.segments?.[0]
                                        ?.flight_number,
                                )
                            }}</span>
                        </div>

                        <div
                            class="mb-3 flex items-center justify-between border-b border-border/50 pb-3"
                        >
                            <div class="flex flex-col">
                                <span class="text-sm font-bold">{{
                                    outbound_flight.segments?.[0]?.airline_info
                                        ?.name ||
                                    outbound_flight.segments?.[0]?.airline_code
                                }}</span>
                                <span
                                    class="text-[10px] text-muted-foreground"
                                    >{{
                                        outbound_flight.segments?.[0]?.aircraft
                                            ?.model_name || 'Aircraft TBA'
                                    }}</span
                                >
                            </div>
                        </div>

                        <div class="mb-4 flex items-center justify-between">
                            <div class="text-left">
                                <span class="block text-2xl font-black">{{
                                    outbound_flight.origin_airport
                                }}</span>
                                <span
                                    class="mb-1 block max-w-[100px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        getCityName(
                                            outbound_flight.origin_airport,
                                        )
                                    }}
                                </span>
                                <span
                                    class="text-[11px] font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(outbound_flight.departure_at)
                                    }}</span
                                >
                            </div>
                            <div class="flex flex-col items-center px-2">
                                <span
                                    v-if="outbound_flight.stop_count === 0"
                                    class="mb-1 text-[10px] font-bold text-emerald-500 uppercase"
                                    >Direct</span
                                >
                                <span
                                    v-else
                                    class="mb-1 text-[10px] font-bold text-amber-500 uppercase"
                                    >{{
                                        outbound_flight.stop_count
                                    }}
                                    Stop(s)</span
                                >

                                <div
                                    class="relative flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-12 bg-border"></div>
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
                                    class="mt-1 text-[10px] font-medium text-muted-foreground"
                                    >{{
                                        calculateDuration(
                                            outbound_flight.departure_at,
                                            outbound_flight.arrival_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span class="block text-2xl font-black">{{
                                    outbound_flight.destination_airport
                                }}</span>
                                <span
                                    class="mb-1 ml-auto block max-w-[100px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        getCityName(
                                            outbound_flight.destination_airport,
                                        )
                                    }}
                                </span>
                                <span
                                    class="text-[11px] font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(outbound_flight.arrival_at)
                                    }}</span
                                >
                            </div>
                        </div>

                        <div
                            v-if="
                                outbound_flight.stop_count > 0 &&
                                outbound_flight.segments &&
                                outbound_flight.segments.length > 1
                            "
                            class="mb-3 rounded border border-border bg-background p-2 text-[10px]"
                        >
                            <p class="mb-1 font-bold text-muted-foreground">
                                Transit Details:
                            </p>
                            <div
                                v-for="(
                                    seg, idx
                                ) in outbound_flight.segments.slice(0, -1)"
                                :key="'out_transit_' + idx"
                                class="flex items-center justify-between text-foreground"
                            >
                                <span>
                                    Layover at
                                    <span class="font-bold">{{
                                        seg.destination_airport
                                    }}</span>
                                </span>
                                <span class="font-mono text-amber-600">
                                    {{
                                        calculateDuration(
                                            seg.arrival_at,
                                            outbound_flight.segments[idx + 1]
                                                .departure_at,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="mt-3 flex flex-wrap gap-1.5 border-t border-border/50 pt-3"
                        >
                            <span
                                v-if="outbound_flight.facilities?.meal"
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                title="Meal Included"
                                >🍱 Meal</span
                            >
                            <span
                                v-if="outbound_flight.facilities?.wifi"
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                title="WiFi Available"
                                >📶 WiFi</span
                            >
                            <span
                                v-if="outbound_flight.facilities?.entertainment"
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                title="In-Flight Entertainment"
                                >🎬 Screen</span
                            >
                            <span
                                v-if="outbound_flight.facilities?.power_usb"
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                title="Power/USB Port"
                                >🔌 USB</span
                            >

                            <span
                                v-if="outbound_flight.is_refundable"
                                class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700"
                                >✓ Refundable</span
                            >
                            <span
                                v-if="outbound_flight.is_reschedulable"
                                class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700"
                                >✓ Reschedule</span
                            >
                        </div>
                    </div>

                    <div
                        v-if="trip_type === 'round_trip' && return_flight"
                        class="relative mb-6 overflow-hidden rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-blue-500"
                        ></div>
                        <div class="mb-3 flex items-center justify-between">
                            <p
                                class="rounded bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold tracking-wider text-blue-600 uppercase"
                            >
                                🛬 Return Flight
                            </p>
                            <span class="font-mono text-[10px] font-bold">{{
                                displayFlightNumber(
                                    return_flight.segments?.[0]?.airline_code,
                                    return_flight.segments?.[0]?.flight_number,
                                )
                            }}</span>
                        </div>

                        <div
                            class="mb-3 flex items-center justify-between border-b border-border/50 pb-3"
                        >
                            <div class="flex flex-col">
                                <span class="text-sm font-bold">{{
                                    return_flight.segments?.[0]?.airlineData
                                        ?.name ||
                                    return_flight.segments?.[0]?.airline_code
                                }}</span>
                                <span
                                    class="text-[10px] text-muted-foreground"
                                    >{{
                                        return_flight.segments?.[0]?.aircraft
                                            ?.model_name || 'Aircraft TBA'
                                    }}</span
                                >
                            </div>
                        </div>

                        <div class="mb-4 flex items-center justify-between">
                            <div class="text-left">
                                <span class="block text-2xl font-black">{{
                                    return_flight.origin_airport
                                }}</span>
                                <span
                                    class="mb-1 block max-w-[100px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        getCityName(
                                            return_flight.origin_airport,
                                        )
                                    }}
                                </span>
                                <span
                                    class="text-[11px] font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(return_flight.departure_at)
                                    }}</span
                                >
                            </div>
                            <div class="flex flex-col items-center px-2">
                                <span
                                    v-if="return_flight.stop_count === 0"
                                    class="mb-1 text-[10px] font-bold text-emerald-500 uppercase"
                                    >Direct</span
                                >
                                <span
                                    v-else
                                    class="mb-1 text-[10px] font-bold text-amber-500 uppercase"
                                    >{{
                                        return_flight.stop_count
                                    }}
                                    Stop(s)</span
                                >

                                <div
                                    class="relative flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-12 bg-border"></div>
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
                                    class="mt-1 text-[10px] font-medium text-muted-foreground"
                                    >{{
                                        calculateDuration(
                                            return_flight.departure_at,
                                            return_flight.arrival_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="text-right">
                                <span class="block text-2xl font-black">{{
                                    return_flight.destination_airport
                                }}</span>
                                <span
                                    class="mb-1 ml-auto block max-w-[100px] truncate text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        getCityName(
                                            return_flight.destination_airport,
                                        )
                                    }}
                                </span>
                                <span
                                    class="text-[11px] font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(return_flight.arrival_at)
                                    }}</span
                                >
                            </div>
                        </div>

                        <div
                            v-if="
                                return_flight.stop_count > 0 &&
                                return_flight.segments &&
                                return_flight.segments.length > 1
                            "
                            class="mb-3 rounded border border-border bg-background p-2 text-[10px]"
                        >
                            <p class="mb-1 font-bold text-muted-foreground">
                                Transit Details:
                            </p>
                            <div
                                v-for="(
                                    seg, idx
                                ) in return_flight.segments.slice(0, -1)"
                                :key="'ret_transit_' + idx"
                                class="flex items-center justify-between text-foreground"
                            >
                                <span>
                                    Layover at
                                    <span class="font-bold">{{
                                        seg.destination_airport
                                    }}</span>
                                </span>
                                <span class="font-mono text-amber-600">
                                    {{
                                        calculateDuration(
                                            seg.arrival_at,
                                            return_flight.segments[idx + 1]
                                                .departure_at,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="mt-3 flex flex-wrap gap-1.5 border-t border-border/50 pt-3"
                        >
                            <span
                                v-if="return_flight.facilities?.meal"
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                >🍱 Meal</span
                            >
                            <span
                                v-if="return_flight.facilities?.wifi"
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                >📶 WiFi</span
                            >
                            <span
                                v-if="return_flight.facilities?.entertainment"
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                >🎬 Screen</span
                            >
                            <span
                                v-if="return_flight.facilities?.power_usb"
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                >🔌 USB</span
                            >
                            <span
                                v-if="return_flight.is_refundable"
                                class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700"
                                >✓ Refundable</span
                            >
                            <span
                                v-if="return_flight.is_reschedulable"
                                class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700"
                                >✓ Reschedule</span
                            >
                        </div>
                    </div>

                    <div
                        class="mb-6 flex flex-col gap-3 border-t border-b border-border py-4 text-sm"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground"
                                >Flight Tickets (x{{
                                    form.passengers.length
                                }})</span
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
                            v-if="totalBaggageFees > 0"
                            class="flex items-center justify-between"
                        >
                            <span
                                class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700"
                                >Extra Baggage Add-ons</span
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

                        <div class="mt-1 flex items-center justify-between">
                            <span
                                class="text-[10px] text-muted-foreground italic"
                                >*Seat selection fees will be calculated in the
                                next step</span
                            >
                        </div>
                    </div>

                    <div class="mb-6 flex items-end justify-between">
                        <span class="font-bold text-muted-foreground"
                            >Current Total</span
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
                        @click="submitToSeatSelection"
                        class="w-full shadow-md transition-all disabled:opacity-50"
                        size="lg"
                        :disabled="form.processing || !isFormValid"
                        :class="
                            isFormValid
                                ? 'hover:bg-primary-hover bg-primary text-primary-foreground hover:shadow-lg'
                                : 'cursor-not-allowed bg-muted text-muted-foreground'
                        "
                    >
                        <span v-if="form.processing">Saving details...</span>
                        <span
                            v-else
                            class="flex w-full items-center justify-center gap-2"
                        >
                            {{
                                isFormValid
                                    ? 'Lanjut Pilih Kursi'
                                    : 'Isi Data Penumpang Dulu'
                            }}
                            <svg
                                v-if="isFormValid"
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5l7 7-7 7"
                                />
                            </svg>
                        </span>
                    </Button>
                </div>
            </div>
        </main>
    </AeroLayout>
</template>
