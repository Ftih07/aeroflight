<!-- eslint-disable vue/block-lang -->
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: null });

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
    booking: Object,
});

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
                        Your E-Ticket has been sent to your email.
                    </p>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="mb-8 text-center">
                        <p
                            class="mb-1 text-sm tracking-wider text-muted-foreground uppercase"
                        >
                            Booking Reference (PNR)
                        </p>
                        <p
                            class="font-mono text-5xl font-bold tracking-[0.2em] text-primary"
                        >
                            {{ booking.pnr_code }}
                        </p>
                    </div>

                    <div
                        class="mb-6 rounded-xl border border-border bg-muted/20 p-5"
                    >
                        <div class="mb-3 text-center">
                            <span
                                class="inline-block rounded-md bg-primary/10 px-3 py-1 text-xs font-bold tracking-widest text-primary uppercase"
                            >
                                {{ booking.flight.airline_name }}
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
                                >
                                    {{
                                        calculateDuration(
                                            booking.flight.departure_at,
                                            booking.flight.arrival_at,
                                        )
                                    }}
                                </span>
                                <div
                                    class="relative flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-16 bg-border"></div>
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
                            class="flex items-center justify-between border-t border-border/50 pt-3 text-sm"
                        >
                            <span class="text-muted-foreground"
                                >Flight No.</span
                            >
                            <span class="font-mono font-bold text-foreground">{{
                                displayFlightNumber(
                                    booking.flight.airline_code,
                                    booking.flight.flight_number,
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
                                }).format(booking.total_amount_usd)
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
                                Download E-Ticket
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
