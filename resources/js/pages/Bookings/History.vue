<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import AeroLayout from '@/layouts/AeroLayout.vue';

// MATIKAN LAYOUT GLOBAL BAWAAN BREEZE
defineOptions({
    // @ts-expect-error - Inertia layout typing not recognized
    layout: null,
});

defineProps<{
    bookings: any[];
}>();

// --- 1. STATE MANAGEMENT ---
// State untuk Modal Refund
const showRefundModal = ref(false);
const bookingToRefund = ref<number | null>(null);

// State untuk Modal Summary
const showSummaryModal = ref(false);
const activeBooking = ref<any | null>(null); // Pakai any agar TS tidak protes soal nested property

// --- 2. HELPER FUNCTIONS ---
const formatDate = (dateString: string) => {
    if (!dateString) return '-';

    return new Date(dateString).toLocaleDateString('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const formatTime = (dateString: string) => {
    if (!dateString) return '--:--';

    return new Date(dateString).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const calculateDuration = (departure: string, arrival: string) => {
    if (!departure || !arrival) return '-';

    const start = new Date(departure).getTime();
    const end = new Date(arrival).getTime();
    const diffMs = end - start;
    const diffHrs = Math.floor(diffMs / (1000 * 60 * 60));
    const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    if (diffHrs === 0) return `${diffMins}m`;

    return `${diffHrs}h ${diffMins}m`;
};

// --- 3. MODAL HANDLERS ---
// Modal Refund
const openRefundModal = (id: number) => {
    bookingToRefund.value = id;
    showRefundModal.value = true;
};

const closeRefundModal = () => {
    showRefundModal.value = false;
    setTimeout(() => {
        bookingToRefund.value = null;
    }, 200); // Tunggu animasi selesai baru reset ID
};

const confirmRefund = () => {
    if (bookingToRefund.value !== null) {
        router.post(
            `/bookings/${bookingToRefund.value}/refund`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => closeRefundModal(),
            },
        );
    }
};

// Modal Summary
const openSummaryModal = (booking: any) => {
    activeBooking.value = booking;
    showSummaryModal.value = true;
};

const closeSummaryModal = () => {
    showSummaryModal.value = false;
    setTimeout(() => {
        activeBooking.value = null;
    }, 200);
};

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
};
</script>

