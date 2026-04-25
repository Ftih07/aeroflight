<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import AeroLayout from '@/layouts/AeroLayout.vue';

defineOptions({ layout: undefined });

const props = defineProps<{
    bookings: any[];
}>();

// --- 1. STATE MANAGEMENT ---
const showRefundModal = ref(false);
const bookingToRefund = ref<number | null>(null);
const showSummaryModal = ref(false);
const activeBooking = ref<any | null>(null);

const searchQuery = ref('');
const statusFilter = ref('all');
const currentPage = ref(1);
const itemsPerPage = 5;

watch([searchQuery, statusFilter], () => {
    currentPage.value = 1;
});

// --- FETCH KOTA BANDARA ---
const allAirports = ref<any[]>([]);
onMounted(async () => {
    try {
        const res = await fetch(
            'https://gist.githubusercontent.com/tdreyno/4278655/raw/7b0762c09b519f40397e4c3e100b097d861f5588/airports.json',
        );
        if (res.ok) {
            const data = await res.json();
            allAirports.value = data.filter(
                (a: any) => a.code && a.code.trim() !== '',
            );
        }
    } catch (e) {
        console.error(e);
    }
});

const getCityName = (code: string) => {
    if (!allAirports.value.length) return '';
    const airport = allAirports.value.find((a: any) => a.code === code);
    return airport ? airport.name : '';
};

// --- 2. LOGIC FILTER & SEARCH ---
const filteredBookings = computed(() => {
    return props.bookings.filter((booking) => {
        if (statusFilter.value !== 'all') {
            if (
                statusFilter.value === 'awaiting_payment' &&
                booking.status !== 'draft'
            )
                return false;
            if (statusFilter.value === 'upcoming' && booking.status !== 'paid')
                return false;
            if (statusFilter.value === 'flown' && booking.status !== 'used')
                return false;
            if (
                statusFilter.value === 'cancelled' &&
                !['refunded', 'cancelled', 'refund_requested'].includes(
                    booking.status,
                )
            )
                return false;
        }

        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const pnrMatch = (booking.pnr_code || 'pending')
                .toLowerCase()
                .includes(q);
            const originMatch = (booking.flight.origin_airport || '')
                .toLowerCase()
                .includes(q);
            const destMatch = (booking.flight.destination_airport || '')
                .toLowerCase()
                .includes(q);

            // 👇 UPDATE LOGIKA SEARCH AIRLINE
            const mainAirlineName =
                booking.flight.segments?.[0]?.airlineData?.name || '';
            const mainAirlineCode =
                booking.flight.segments?.[0]?.airline_code || '';
            const airlineMatch = (mainAirlineName || mainAirlineCode)
                .toLowerCase()
                .includes(q);

            if (!pnrMatch && !originMatch && !destMatch && !airlineMatch) {
                return false;
            }
        }

        return true;
    });
});

const paginatedBookings = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredBookings.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() =>
    Math.ceil(filteredBookings.value.length / itemsPerPage),
);

// --- 3. FORMATTERS ---
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

const formatDateTime = (dateString: string) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const calculateDuration = (departure: string, arrival: string) => {
    if (!departure || !arrival) return '-';
    const diffMs = new Date(arrival).getTime() - new Date(departure).getTime();
    const diffHrs = Math.floor(diffMs / (1000 * 60 * 60));
    const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    return diffHrs === 0 ? `${diffMins}m` : `${diffHrs}h ${diffMins}m`;
};

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
};

// --- HELPER SEATS ---
const formatSeats = (seatsJson: any) => {
    if (!seatsJson) return 'TBA';
    // Menghandle data yang mungkin masih string JSON dari database
    const parsedSeats =
        typeof seatsJson === 'string' ? JSON.parse(seatsJson) : seatsJson;
    if (typeof parsedSeats === 'object' && parsedSeats !== null) {
        return Object.values(parsedSeats).join(', ');
    }
    return 'TBA';
};

// --- 4. MODAL HANDLERS ---
const openRefundModal = (id: number) => {
    bookingToRefund.value = id;
    showRefundModal.value = true;
};
const closeRefundModal = () => {
    showRefundModal.value = false;
    setTimeout(() => {
        bookingToRefund.value = null;
    }, 200);
};
const confirmRefund = () => {
    if (bookingToRefund.value !== null) {
        router.post(
            `/bookings/${bookingToRefund.value}/refund`,
            {},
            { preserveScroll: true, onSuccess: () => closeRefundModal() },
        );
    }
};

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
</script>

