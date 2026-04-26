<template>
    <Head :title="airline.name + ' - AeroFlight Fleet'" />

    <AeroLayout>
        <section
            class="mx-auto max-w-7xl px-4 pt-20 pb-12 sm:px-6 sm:pt-24 lg:px-8"
        >
            <!-- Back button -->
            <div class="reveal-left mb-8" :class="{ visible: pageVisible }">
                <Link
                    href="/airlines"
                    class="group inline-flex items-center gap-1.5 text-xs font-semibold text-primary transition-all hover:gap-2.5"
                >
                    <svg
                        class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-0.5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        />
                    </svg>
                    Back to Directory
                </Link>
            </div>

            <!-- Airline Hero Card -->
            <div
                class="reveal-up service-card relative mb-10 overflow-hidden rounded-xl border border-border bg-card p-5 sm:p-8"
                :class="{ visible: pageVisible }"
                style="transition-delay: 80ms"
            >
                <!-- Glow -->
                <div
                    class="pointer-events-none absolute -top-16 -right-16 h-48 w-48 rounded-full bg-primary/10 blur-3xl"
                ></div>
                <div
                    class="absolute top-0 left-0 h-0.5 w-full rounded-t-xl bg-primary/20"
                ></div>

                <div
                    class="relative z-10 flex flex-col items-start gap-6 sm:flex-row sm:items-center"
                >
                    <!-- Code badge -->
                    <div class="relative flex-shrink-0">
                        <div
                            class="absolute inset-0 rounded-xl bg-primary opacity-20 blur-md"
                        ></div>
                        <div
                            class="relative flex h-16 w-16 items-center justify-center rounded-xl border border-border bg-gradient-to-br from-muted to-background text-lg font-extrabold text-foreground shadow-sm sm:h-20 sm:w-20 sm:text-xl"
                        >
                            {{ airline.code }}
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="flex-1">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <h1
                                class="text-2xl font-bold text-foreground sm:text-3xl"
                            >
                                {{ airline.name }}
                            </h1>
                            <span
                                class="rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-[10px] font-bold text-primary uppercase"
                            >
                                Official Carrier
                            </span>
                        </div>
                        <p
                            class="mb-5 max-w-3xl text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            {{ airline.description }}
                        </p>

                        <div class="flex flex-wrap gap-3">
                            <div
                                class="flex items-center gap-2.5 rounded-lg border border-border bg-muted/50 px-3 py-2"
                            >
                                <span class="text-base">📅</span>
                                <div>
                                    <p
                                        class="text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Established
                                    </p>
                                    <p
                                        class="text-xs font-bold text-foreground"
                                    >
                                        {{ airline.founded_year }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="flex items-center gap-2.5 rounded-lg border border-border bg-muted/50 px-3 py-2"
                            >
                                <span class="text-base">📍</span>
                                <div>
                                    <p
                                        class="text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Headquarters
                                    </p>
                                    <p
                                        class="text-xs font-bold text-foreground"
                                    >
                                        {{ airline.headquarters }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="flex items-center gap-2.5 rounded-lg border border-border bg-muted/50 px-3 py-2"
                            >
                                <span class="text-base">✈️</span>
                                <div>
                                    <p
                                        class="text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Fleet Size
                                    </p>
                                    <p
                                        class="text-xs font-bold text-foreground"
                                    >
                                        {{ fleets.length }} Aircraft Types
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fleet Section Header -->
            <div
                class="reveal-up mb-3 text-center"
                :class="{ visible: fleetVisible }"
            >
                <span
                    class="rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                >
                    Aircraft Education
                </span>
            </div>
            <h2
                class="reveal-up mb-10 text-center text-2xl font-bold text-foreground sm:text-3xl"
                :class="{ visible: fleetVisible }"
                style="transition-delay: 80ms"
            >
                Operated Fleet
                <span class="text-primary">({{ fleets.length }})</span>
            </h2>

            <!-- Fleet Grid -->
            <div class="grid grid-cols-1 gap-4 sm:gap-5 lg:grid-cols-2">
                <div
                    v-for="(aircraft, index) in fleets"
                    :key="index"
                    class="reveal-up service-card group relative overflow-hidden rounded-xl border border-border bg-card"
                    :class="{ visible: fleetVisible }"
                    :style="{ transitionDelay: (index + 2) * 90 + 'ms' }"
                >
                    <!-- Top accent bar -->
                    <div
                        class="absolute top-0 left-0 h-0.5 w-0 rounded-t-xl bg-primary transition-all duration-500 ease-out group-hover:w-full"
                    ></div>

                    <!-- Header section -->
                    <div
                        class="border-b border-border/50 bg-muted/20 p-5 sm:p-6"
                    >
                        <div class="mb-1 flex items-start justify-between">
                            <span
                                class="text-[10px] font-extrabold tracking-widest text-primary uppercase"
                            >
                                {{ aircraft.manufacturer }}
                            </span>
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-border bg-muted text-xs opacity-50 transition-opacity group-hover:opacity-100"
                            >
                                ✈️
                            </div>
                        </div>
                        <h3
                            class="text-lg font-bold text-foreground transition-colors group-hover:text-primary sm:text-xl"
                        >
                            {{ aircraft.model_name }}
                        </h3>
                    </div>

                    <!-- Body -->
                    <div class="flex flex-col p-5 sm:p-6">
                        <p
                            class="mb-5 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                        >
                            {{ aircraft.description }}
                        </p>

                        <!-- Specs grid -->
                        <div class="mb-5 grid grid-cols-2 gap-x-4 gap-y-4">
                            <!-- Range -->
                            <div class="space-y-1">
                                <p
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Max Range
                                </p>
                                <div class="flex items-end gap-1">
                                    <span
                                        class="text-base font-bold text-foreground"
                                        >{{
                                            aircraft.max_range_km.toLocaleString()
                                        }}</span
                                    >
                                    <span
                                        class="mb-0.5 text-xs text-muted-foreground"
                                        >km</span
                                    >
                                </div>
                                <div
                                    class="h-1 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary transition-all duration-1000"
                                        :style="{
                                            width:
                                                calculateRange(
                                                    aircraft.max_range_km,
                                                ) + '%',
                                        }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Speed -->
                            <div class="space-y-1">
                                <p
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Cruising Speed
                                </p>
                                <div class="flex items-end gap-1">
                                    <span
                                        class="text-base font-bold text-foreground"
                                        >{{ aircraft.cruising_speed_kmh }}</span
                                    >
                                    <span
                                        class="mb-0.5 text-xs text-muted-foreground"
                                        >km/h</span
                                    >
                                </div>
                            </div>

                            <!-- Engine -->
                            <div class="space-y-1">
                                <p
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Engine Type
                                </p>
                                <p
                                    class="truncate text-xs font-bold text-foreground sm:text-sm"
                                    :title="aircraft.engine_type"
                                >
                                    {{ aircraft.engine_type }}
                                </p>
                            </div>

                            <!-- Config -->
                            <div class="space-y-1">
                                <p
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Configuration
                                </p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span
                                        class="rounded border border-border bg-muted px-2 py-0.5 text-[10px] font-bold text-foreground"
                                    >
                                        {{ aircraft.seat_layout.config }}
                                    </span>
                                    <span
                                        class="rounded border border-border bg-muted px-2 py-0.5 text-[10px] font-bold text-foreground"
                                    >
                                        {{ aircraft.seat_layout.rows }} Rows
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Class breakdown -->
                        <div
                            class="mb-5 grid grid-cols-2 gap-3 border-t border-border/50 pt-4"
                        >
                            <div
                                class="rounded-lg border border-border/50 bg-muted/40 p-3 text-center"
                            >
                                <p
                                    class="mb-0.5 text-[9px] font-bold text-muted-foreground uppercase"
                                >
                                    Business
                                </p>
                                <p class="text-sm font-bold text-foreground">
                                    {{
                                        aircraft.seat_layout.business_rows
                                    }}
                                    Rows
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-border/50 bg-muted/40 p-3 text-center"
                            >
                                <p
                                    class="mb-0.5 text-[9px] font-bold text-muted-foreground uppercase"
                                >
                                    First Class
                                </p>
                                <p class="text-sm font-bold text-foreground">
                                    {{
                                        aircraft.seat_layout.first_class_rows >
                                        0
                                            ? aircraft.seat_layout
                                                  .first_class_rows + ' Rows'
                                            : '0 Rows'
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Seat layout preview -->
                        <div class="border-t border-border/50 pt-4">
                            <div class="mb-3 flex items-center justify-between">
                                <p
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Layout Preview
                                </p>
                                <span
                                    class="rounded bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary"
                                >
                                    {{ aircraft.seat_layout.config }}
                                </span>
                            </div>
                            <div
                                class="flex flex-col items-center gap-2 overflow-x-auto rounded-lg border border-border/50 bg-muted/30 p-4"
                            >
                                <div
                                    v-for="row in 2"
                                    :key="'row-' + row"
                                    class="flex items-center gap-3 sm:gap-4"
                                >
                                    <div
                                        v-for="(
                                            groupCount, gIndex
                                        ) in aircraft.seat_layout.config.split(
                                            '-',
                                        )"
                                        :key="'group-' + gIndex"
                                        class="flex gap-1.5"
                                    >
                                        <div
                                            v-for="seat in parseInt(groupCount)"
                                            :key="'seat-' + seat"
                                            class="flex h-7 w-5 flex-col items-center overflow-hidden rounded border border-border bg-card shadow-sm"
                                        >
                                            <div
                                                class="mt-1 h-1.5 w-3.5 rounded-full bg-muted-foreground/20"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                                <p
                                    class="mt-1 text-[9px] text-muted-foreground italic"
                                >
                                    Economy class layout shown
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AeroLayout>
</template>

<script>
export default {
    layout: null,
};
</script>

<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AeroLayout from '@/layouts/AeroLayout.vue';

const props = defineProps({
    airline: Object,
    fleets: Array,
});

const calculateRange = (km) => Math.min((km / 16000) * 100, 100);

const pageVisible = ref(false);
const fleetVisible = ref(false);

onMounted(() => {
    setTimeout(() => {
        pageVisible.value = true;
    }, 60);
    setTimeout(() => {
        fleetVisible.value = true;
    }, 300);
});
</script>

<style scoped>
.reveal-up {
    opacity: 0;
    transform: translateY(28px);
    transition:
        opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.reveal-up.visible {
    opacity: 1;
    transform: translateY(0);
}

.reveal-left {
    opacity: 0;
    transform: translateX(-36px);
    transition:
        opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
}
.reveal-left.visible {
    opacity: 1;
    transform: translateX(0);
}

.service-card {
    transition:
        transform 0.28s cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 0.28s ease,
        border-color 0.28s ease;
}
.service-card:hover {
    transform: translateY(-5px);
    border-color: hsl(var(--primary) / 0.35);
    box-shadow: 0 12px 32px -8px hsl(var(--primary) / 0.12);
}
</style>