<template>
    <Head title="My Bookings" />

    <AeroLayout>
        <main class="min-h-[80vh] px-4 pt-24 pb-12 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-8">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-foreground">
                        My Bookings
                    </h2>
                    <span
                        class="rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                    >
                        {{ bookings.length }} Trips
                    </span>
                </div>

                <div
                    v-if="bookings.length === 0"
                    class="rounded-2xl border border-border bg-card py-20 text-center shadow-sm"
                >
                    <div class="mb-4 text-5xl opacity-40">🎫</div>
                    <h3 class="mb-2 text-lg font-bold text-foreground">
                        No Bookings Found
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        You haven't booked any flights yet. Start exploring the
                        world with AeroFlight!
                    </p>
                    <a href="/#routes" class="mt-6 inline-block">
                        <Button
                            class="hover:bg-primary-hover bg-primary text-primary-foreground"
                        >
                            Explore Flights
                        </Button>
                    </a>
                </div>

                <div class="space-y-6">
                    <div
                        v-for="booking in bookings"
                        :key="booking.id"
                        class="group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-all hover:border-primary/40 hover:shadow-md md:flex-row"
                    >
                        <div class="flex-1 p-6">
                            <div
                                class="mb-6 flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="rounded-md bg-primary/10 px-2 py-1 text-[10px] font-bold text-primary uppercase"
                                    >
                                        {{
                                            booking.flight.airline_name ||
                                            booking.flight.airline_code
                                        }}
                                    </span>
                                    <span
                                        class="rounded-md bg-muted px-2.5 py-1 font-mono text-xs font-bold tracking-widest text-muted-foreground"
                                    >
                                        PNR:
                                        <span class="text-foreground">{{
                                            booking.pnr_code || 'PENDING'
                                        }}</span>
                                    </span>
                                </div>

                                <span
                                    v-if="booking.status === 'paid'"
                                    class="rounded-full bg-emerald-500/15 px-3 py-1 text-[10px] font-extrabold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    Confirmed
                                </span>
                                <span
                                    v-else-if="booking.status === 'used'"
                                    class="rounded-full bg-muted px-3 py-1 text-[10px] font-extrabold tracking-wider text-muted-foreground uppercase"
                                >
                                    Flown
                                </span>
                                <span
                                    v-else-if="
                                        booking.status === 'refund_requested'
                                    "
                                    class="rounded-full bg-amber-500/15 px-3 py-1 text-[10px] font-extrabold tracking-wider text-amber-600 uppercase dark:text-amber-400"
                                >
                                    Refund Pending
                                </span>
                                <span
                                    v-else-if="
                                        ['refunded', 'cancelled'].includes(
                                            booking.status,
                                        )
                                    "
                                    class="rounded-full bg-destructive/15 px-3 py-1 text-[10px] font-extrabold tracking-wider text-destructive uppercase dark:text-red-400"
                                >
                                    {{ booking.status }}
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-primary/15 px-3 py-1 text-[10px] font-extrabold tracking-wider text-primary uppercase dark:text-blue-400"
                                >
                                    Awaiting Payment
                                </span>
                            </div>

                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="text-center sm:text-left">
                                    <p
                                        class="text-2xl font-black text-foreground"
                                    >
                                        {{ booking.flight.origin_airport }}
                                    </p>
                                    <p
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        Origin
                                    </p>
                                </div>

                                <div
                                    class="flex flex-1 flex-col items-center px-4"
                                >
                                    <span
                                        v-if="!booking.flight.is_transit"
                                        class="mb-1 text-[10px] font-bold tracking-widest text-emerald-500 uppercase"
                                    >
                                        Direct
                                    </span>
                                    <span
                                        v-else
                                        class="mb-1 text-[10px] font-bold tracking-widest text-amber-500 uppercase"
                                    >
                                        Transit
                                    </span>
                                    <div
                                        class="relative flex w-full items-center justify-center"
                                    >
                                        <div
                                            class="h-[2px] w-full bg-border"
                                        ></div>
                                        <svg
                                            class="absolute h-5 w-5 bg-card px-0.5 text-primary"
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
                                    <p
                                        class="mt-2 text-xs font-semibold text-foreground"
                                    >
                                        {{ booking.flight.airline_code }}-{{
                                            booking.flight.flight_number
                                        }}
                                    </p>
                                </div>

                                <div class="text-center sm:text-right">
                                    <p
                                        class="text-2xl font-black text-foreground"
                                    >
                                        {{ booking.flight.destination_airport }}
                                    </p>
                                    <p
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        Destination
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-6 flex flex-wrap gap-y-4 rounded-lg bg-muted/30 p-4"
                            >
                                <div class="w-1/2 sm:w-1/3">
                                    <p
                                        class="text-[10px] font-bold text-muted-foreground uppercase"
                                    >
                                        Departure Date
                                    </p>
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        {{
                                            formatDate(
                                                booking.flight.departure_at,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="w-1/2 sm:w-1/3">
                                    <p
                                        class="text-[10px] font-bold text-muted-foreground uppercase"
                                    >
                                        Time
                                    </p>
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        {{
                                            formatTime(
                                                booking.flight.departure_at,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="w-full sm:w-1/3 sm:text-right">
                                    <p
                                        class="text-[10px] font-bold text-muted-foreground uppercase"
                                    >
                                        Total Amount
                                    </p>
                                    <p class="text-sm font-black text-primary">
                                        {{
                                            new Intl.NumberFormat('en-US', {
                                                style: 'currency',
                                                currency: 'USD',
                                            }).format(booking.total_amount_usd)
                                        }}
                                    </p>
                                </div>
                            </div>

                            <p
                                v-if="booking.status === 'refunded'"
                                class="mt-3 flex items-center gap-1 text-xs font-bold text-destructive"
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
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                Refunded Amount: ${{
                                    booking.transactions?.find(
                                        (t: any) => t.type === 'refund',
                                    )?.amount || '0.00'
                                }}
                            </p>
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
                            class="flex flex-col justify-center gap-3 bg-muted/10 p-6 md:w-56"
                        >
                            <Button
                                @click="openSummaryModal(booking)"
                                variant="outline"
                                class="w-full shadow-sm"
                            >
                                View Details
                            </Button>

                            <a
                                v-if="['paid', 'used'].includes(booking.status)"
                                :href="`/bookings/${booking.id}/ticket`"
                                class="w-full"
                            >
                                <Button
                                    class="hover:bg-primary-hover w-full bg-primary text-primary-foreground shadow-sm"
                                >
                                    Download E-Ticket
                                </Button>
                            </a>

                            <Button
                                v-if="booking.status === 'paid'"
                                variant="outline"
                                class="w-full border-destructive/50 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="openRefundModal(booking.id)"
                            >
                                Request Refund
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <transition name="modal">
            <div
                v-if="showRefundModal"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-background/80 backdrop-blur-sm transition-opacity"
                    @click="closeRefundModal"
                ></div>
                <div
                    class="relative z-10 w-full max-w-md transform overflow-hidden rounded-2xl border border-border bg-card p-6 text-left shadow-2xl transition-all"
                >
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-destructive/10"
                    >
                        <svg
                            class="h-7 w-7 text-destructive"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>
                    <h3
                        class="mb-2 text-center text-lg font-bold text-foreground"
                    >
                        Confirm Refund Request
                    </h3>
                    <p class="mb-6 text-center text-sm text-muted-foreground">
                        Are you sure you want to cancel this booking and request
                        a refund? Your seat will be released and this action
                        <span class="font-bold text-foreground"
                            >cannot be undone</span
                        >.
                    </p>
                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row">
                        <button
                            @click="closeRefundModal"
                            class="w-full rounded-md border border-border bg-background px-4 py-2 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmRefund"
                            class="w-full rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-red-700"
                        >
                            Yes, Refund
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <transition name="modal">
            <div
                v-if="showSummaryModal && activeBooking"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-background/80 backdrop-blur-sm transition-opacity"
                    @click="closeSummaryModal"
                ></div>
                <div
                    class="relative z-10 w-full max-w-md transform overflow-hidden rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all"
                >
                    <button
                        @click="closeSummaryModal"
                        class="absolute top-4 right-4 text-muted-foreground hover:text-foreground"
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
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>

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
                                {{
                                    activeBooking.flight.airline_name ||
                                    activeBooking.flight.airline_code
                                }}
                            </span>
                        </div>

                        <div class="mb-4 flex items-center justify-between">
                            <div class="text-left">
                                <span
                                    class="block text-lg font-black text-foreground"
                                    >{{
                                        activeBooking.flight.origin_airport
                                    }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(
                                            activeBooking.flight.departure_at,
                                        )
                                    }}</span
                                >
                            </div>

                            <div class="flex flex-col items-center px-2">
                                <span
                                    v-if="
                                        !activeBooking.flight.transits ||
                                        activeBooking.flight.transits ===
                                            'null' ||
                                        activeBooking.flight.transits.length ===
                                            0
                                    "
                                    class="mb-1 text-[10px] font-bold tracking-widest text-emerald-500 uppercase"
                                >
                                    Direct
                                </span>
                                <span
                                    v-else
                                    class="mb-1 text-[10px] font-bold tracking-widest text-amber-500 uppercase"
                                >
                                    Transit
                                </span>

                                <span
                                    class="mb-1 text-[10px] font-bold text-muted-foreground"
                                >
                                    {{
                                        calculateDuration(
                                            activeBooking.flight.departure_at,
                                            activeBooking.flight.arrival_at,
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
                                    >{{
                                        activeBooking.flight.destination_airport
                                    }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-muted-foreground"
                                    >{{
                                        formatTime(
                                            activeBooking.flight.arrival_at,
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
                            <span class="font-mono font-bold text-foreground">
                                {{ activeBooking.flight.airline_code }}-{{
                                    activeBooking.flight.flight_number
                                }}
                            </span>
                        </div>
                        <div
                            class="mt-1 flex items-center justify-between text-xs"
                        >
                            <span class="text-muted-foreground">Aircraft</span>
                            <span class="font-medium text-foreground">{{
                                activeBooking.flight.aircraft?.model_name ||
                                'TBA'
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="mb-6 flex flex-col gap-4 border-b border-border pb-5 text-sm"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex flex-col">
                                <span class="font-medium text-foreground">
                                    Tickets (x{{
                                        activeBooking.pax_count || 1
                                    }})
                                </span>
                                <span
                                    class="text-[10px] tracking-tight text-muted-foreground uppercase"
                                >
                                    {{
                                        activeBooking.pax_count || 1
                                    }}
                                    passenger(s) @
                                    {{
                                        formatCurrency(
                                            activeBooking.flight.base_price_usd,
                                        )
                                    }}
                                </span>
                            </div>
                            <span class="font-bold text-foreground">
                                {{
                                    formatCurrency(
                                        (activeBooking.pax_count || 1) *
                                            activeBooking.flight.base_price_usd,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            v-if="activeBooking.seat_upgrade_fee > 0"
                            class="flex items-start justify-between"
                        >
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 uppercase"
                                    >
                                        Seat Upgrades
                                    </span>
                                </div>
                                <span
                                    class="mt-0.5 text-[10px] tracking-tight text-muted-foreground uppercase"
                                >
                                    {{ activeBooking.pax_count || 1 }} seat(s) @
                                    {{
                                        formatCurrency(
                                            activeBooking.seat_upgrade_fee /
                                                (activeBooking.pax_count || 1),
                                        )
                                    }}
                                </span>
                            </div>
                            <span class="font-bold text-foreground">
                                +
                                {{
                                    formatCurrency(
                                        activeBooking.seat_upgrade_fee,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            v-if="activeBooking.baggage_fee > 0"
                            class="flex items-start justify-between"
                        >
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700 uppercase"
                                    >
                                        Extra Baggage
                                    </span>
                                </div>
                                <span
                                    class="mt-0.5 text-[10px] tracking-tight text-muted-foreground uppercase"
                                >
                                    Additional weight/items
                                </span>
                            </div>
                            <span class="font-bold text-foreground">
                                +
                                {{ formatCurrency(activeBooking.baggage_fee) }}
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
                                }).format(activeBooking.total_amount_usd || 0)
                            }}
                        </span>
                    </div>

                    <div v-if="['paid', 'used'].includes(activeBooking.status)">
                        <a :href="`/bookings/${activeBooking.id}/ticket`">
                            <Button
                                class="hover:bg-primary-hover w-full bg-primary text-primary-foreground shadow-md transition-all hover:shadow-lg"
                                size="lg"
                            >
                                Download E-Ticket
                            </Button>
                        </a>
                    </div>
                    <div
                        v-else-if="
                            activeBooking.status === 'pending' ||
                            activeBooking.status === 'unpaid'
                        "
                    >
                        <a :href="`/bookings/${activeBooking.id}/payment`">
                            <Button
                                class="w-full bg-blue-600 text-white shadow-md hover:bg-blue-700"
                                size="lg"
                            >
                                Pay Securely &rarr;
                            </Button>
                        </a>
                        <p
                            class="mt-4 text-center text-xs text-muted-foreground"
                        >
                            Please complete your payment to secure the booking.
                        </p>
                    </div>
                </div>
            </div>
        </transition>
    </AeroLayout>
</template>

<style scoped>
/* Transisi untuk Modal */
.modal-enter-active,
.modal-leave-active {
    transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95) translateY(10px);
    opacity: 0;
}
</style>
