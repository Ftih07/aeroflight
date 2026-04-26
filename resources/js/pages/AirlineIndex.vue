<template>
    <Head title="Airline Directory - AeroFlight" />

    <AeroLayout>
        <section
            class="mx-auto max-w-7xl px-4 pt-20 pb-12 sm:px-6 sm:pt-24 lg:px-8"
        >
            <!-- Header -->
            <div
                class="reveal-up mb-3 text-center"
                :class="{ visible: headerVisible }"
            >
                <span
                    class="rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                >
                    AeroFlight Explorer
                </span>
            </div>
            <h1
                class="reveal-up mb-3 text-center text-2xl font-bold text-foreground sm:text-3xl"
                :class="{ visible: headerVisible }"
                style="transition-delay: 80ms"
            >
                Airline <span class="text-primary">Directory</span>
            </h1>
            <p
                class="reveal-up mx-auto mb-10 max-w-md text-center text-sm text-muted-foreground sm:mb-14 sm:text-base"
                :class="{ visible: headerVisible }"
                style="transition-delay: 160ms"
            >
                Discover the world's leading airlines — their history, global
                hubs, and the advanced aircraft fleets they operate.
            </p>

            <!-- Grid Airline Cards -->
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3"
            >
                <div
                    v-for="(airline, i) in airlines"
                    :key="airline.code"
                    class="reveal-up service-card group relative overflow-hidden rounded-xl border border-border bg-card p-5 sm:p-6"
                    :class="{ visible: headerVisible }"
                    :style="{ transitionDelay: (i + 4) * 80 + 'ms' }"
                >
                    <!-- Glow blob -->
                    <div
                        class="absolute -top-5 -right-5 h-20 w-20 rounded-full bg-primary opacity-10 transition-all duration-500 group-hover:scale-150 group-hover:opacity-20"
                    ></div>

                    <!-- Top bar accent -->
                    <div
                        class="absolute top-0 left-0 h-0.5 w-0 rounded-t-xl bg-primary transition-all duration-500 ease-out group-hover:w-full"
                    ></div>

                    <!-- Airline code badge + name -->
                    <div class="mb-4 flex items-center gap-4">
                        <div class="relative flex-shrink-0">
                            <div
                                class="absolute inset-0 rounded-lg bg-primary opacity-15 blur-md transition-opacity group-hover:opacity-30"
                            ></div>
                            <div
                                class="relative flex h-12 w-12 items-center justify-center rounded-lg border border-border bg-muted text-sm font-extrabold text-foreground"
                            >
                                {{ airline.code }}
                            </div>
                        </div>
                        <div>
                            <h2
                                class="text-sm font-bold text-foreground transition-colors group-hover:text-primary sm:text-base"
                            >
                                {{ airline.name }}
                            </h2>
                            <div
                                class="mt-0.5 inline-flex items-center rounded bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                            >
                                Est. {{ airline.founded_year }}
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <p
                        class="mb-4 line-clamp-3 text-xs text-muted-foreground sm:text-sm"
                    >
                        {{ airline.description }}
                    </p>

                    <!-- Feature list -->
                    <ul class="mb-4 space-y-1.5">
                        <li
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <svg
                                class="h-3.5 w-3.5 flex-shrink-0 text-primary"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ airline.headquarters }}
                        </li>
                    </ul>

                    <!-- Footer -->
                    <div
                        class="flex items-center justify-between border-t border-border/50 pt-4 transition-colors group-hover:border-border"
                    >
                        <div
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <svg
                                class="h-3.5 w-3.5 text-muted-foreground/70"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                            <span
                                class="max-w-[130px] truncate"
                                :title="airline.headquarters"
                                >{{ airline.headquarters }}</span
                            >
                        </div>

                        <Link
                            :href="`/airlines/${airline.code}`"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-primary transition-all hover:gap-2"
                        >
                            View Fleet
                            <svg
                                class="h-3 w-3"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5l7 7-7 7"
                                />
                            </svg>
                        </Link>
                    </div>

                    <!-- BG icon decoration -->
                    <svg
                        class="absolute -right-5 -bottom-5 h-24 w-24 transform text-primary opacity-[0.04] transition-all duration-500 group-hover:scale-110 group-hover:rotate-12 group-hover:opacity-[0.07]"
                        fill="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"
                        />
                    </svg>
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

defineProps({
    airlines: Array,
});

const headerVisible = ref(false);

onMounted(() => {
    setTimeout(() => {
        headerVisible.value = true;
    }, 60);
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
