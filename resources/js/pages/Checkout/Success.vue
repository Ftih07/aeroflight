<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: null });

const props = defineProps({
    booking: Object, // Parent Booking (Outbound)
    child_booking: { type: Object, default: null }, // Child Booking (Return)
});

// Hitung total harga gabungan akhir LANGSUNG dari final_amount_usd DB
const grandTotal = computed(() => {
    return Number(props.booking.final_amount_usd || 0);
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
    <Head title="Payment Successful" />

    <AeroLayout>
        <main
            class="flex min-h-[80vh] items-center justify-center px-4 pt-24 pb-12 sm:px-6 lg:px-8"
        >
            <div
                class="w-full max-w-lg overflow-hidden rounded-2xl border border-border bg-card shadow-xl"
            >
                <div
                    class="relative overflow-hidden bg-emerald-500 p-8 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm"
                    >
                        <svg
                            class="h-10 w-10 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-white">
                        Payment Successful!
                    </h2>
                    <p class="mt-1 text-emerald-50">
                        Your E-Ticket(s) have been sent to your email.
                    </p>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="mb-8 flex flex-col gap-4">
                        <div
                            class="flex flex-col items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-6 text-center"
                        >
                            <p
                                class="mb-2 text-[10px] font-bold tracking-wider text-emerald-600 uppercase"
                            >
                                {{
                                    child_booking
                                        ? 'Outbound PNR'
                                        : 'Booking Reference (PNR)'
                                }}
                            </p>
                            <p
                                class="mb-4 font-mono text-4xl font-black tracking-[0.15em] text-primary"
                            >
                                {{ booking.pnr_code }}
                            </p>

                            <div
                                v-if="booking.qr_token"
                                class="inline-block rounded-xl border border-border bg-white p-2 shadow-sm"
                            >
                                <img
                                    :src="`https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${booking.qr_token}`"
                                    class="h-28 w-28"
                                    alt="Boarding QR"
                                />
                            </div>
                        </div>

                        <div
                            v-if="child_booking"
                            class="flex flex-col items-center justify-center rounded-xl border border-blue-500/30 bg-blue-500/5 p-6 text-center"
                        >
                            <p
                                class="mb-2 text-[10px] font-bold tracking-wider text-blue-600 uppercase"
                            >
                                Return PNR
                            </p>
                            <p
                                class="mb-4 font-mono text-4xl font-black tracking-[0.15em] text-primary"
                            >
                                {{ child_booking.pnr_code }}
                            </p>

                            <div
                                v-if="child_booking.qr_token"
                                class="inline-block rounded-xl border border-border bg-white p-2 shadow-sm"
                            >
                                <img
                                    :src="`https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${child_booking.qr_token}`"
                                    class="h-28 w-28"
                                    alt="Boarding QR"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="relative mb-4 overflow-hidden rounded-xl border border-border bg-muted/20 p-5"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-emerald-500"
                        ></div>
                        <div class="mb-3 text-center">
                            <span
                                class="inline-block rounded-md bg-emerald-100 px-3 py-1 text-xs font-bold tracking-widest text-emerald-700 uppercase"
                            >
                                🛫
                                {{
                                    booking.flight.segments?.[0]?.airlineData
                                        ?.name ||
                                    booking.flight.segments?.[0]?.airline_code
                                }}
                            </span>
                        </div>
                        <div class="mb-4 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-2xl font-black text-foreground"
                                    >{{ booking.flight.origin_airport }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(booking.flight.departure_at)
                                    }}</span
                                >
                            </div>
                            <div class="flex flex-col items-center px-2">
                                <span
                                    class="mb-1 text-[10px] font-bold text-muted-foreground"
                                    >{{
                                        calculateDuration(
                                            booking.flight.departure_at,
                                            booking.flight.arrival_at,
                                        )
                                    }}</span
                                >
                                <div
                                    class="relative flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-12 bg-border"></div>
                                    <svg
                                        class="absolute h-5 w-5 bg-transparent text-primary"
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
                                    class="block text-2xl font-black text-foreground"
                                    >{{
                                        booking.flight.destination_airport
                                    }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(booking.flight.arrival_at)
                                    }}</span
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
                                    booking.flight.segments?.[0]?.airline_code,
                                    booking.flight.segments?.[0]?.flight_number,
                                )
                            }}</span>
                        </div>
                    </div>

                    <div
                        v-if="child_booking"
                        class="relative mb-6 overflow-hidden rounded-xl border border-border bg-muted/20 p-5"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-blue-500"
                        ></div>
                        <div class="mb-3 text-center">
                            <span
                                class="inline-block rounded-md bg-blue-100 px-3 py-1 text-xs font-bold tracking-widest text-blue-700 uppercase"
                            >
                                🛬
                                {{
                                    child_booking.flight.segments?.[0]
                                        ?.airlineData?.name ||
                                    child_booking.flight.segments?.[0]
                                        ?.airline_code
                                }}
                            </span>
                        </div>
                        <div class="mb-4 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-2xl font-black text-foreground"
                                    >{{
                                        child_booking.flight.origin_airport
                                    }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(
                                            child_booking.flight.departure_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="flex flex-col items-center px-2">
                                <span
                                    class="mb-1 text-[10px] font-bold text-muted-foreground"
                                    >{{
                                        calculateDuration(
                                            child_booking.flight.departure_at,
                                            child_booking.flight.arrival_at,
                                        )
                                    }}</span
                                >
                                <div
                                    class="relative flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-12 bg-border"></div>
                                    <svg
                                        class="absolute h-5 w-5 bg-transparent text-primary"
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
                                    class="block text-2xl font-black text-foreground"
                                    >{{
                                        child_booking.flight.destination_airport
                                    }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(
                                            child_booking.flight.arrival_at,
                                        )
                                    }}</span
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
                                    child_booking.flight.segments?.[0]
                                        ?.airline_code,
                                    child_booking.flight.segments?.[0]
                                        ?.flight_number,
                                )
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="mb-8 flex items-center justify-between rounded-xl border border-primary/10 bg-primary/5 p-4"
                    >
                        <span class="font-medium text-muted-foreground"
                            >Total Paid</span
                        >
                        <span class="text-2xl font-black text-primary">
                            {{
                                new Intl.NumberFormat('en-US', {
                                    style: 'currency',
                                    currency: 'USD',
                                }).format(grandTotal)
                            }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        <a
                            :href="`/bookings/${booking.id}/ticket`"
                            class="block w-full"
                        >
                            <Button
                                class="hover:bg-primary-hover flex w-full items-center justify-center gap-2 bg-primary text-primary-foreground shadow-md transition-all hover:shadow-lg"
                                size="lg"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                    />
                                </svg>
                                Download PDF Ticket
                            </Button>
                        </a>
                        <Link href="/my-bookings" class="block">
                            <Button
                                class="w-full border-border text-foreground hover:bg-muted"
                                variant="outline"
                                size="lg"
                            >
                                Go to My Bookings
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </main>
    </AeroLayout>
</template>