<template>
    <Head title="My Bookings" />

    <AeroLayout>
        <main class="min-h-[80vh] px-4 pt-24 pb-12 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-8">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="flex items-center gap-3 text-2xl font-bold text-foreground"
                        >
                            My Bookings
                            <span
                                class="rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                                >{{ filteredBookings.length }} Trips</span
                            >
                        </h2>
                    </div>
                </div>

                <div
                    v-if="bookings.length > 0"
                    class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:flex-row"
                >
                    <div class="relative flex-1">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                        >
                            <svg
                                class="h-4 w-4 text-muted-foreground"
                                fill="none"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"
                                />
                            </svg>
                        </div>
                        <input
                            type="text"
                            v-model="searchQuery"
                            class="block w-full rounded-lg border border-input bg-background p-2.5 pl-10 text-sm text-foreground focus:border-primary focus:ring-primary"
                            placeholder="Search by PNR, City, or Airline..."
                        />
                    </div>
                    <select
                        v-model="statusFilter"
                        class="rounded-lg border border-input bg-background p-2.5 text-sm text-foreground focus:border-primary focus:ring-primary sm:w-48"
                    >
                        <option value="all">All Status</option>
                        <option value="awaiting_payment">
                            Awaiting Payment
                        </option>
                        <option value="upcoming">Upcoming Flights</option>
                        <option value="flown">Completed (Flown)</option>
                        <option value="cancelled">Refunded / Cancelled</option>
                    </select>
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
                    <a href="/#routes" class="mt-6 inline-block"
                        ><Button
                            class="hover:bg-primary-hover bg-primary text-primary-foreground"
                            >Explore Flights</Button
                        ></a
                    >
                </div>

                <div
                    v-else-if="filteredBookings.length === 0"
                    class="rounded-2xl border border-border bg-card py-16 text-center shadow-sm"
                >
                    <h3 class="mb-2 text-lg font-bold text-foreground">
                        No matches found
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        We couldn't find any bookings matching your criteria.
                    </p>
                    <button
                        @click="
                            searchQuery = '';
                            statusFilter = 'all';
                        "
                        class="mt-4 text-sm font-semibold text-primary hover:underline"
                    >
                        Clear Filters
                    </button>
                </div>

                <div v-else class="space-y-6">
                    <div
                        v-for="booking in paginatedBookings"
                        :key="booking.id"
                        class="group relative flex animate-in flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-all duration-300 fade-in slide-in-from-bottom-4 hover:border-primary/40 hover:shadow-md md:flex-row"
                    >
                        <div class="flex-1 p-6">
                            <div
                                class="mb-6 flex flex-wrap items-center justify-between gap-2 border-b border-border/50 pb-4"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="rounded-md bg-primary/10 px-2 py-1 text-[10px] font-bold text-primary uppercase"
                                    >
                                        {{
                                            booking.flight.segments?.[0]
                                                ?.airlineData?.name ||
                                            booking.flight.segments?.[0]
                                                ?.airline_code
                                        }}
                                    </span>
                                    <span
                                        class="font-mono text-xs font-bold text-muted-foreground"
                                    >
                                        PNR:
                                        <span class="text-foreground">{{
                                            booking.pnr_code || 'PENDING'
                                        }}</span>
                                    </span>
                                </div>
                                <div>
                                    <span
                                        v-if="booking.status === 'paid'"
                                        class="rounded-full bg-emerald-500/15 px-3 py-1 text-[10px] font-extrabold tracking-wider text-emerald-600 uppercase"
                                        >Confirmed</span
                                    >
                                    <span
                                        v-else-if="booking.status === 'used'"
                                        class="rounded-full bg-muted px-3 py-1 text-[10px] font-extrabold tracking-wider text-muted-foreground uppercase"
                                        >Flown</span
                                    >
                                    <span
                                        v-else-if="
                                            booking.status ===
                                            'refund_requested'
                                        "
                                        class="rounded-full bg-amber-500/15 px-3 py-1 text-[10px] font-extrabold tracking-wider text-amber-600 uppercase"
                                        >Refund Pending</span
                                    >
                                    <span
                                        v-else-if="
                                            ['refunded', 'cancelled'].includes(
                                                booking.status,
                                            )
                                        "
                                        class="rounded-full bg-destructive/15 px-3 py-1 text-[10px] font-extrabold tracking-wider text-destructive uppercase"
                                        >{{ booking.status }}</span
                                    >
                                    <span
                                        v-else
                                        class="rounded-full bg-primary/15 px-3 py-1 text-[10px] font-extrabold tracking-wider text-primary uppercase"
                                        >Awaiting Payment</span
                                    >
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="w-1/3 text-left">
                                    <p
                                        class="text-2xl font-black text-foreground"
                                    >
                                        {{ booking.flight.origin_airport }}
                                    </p>
                                    <p
                                        class="max-w-[120px] truncate text-[10px] font-bold text-muted-foreground uppercase"
                                    >
                                        {{
                                            getCityName(
                                                booking.flight.origin_airport,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-foreground"
                                    >
                                        {{
                                            formatDate(
                                                booking.flight.departure_at,
                                            )
                                        }},
                                        {{
                                            formatTime(
                                                booking.flight.departure_at,
                                            )
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="flex flex-1 flex-col items-center px-2"
                                >
                                    <span
                                        v-if="booking.flight.stop_count === 0"
                                        class="mb-1 text-[10px] font-bold tracking-widest text-emerald-500 uppercase"
                                        >Direct</span
                                    >
                                    <span
                                        v-else
                                        class="mb-1 text-[10px] font-bold tracking-widest text-amber-500 uppercase"
                                        >{{
                                            booking.flight.stop_count
                                        }}
                                        Stop</span
                                    >

                                    <div
                                        class="relative my-1 flex w-full items-center justify-center"
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
                                        class="text-[10px] font-medium text-muted-foreground"
                                    >
                                        {{
                                            calculateDuration(
                                                booking.flight.departure_at,
                                                booking.flight.arrival_at,
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="w-1/3 text-right">
                                    <p
                                        class="text-2xl font-black text-foreground"
                                    >
                                        {{ booking.flight.destination_airport }}
                                    </p>
                                    <p
                                        class="ml-auto max-w-[120px] truncate text-[10px] font-bold text-muted-foreground uppercase"
                                    >
                                        {{
                                            getCityName(
                                                booking.flight
                                                    .destination_airport,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-semibold text-foreground"
                                    >
                                        {{
                                            formatDate(
                                                booking.flight.arrival_at,
                                            )
                                        }},
                                        {{
                                            formatTime(
                                                booking.flight.arrival_at,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <p
                                v-if="booking.status === 'refunded'"
                                class="mt-4 flex items-center gap-1 rounded bg-destructive/5 p-2 text-xs font-bold text-destructive"
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
                            class="flex flex-col justify-center gap-3 border-t border-dashed border-border bg-muted/10 p-6 md:w-64 md:border-t-0 md:border-l"
                        >
                            <div class="mb-2 text-center">
                                <p
                                    class="text-[10px] font-bold text-muted-foreground uppercase"
                                >
                                    Total Amount
                                </p>
                                <p class="text-xl font-black text-primary">
                                    {{
                                        formatCurrency(
                                            Number(booking.total_amount_usd) +
                                                (booking.return_booking
                                                    ? Number(
                                                          booking.return_booking
                                                              .total_amount_usd,
                                                      )
                                                    : 0),
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="booking.return_booking"
                                    class="mt-1 inline-block rounded bg-blue-100 px-1.5 py-0.5 text-[9px] font-bold text-blue-600 uppercase"
                                >
                                    Round Trip
                                </p>
                            </div>

                            <Button
                                @click="openSummaryModal(booking)"
                                variant="outline"
                                class="w-full border-primary/20 text-primary shadow-sm hover:bg-primary/5"
                                >View Details</Button
                            >

                            <a
                                v-if="booking.status === 'pending'"
                                :href="`/bookings/${booking.id}/payment`"
                                class="w-full"
                            >
                                <Button
                                    class="w-full bg-blue-600 text-white shadow-sm transition-colors hover:bg-blue-700"
                                    >Pay Securely</Button
                                >
                            </a>

                            <a
                                v-if="['paid', 'used'].includes(booking.status)"
                                :href="`/bookings/${booking.id}/ticket`"
                                class="w-full"
                            >
                                <Button
                                    class="hover:bg-primary-hover w-full bg-primary text-primary-foreground shadow-sm"
                                    >Download E-Ticket</Button
                                >
                            </a>

                            <Button
                                v-if="booking.status === 'paid'"
                                variant="outline"
                                class="w-full border-destructive/50 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="openRefundModal(booking.id)"
                                >Request Refund</Button
                            >
                        </div>
                    </div>
                </div>

                <div
                    v-if="totalPages > 1"
                    class="mt-8 flex items-center justify-center gap-4"
                >
                    <Button
                        @click="currentPage--"
                        :disabled="currentPage === 1"
                        variant="outline"
                        size="sm"
                        >Previous</Button
                    >
                    <span class="text-sm font-semibold text-muted-foreground"
                        >Page {{ currentPage }} of {{ totalPages }}</span
                    >
                    <Button
                        @click="currentPage++"
                        :disabled="currentPage === totalPages"
                        variant="outline"
                        size="sm"
                        >Next</Button
                    >
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
                    class="custom-scrollbar relative z-10 max-h-[90vh] w-full max-w-lg transform overflow-hidden overflow-y-auto rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all"
                >
                    <button
                        @click="closeSummaryModal"
                        class="absolute top-4 right-4 rounded-full bg-muted p-1 text-muted-foreground hover:text-foreground"
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

                    <div
                        class="mb-5 flex flex-col border-b border-border pr-8 pb-4"
                    >
                        <h3 class="mb-1 text-xl font-bold text-foreground">
                            E-Ticket Summary
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Booked on:
                            {{ formatDateTime(activeBooking.created_at) }}
                        </p>
                    </div>

                    <div
                        v-if="
                            activeBooking.status === 'paid' &&
                            activeBooking.qr_token
                        "
                        class="mb-6 flex flex-col items-center justify-center rounded-xl border-b border-dashed border-border bg-muted/10 p-4 pb-6"
                    >
                        <div class="flex gap-6">
                            <div class="text-center">
                                <p
                                    class="mb-2 inline-block rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-600"
                                >
                                    OUTBOUND PNR
                                </p>
                                <div
                                    class="mx-auto w-fit rounded-xl border border-border bg-white p-2 shadow-sm"
                                >
                                    <img
                                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${activeBooking.qr_token}`"
                                        class="h-20 w-20"
                                        alt="Outbound QR"
                                    />
                                </div>
                                <p
                                    class="mt-2 font-mono text-[11px] font-bold tracking-widest text-foreground"
                                >
                                    {{ activeBooking.pnr_code }}
                                </p>
                            </div>
                            <div
                                v-if="activeBooking.return_booking?.qr_token"
                                class="text-center"
                            >
                                <p
                                    class="mb-2 inline-block rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-600"
                                >
                                    RETURN PNR
                                </p>
                                <div
                                    class="mx-auto w-fit rounded-xl border border-border bg-white p-2 shadow-sm"
                                >
                                    <img
                                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${activeBooking.return_booking.qr_token}`"
                                        class="h-20 w-20"
                                        alt="Return QR"
                                    />
                                </div>
                                <p
                                    class="mt-2 font-mono text-[11px] font-bold tracking-widest text-foreground"
                                >
                                    {{ activeBooking.return_booking.pnr_code }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="relative mb-6 overflow-hidden rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-emerald-500"
                        ></div>

                        <div
                            class="mb-3 flex items-center justify-between border-b border-border/50 pb-3"
                        >
                            <div class="flex flex-col">
                                <span
                                    class="text-sm font-bold text-foreground"
                                    >{{
                                        activeBooking.flight.segments?.[0]
                                            ?.airlineData?.name ||
                                        activeBooking.flight.segments?.[0]
                                            ?.airline_code
                                    }}</span
                                >
                                <span
                                    class="text-[10px] text-muted-foreground uppercase"
                                    >{{
                                        activeBooking.flight.segments?.[0]
                                            ?.aircraft?.model_name ||
                                        'Aircraft TBA'
                                    }}</span
                                >
                            </div>
                            <span
                                class="rounded border border-border bg-background px-2 py-1 font-mono text-xs font-bold"
                                >{{
                                    activeBooking.flight.segments?.[0]
                                        ?.airline_code
                                }}-{{
                                    activeBooking.flight.segments?.[0]
                                        ?.flight_number
                                }}</span
                            >
                        </div>

                        <div class="mb-3 flex items-center justify-between">
                            <div class="w-1/3 text-left">
                                <span
                                    class="block text-2xl font-black text-foreground"
                                    >{{
                                        activeBooking.flight.origin_airport
                                    }}</span
                                >
                                <span
                                    class="mb-1 block truncate text-[10px] font-bold text-muted-foreground uppercase"
                                    >{{
                                        getCityName(
                                            activeBooking.flight.origin_airport,
                                        )
                                    }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-foreground"
                                    >{{
                                        formatTime(
                                            activeBooking.flight.departure_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="flex flex-1 flex-col items-center px-2">
                                <span
                                    v-if="
                                        !activeBooking.flight.stop_count ||
                                        activeBooking.flight.stop_count === 0
                                    "
                                    class="mb-1 text-[10px] font-bold tracking-widest text-emerald-500 uppercase"
                                    >Direct</span
                                >
                                <span
                                    v-else
                                    class="mb-1 text-[10px] font-bold tracking-widest text-amber-500 uppercase"
                                    >{{
                                        activeBooking.flight.stop_count
                                    }}
                                    Stop</span
                                >
                                <div
                                    class="relative flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-full bg-border"></div>
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
                                <span
                                    class="mt-1 text-[10px] font-bold text-muted-foreground"
                                    >{{
                                        calculateDuration(
                                            activeBooking.flight.departure_at,
                                            activeBooking.flight.arrival_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="w-1/3 text-right">
                                <span
                                    class="block text-2xl font-black text-foreground"
                                    >{{
                                        activeBooking.flight.destination_airport
                                    }}</span
                                >
                                <span
                                    class="mb-1 ml-auto block truncate text-[10px] font-bold text-muted-foreground uppercase"
                                    >{{
                                        getCityName(
                                            activeBooking.flight
                                                .destination_airport,
                                        )
                                    }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-foreground"
                                    >{{
                                        formatTime(
                                            activeBooking.flight.arrival_at,
                                        )
                                    }}</span
                                >
                            </div>
                        </div>

                        <div
                            v-if="
                                activeBooking.flight.stop_count > 0 &&
                                activeBooking.flight.segments
                            "
                            class="mt-2 text-center text-[10px] font-medium text-amber-600"
                        >
                            via
                            {{
                                activeBooking.flight.segments
                                    .slice(0, -1)
                                    .map(
                                        (t: any) =>
                                            getCityName(
                                                t.destination_airport,
                                            ) || t.destination_airport,
                                    )
                                    .join(', ')
                            }}
                        </div>

                        <div
                            class="mt-3 flex flex-wrap justify-center gap-1.5 border-t border-border/50 pt-3"
                        >
                            <span
                                v-if="
                                    activeBooking.flight.segments?.[0]
                                        ?.classes?.[0]?.facilities?.meal
                                "
                                class="rounded border border-border bg-background px-1.5 py-0.5 text-[9px]"
                                >🍱 Meal</span
                            >
                            <span
                                v-if="
                                    activeBooking.flight.segments?.[0]
                                        ?.classes?.[0]?.facilities?.wifi
                                "
                                class="rounded border border-border bg-background px-1.5 py-0.5 text-[9px]"
                                >📶 WiFi</span
                            >
                            <span
                                v-if="
                                    activeBooking.flight.segments?.[0]
                                        ?.classes?.[0]?.facilities
                                        ?.entertainment
                                "
                                class="rounded border border-border bg-background px-1.5 py-0.5 text-[9px]"
                                >🎬 Screen</span
                            >
                            <span
                                v-if="
                                    activeBooking.flight.segments?.[0]
                                        ?.classes?.[0]?.facilities?.power_usb
                                "
                                class="rounded border border-border bg-background px-1.5 py-0.5 text-[9px]"
                                >🔌 USB</span
                            >
                        </div>
                    </div>

                    <div
                        v-if="activeBooking.return_booking"
                        class="relative mb-6 overflow-hidden rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <div
                            class="absolute top-0 left-0 h-full w-1 bg-blue-500"
                        ></div>

                        <div
                            class="mb-3 flex items-center justify-between border-b border-border/50 pb-3"
                        >
                            <div class="flex flex-col">
                                <span
                                    class="text-sm font-bold text-foreground"
                                    >{{
                                        activeBooking.return_booking.flight
                                            .segments?.[0]?.airlineData?.name ||
                                        activeBooking.return_booking.flight
                                            .segments?.[0]?.airline_code
                                    }}</span
                                >
                                <span
                                    class="text-[10px] text-muted-foreground uppercase"
                                    >{{
                                        activeBooking.return_booking.flight
                                            .segments?.[0]?.aircraft
                                            ?.model_name || 'Aircraft TBA'
                                    }}</span
                                >
                            </div>
                            <span
                                class="rounded border border-border bg-background px-2 py-1 font-mono text-xs font-bold"
                                >{{
                                    activeBooking.return_booking.flight
                                        .segments?.[0]?.airline_code
                                }}-{{
                                    activeBooking.return_booking.flight
                                        .segments?.[0]?.flight_number
                                }}</span
                            >
                        </div>

                        <div class="mb-3 flex items-center justify-between">
                            <div class="w-1/3 text-left">
                                <span
                                    class="block text-2xl font-black text-foreground"
                                    >{{
                                        activeBooking.return_booking.flight
                                            .origin_airport
                                    }}</span
                                >
                                <span
                                    class="mb-1 block truncate text-[10px] font-bold text-muted-foreground uppercase"
                                    >{{
                                        getCityName(
                                            activeBooking.return_booking.flight
                                                .origin_airport,
                                        )
                                    }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-foreground"
                                    >{{
                                        formatTime(
                                            activeBooking.return_booking.flight
                                                .departure_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="flex flex-1 flex-col items-center px-2">
                                <span
                                    v-if="
                                        !activeBooking.return_booking.flight
                                            .stop_count ||
                                        activeBooking.return_booking.flight
                                            .stop_count === 0
                                    "
                                    class="mb-1 text-[10px] font-bold tracking-widest text-emerald-500 uppercase"
                                    >Direct</span
                                >
                                <span
                                    v-else
                                    class="mb-1 text-[10px] font-bold tracking-widest text-amber-500 uppercase"
                                    >{{
                                        activeBooking.return_booking.flight
                                            .stop_count
                                    }}
                                    Stop</span
                                >
                                <div
                                    class="relative flex w-full items-center justify-center"
                                >
                                    <div class="h-[2px] w-full bg-border"></div>
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
                                <span
                                    class="mt-1 text-[10px] font-bold text-muted-foreground"
                                    >{{
                                        calculateDuration(
                                            activeBooking.return_booking.flight
                                                .departure_at,
                                            activeBooking.return_booking.flight
                                                .arrival_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="w-1/3 text-right">
                                <span
                                    class="block text-2xl font-black text-foreground"
                                    >{{
                                        activeBooking.return_booking.flight
                                            .destination_airport
                                    }}</span
                                >
                                <span
                                    class="mb-1 ml-auto block truncate text-[10px] font-bold text-muted-foreground uppercase"
                                    >{{
                                        getCityName(
                                            activeBooking.return_booking.flight
                                                .destination_airport,
                                        )
                                    }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-foreground"
                                    >{{
                                        formatTime(
                                            activeBooking.return_booking.flight
                                                .arrival_at,
                                        )
                                    }}</span
                                >
                            </div>
                        </div>

                        <div
                            v-if="
                                activeBooking.return_booking.flight.stop_count >
                                    0 &&
                                activeBooking.return_booking.flight.segments
                            "
                            class="mt-2 text-center text-[10px] font-medium text-amber-600"
                        >
                            via
                            {{
                                activeBooking.return_booking.flight.segments
                                    .slice(0, -1)
                                    .map(
                                        (t: any) =>
                                            getCityName(
                                                t.destination_airport,
                                            ) || t.destination_airport,
                                    )
                                    .join(', ')
                            }}
                        </div>
                        <div
                            class="mt-3 flex flex-wrap justify-center gap-1.5 border-t border-border/50 pt-3"
                        >
                            <span
                                v-if="
                                    activeBooking.return_booking.flight
                                        .segments?.[0]?.classes?.[0]?.facilities
                                        ?.meal
                                "
                                class="rounded border border-border bg-background px-1.5 py-0.5 text-[9px]"
                                >🍱 Meal</span
                            >
                            <span
                                v-if="
                                    activeBooking.return_booking.flight
                                        .segments?.[0]?.classes?.[0]?.facilities
                                        ?.wifi
                                "
                                class="rounded border border-border bg-background px-1.5 py-0.5 text-[9px]"
                                >📶 WiFi</span
                            >
                            <span
                                v-if="
                                    activeBooking.return_booking.flight
                                        .segments?.[0]?.classes?.[0]?.facilities
                                        ?.entertainment
                                "
                                class="rounded border border-border bg-background px-1.5 py-0.5 text-[9px]"
                                >🎬 Screen</span
                            >
                            <span
                                v-if="
                                    activeBooking.return_booking.flight
                                        .segments?.[0]?.classes?.[0]?.facilities
                                        ?.power_usb
                                "
                                class="rounded border border-border bg-background px-1.5 py-0.5 text-[9px]"
                                >🔌 USB</span
                            >
                        </div>
                    </div>

                    <div class="mb-6 space-y-3">
                        <p
                            class="border-b border-border pb-2 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Passenger Details
                        </p>
                        <div
                            v-for="(pax, idx) in activeBooking.passengers"
                            :key="idx"
                            class="rounded-lg border border-border bg-card p-4 text-sm shadow-sm"
                        >
                            <div class="mb-3 flex items-start justify-between">
                                <div>
                                    <span
                                        class="block font-bold text-foreground"
                                        >{{ pax.title }}. {{ pax.first_name }}
                                        {{ pax.last_name }}</span
                                    >
                                    <span
                                        v-if="pax.passport_number"
                                        class="text-[10px] text-muted-foreground"
                                        >ID: {{ pax.passport_number }}</span
                                    >
                                </div>
                                <div
                                    class="rounded border border-border bg-muted/50 p-2 text-right"
                                >
                                    <span
                                        class="block font-mono text-xs font-bold text-emerald-600"
                                        >🛫
                                        {{
                                            formatSeats(pax.assigned_seats)
                                        }}</span
                                    >
                                    <span
                                        v-if="activeBooking.return_booking"
                                        class="mt-1 block border-t border-border/50 pt-1 font-mono text-xs font-bold text-blue-600"
                                    >
                                        🛬
                                        {{
                                            formatSeats(
                                                activeBooking.return_booking
                                                    .passengers[idx]
                                                    ?.assigned_seats,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="rounded border border-primary/10 bg-primary/5 p-2 text-[10px]"
                            >
                                <span class="mb-1 block font-bold text-primary"
                                    >Baggage Allowance:</span
                                >
                                <div class="flex gap-4">
                                    <span class="text-muted-foreground"
                                        >🎒 Cabin:
                                        <span class="font-bold text-foreground"
                                            >{{
                                                activeBooking.flight
                                                    .segments?.[0]?.classes?.[0]
                                                    ?.cabin_baggage_kg || 7
                                            }}
                                            KG</span
                                        ></span
                                    >
                                    <span class="text-muted-foreground"
                                        >🧳 Checked:
                                        <span class="font-bold text-foreground"
                                            >{{
                                                activeBooking.flight
                                                    .segments?.[0]?.classes?.[0]
                                                    ?.free_baggage_kg || 20
                                            }}
                                            KG</span
                                        ></span
                                    >
                                    <span
                                        v-if="pax.extra_baggage_kg > 0"
                                        class="text-amber-600"
                                        >➕ Extra:
                                        <span class="font-bold"
                                            >{{ pax.extra_baggage_kg }} KG</span
                                        ></span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="-mx-6 mb-6 flex items-center justify-between border-t-2 border-dashed border-border bg-muted/20 px-6 pt-4 pb-2"
                    >
                        <span
                            class="text-sm font-bold text-muted-foreground uppercase"
                            >Total Payment</span
                        >
                        <span class="text-3xl font-black text-primary">
                            {{
                                formatCurrency(
                                    Number(activeBooking.total_amount_usd) +
                                        (activeBooking.return_booking
                                            ? Number(
                                                  activeBooking.return_booking
                                                      .total_amount_usd,
                                              )
                                            : 0),
                                )
                            }}
                        </span>
                    </div>

                    <div v-if="['paid', 'used'].includes(activeBooking.status)">
                        <a :href="`/bookings/${activeBooking.id}/ticket`">
                            <Button
                                class="hover:bg-primary-hover w-full bg-primary text-primary-foreground shadow-md transition-all hover:shadow-lg"
                                size="lg"
                                >Download PDF Ticket</Button
                            >
                        </a>
                    </div>
                    <div v-else-if="activeBooking.status === 'pending'">
                        <a :href="`/bookings/${activeBooking.id}/payment`">
                            <Button
                                class="w-full bg-blue-600 text-white shadow-md hover:bg-blue-700"
                                size="lg"
                                >Pay Securely &rarr;</Button
                            >
                        </a>
                    </div>
                </div>
            </div>
        </transition>
    </AeroLayout>
</template>

<style scoped>
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
