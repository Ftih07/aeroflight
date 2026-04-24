<template>
    <Head title="Welcome to AeroFlight" />

    <AeroLayout>
        <section
            id="home"
            class="mx-auto max-w-7xl px-4 pt-20 pb-8 sm:px-6 sm:pt-24 lg:px-8"
        >
            <div
                class="flex flex-col items-center gap-6 lg:flex-row lg:gap-12 lg:pt-6"
            >
                <div
                    class="reveal-left w-full flex-1 text-center lg:text-left"
                    :class="{ visible: heroVisible }"
                >
                    <div
                        class="mb-4 inline-flex items-center gap-2 rounded-full border border-border bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                    >
                        <span class="relative flex h-1.5 w-1.5">
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-1.5 w-1.5 rounded-full bg-primary"
                            ></span>
                        </span>
                        Indonesia's #1 Flight Management Platform
                    </div>
                    <h1
                        class="mb-4 text-3xl font-bold tracking-tight text-foreground sm:text-4xl lg:text-5xl xl:text-6xl"
                    >
                        Fly Comfortably,<br />
                        <span class="text-primary">Hassle-Free.</span>
                    </h1>
                    <p
                        class="mx-auto mb-6 max-w-lg text-sm text-muted-foreground sm:text-base lg:mx-0"
                    >
                        Book commercial flights, ship air cargo, and charter
                        private jets — all in one seamless platform.
                    </p>

                    <div
                        class="rounded-xl border border-border bg-card p-4 text-left shadow-sm"
                    >
                        <form @submit.prevent="submitHomeSearch">
                            <div class="mb-3 flex flex-wrap gap-1.5">
                                <button
                                    type="button"
                                    v-for="type in tripTypes"
                                    :key="type.value"
                                    @click="tripType = type.value"
                                    :class="
                                        tripType === type.value
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'
                                    "
                                    class="rounded-md px-3 py-1 text-xs font-semibold transition-all"
                                >
                                    {{ type.label }}
                                </button>
                            </div>

                            <div
                                class="grid grid-cols-1 gap-2.5"
                                :class="
                                    tripType === 'round_trip'
                                        ? 'sm:grid-cols-4'
                                        : 'sm:grid-cols-3'
                                "
                            >
                                <div class="relative">
                                    <label
                                        class="mb-1 block text-xs font-semibold text-muted-foreground"
                                        >From</label
                                    >
                                    <input
                                        type="text"
                                        v-model="displayOrigin"
                                        @input="searchAirport('origin')"
                                        @focus="searchAirport('origin')"
                                        @blur="hideDropdowns"
                                        :disabled="isAirportsLoading"
                                        placeholder="Jakarta (CGK)"
                                        autocomplete="off"
                                        class="aero-input w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground uppercase placeholder:text-muted-foreground placeholder:normal-case disabled:opacity-50"
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
                                            class="flex cursor-pointer items-center justify-between border-b border-border/40 px-3 py-2 transition-colors hover:bg-muted/50"
                                        >
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-xs font-bold text-foreground"
                                                    >{{ airport.city }}</span
                                                >
                                                <span
                                                    class="text-[9px] text-muted-foreground"
                                                    >{{ airport.name }}</span
                                                >
                                            </div>
                                            <span
                                                class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary"
                                                >{{ airport.code }}</span
                                            >
                                        </li>
                                    </ul>
                                </div>

                                <div class="relative">
                                    <label
                                        class="mb-1 block text-xs font-semibold text-muted-foreground"
                                        >To</label
                                    >
                                    <input
                                        type="text"
                                        v-model="displayDestination"
                                        @input="searchAirport('destination')"
                                        @focus="searchAirport('destination')"
                                        @blur="hideDropdowns"
                                        :disabled="isLoading"
                                        placeholder="Bali (DPS)"
                                        autocomplete="off"
                                        class="aero-input w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground uppercase placeholder:text-muted-foreground placeholder:normal-case disabled:opacity-50"
                                    />

                                    <ul
                                        v-if="destinationResults.length > 0"
                                        class="absolute left-0 z-[9999] mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-border bg-card shadow-2xl"
                                    >
                                        <li
                                            v-for="airport in destinationResults"
                                            :key="airport.code"
                                            @mousedown.prevent="
                                                selectAirport(
                                                    'destination',
                                                    airport,
                                                )
                                            "
                                            class="flex cursor-pointer items-center justify-between border-b border-border/40 px-3 py-2 transition-colors hover:bg-muted/50"
                                        >
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-xs font-bold text-foreground"
                                                    >{{ airport.city }}</span
                                                >
                                                <span
                                                    class="text-[9px] text-muted-foreground"
                                                    >{{ airport.name }}</span
                                                >
                                            </div>
                                            <span
                                                class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary"
                                                >{{ airport.code }}</span
                                            >
                                        </li>
                                    </ul>
                                </div>

                                <div>
                                    <label
                                        class="mb-1 block text-xs font-semibold text-muted-foreground"
                                        >Depart</label
                                    >
                                    <input
                                        type="date"
                                        v-model="searchForm.date"
                                        class="aero-input w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground"
                                    />
                                </div>

                                <div v-if="tripType === 'round_trip'">
                                    <label
                                        class="mb-1 block text-xs font-semibold text-muted-foreground"
                                        >Return</label
                                    >
                                    <input
                                        type="date"
                                        v-model="searchForm.returnDate"
                                        :min="searchForm.date"
                                        class="aero-input w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground"
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="hover:bg-primary-hover mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition"
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
                                Search Flights
                            </button>
                        </form>

                        <div
                            v-if="recentSearches.length > 0"
                            class="mt-4 border-t border-border/50 pt-3"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <p
                                    class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Recent Searches:
                                </p>
                                <button
                                    type="button"
                                    @click="clearRecentSearches"
                                    class="text-[10px] font-bold text-destructive transition-colors hover:text-red-700 hover:underline"
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
                                        <span class="font-bold">{{
                                            item.origin
                                        }}</span>
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

                                        <span>{{
                                            item.formattedTripType
                                        }}</span>

                                        <span v-if="item.formattedReturnDate">
                                            • Return:
                                            {{ item.formattedReturnDate }}
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="reveal-right w-full flex-1"
                    :class="{ visible: heroVisible }"
                >
                    <div
                        class="relative h-56 w-full sm:h-72 md:h-80 lg:h-[440px]"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 rounded-2xl bg-primary/5"
                        ></div>
                        <div
                            class="absolute top-2 right-0 left-0 z-20 flex justify-center gap-1 px-2 sm:top-3 sm:gap-2"
                        >
                            <button
                                v-for="(config, key) in planesConfig"
                                :key="key"
                                @click="changePlane(key)"
                                :class="
                                    activePlane === key
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'bg-card/90 text-muted-foreground hover:bg-accent'
                                "
                                class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold backdrop-blur transition-all sm:px-3 sm:py-1.5"
                            >
                                {{ config.label }}
                            </button>
                        </div>

                        <div
                            ref="canvasContainer"
                            class="relative z-10 h-full w-full cursor-grab overflow-hidden rounded-2xl active:cursor-grabbing"
                        ></div>

                        <div
                            v-if="isLoading"
                            class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-card/60 backdrop-blur-sm"
                        >
                            <div class="flex flex-col items-center gap-2">
                                <div
                                    class="h-5 w-5 animate-spin rounded-full border-2 border-border border-t-primary"
                                ></div>
                                <span class="text-xs text-muted-foreground"
                                    >Loading model...</span
                                >
                            </div>
                        </div>

                        <transition name="fade-slide">
                            <div
                                v-if="!isLoading && currentPlaneInfo"
                                class="absolute bottom-3 left-1/2 z-30 -translate-x-1/2 rounded-lg border border-border bg-card/95 px-3 py-1.5 whitespace-nowrap shadow-lg backdrop-blur"
                            >
                                <p
                                    class="text-center text-xs font-bold text-foreground"
                                >
                                    {{ currentPlaneInfo.name }}
                                </p>
                                <p
                                    class="text-center text-xs text-muted-foreground"
                                >
                                    {{ currentPlaneInfo.desc }}
                                </p>
                            </div>
                        </transition>
                    </div>
                </div>
            </div>
        </section>

        <div
            class="w-full overflow-hidden border-y border-border bg-muted/40 py-3 sm:py-4"
        >
            <div class="animate-marquee flex whitespace-nowrap">
                <div
                    class="flex items-center gap-10 px-8 text-xs font-medium text-muted-foreground sm:gap-14 sm:text-sm"
                    v-for="i in 2"
                    :key="i"
                >
                    <span>✈️ Popular: Jakarta</span><span>•</span
                    ><span>Bali</span><span>•</span> <span>Surabaya</span
                    ><span>•</span><span>Singapore</span><span>•</span
                    ><span>Kuala Lumpur</span><span>•</span><span>Tokyo</span
                    ><span>•</span> <span>Sydney</span><span>•</span
                    ><span>Dubai</span><span>•</span>
                </div>
            </div>
        </div>

        <section
            ref="statsSection"
            class="border-b border-border bg-card py-10 sm:py-14"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                    <div
                        v-for="(stat, i) in stats"
                        :key="i"
                        class="reveal-up text-center"
                        :class="{ visible: statsVisible }"
                        :style="{ transitionDelay: i * 75 + 'ms' }"
                    >
                        <div
                            class="mb-1 text-2xl font-extrabold text-primary sm:text-3xl"
                        >
                            {{ statsVisible ? stat.display : '—' }}
                        </div>
                        <div class="text-xs text-muted-foreground sm:text-sm">
                            {{ stat.label }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="services"
            ref="layananSection"
            class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-20 lg:px-8"
        >
            <div
                class="reveal-up mb-3 text-center"
                :class="{ visible: layananVisible }"
            >
                <span
                    class="rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                    >Our Services</span
                >
            </div>
            <h2
                class="reveal-up mb-3 text-center text-2xl font-bold text-foreground sm:text-3xl"
                :class="{ visible: layananVisible }"
                style="transition-delay: 80ms"
            >
                Everything You Need to Fly
            </h2>
            <p
                class="reveal-up mx-auto mb-10 max-w-md text-center text-sm text-muted-foreground sm:mb-14 sm:text-base"
                :class="{ visible: layananVisible }"
                style="transition-delay: 160ms"
            >
                From daily commercial tickets to logistics and exclusive charter
                — one integrated platform.
            </p>
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3"
            >
                <div
                    v-for="(service, i) in services"
                    :key="i"
                    class="reveal-up service-card group relative overflow-hidden rounded-xl border border-border bg-card p-5 sm:p-6"
                    :class="{ visible: layananVisible }"
                    :style="{ transitionDelay: (i + 4) * 80 + 'ms' }"
                >
                    <div
                        class="absolute -top-5 -right-5 h-20 w-20 rounded-full opacity-10 transition-all duration-500 group-hover:scale-150 group-hover:opacity-20"
                        :class="service.glow"
                    ></div>
                    <div
                        class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg border border-border bg-muted text-lg"
                    >
                        {{ service.icon }}
                    </div>
                    <h3
                        class="mb-2 text-sm font-bold text-foreground sm:text-base"
                    >
                        {{ service.title }}
                    </h3>
                    <p class="mb-3 text-xs text-muted-foreground sm:text-sm">
                        {{ service.desc }}
                    </p>
                    <ul class="space-y-1.5">
                        <li
                            v-for="feat in service.features"
                            :key="feat"
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
                            {{ feat }}
                        </li>
                    </ul>
                    <a
                        href="#"
                        class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-primary transition-all hover:gap-2"
                        >Learn More
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
                            /></svg
                    ></a>
                </div>
            </div>
        </section>

        <section
            ref="howSection"
            class="border-t border-border bg-muted/30 py-12 sm:py-20"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="reveal-up mb-3 text-center"
                    :class="{ visible: howVisible }"
                >
                    <span
                        class="rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold text-muted-foreground"
                        >How It Works</span
                    >
                </div>
                <h2
                    class="reveal-up mb-10 text-center text-2xl font-bold text-foreground sm:mb-14 sm:text-3xl"
                    :class="{ visible: howVisible }"
                    style="transition-delay: 80ms"
                >
                    Book in 3 Simple Steps
                </h2>
                <div class="grid grid-cols-1 gap-8 sm:grid-cols-3">
                    <div
                        v-for="(step, i) in steps"
                        :key="i"
                        class="reveal-up text-center"
                        :class="{ visible: howVisible }"
                        :style="{ transitionDelay: (i + 2) * 100 + 'ms' }"
                    >
                        <div
                            class="relative mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-primary text-2xl text-primary-foreground shadow-sm"
                        >
                            {{ step.icon }}
                            <span
                                class="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full border border-border bg-card text-xs font-extrabold text-foreground shadow-sm"
                                >{{ i + 1 }}</span
                            >
                        </div>
                        <h3
                            class="mb-2 text-sm font-bold text-foreground sm:text-base"
                        >
                            {{ step.title }}
                        </h3>
                        <p class="text-xs text-muted-foreground sm:text-sm">
                            {{ step.desc }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="routes"
            ref="routeSection"
            class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-20 lg:px-8"
        >
            <div
                class="reveal-up mb-3 text-center"
                :class="{ visible: routeVisible }"
            >
                <span
                    class="rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                    >Destinations</span
                >
            </div>
            <h2
                class="reveal-up mb-10 text-center text-2xl font-bold text-foreground sm:mb-14 sm:text-3xl"
                :class="{ visible: routeVisible }"
                style="transition-delay: 80ms"
            >
                Popular Routes This Week
            </h2>
            <div
                class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4"
            >
                <div
                    v-for="(route, i) in popularRoutes"
                    :key="i"
                    class="reveal-up group cursor-pointer overflow-hidden rounded-xl border border-border bg-card shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md"
                    :class="{ visible: routeVisible }"
                    :style="{ transitionDelay: (i + 2) * 70 + 'ms' }"
                >
                    <div class="relative h-24 overflow-hidden sm:h-32">
                        <div
                            :class="route.gradient"
                            class="flex h-full w-full items-center justify-center transition-transform duration-500 group-hover:scale-110"
                        >
                            <span class="text-3xl sm:text-5xl">{{
                                route.emoji
                            }}</span>
                        </div>
                        <div
                            class="absolute bottom-2 left-2 rounded-md bg-card/90 px-1.5 py-0.5 text-xs font-semibold text-foreground backdrop-blur"
                        >
                            {{ route.duration }}
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p
                                    class="truncate text-xs font-bold text-foreground sm:text-sm"
                                >
                                    {{ route.from }} → {{ route.to }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ route.airline }}
                                </p>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p
                                    class="text-xs font-extrabold text-primary sm:text-sm"
                                >
                                    {{ route.price }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    /person
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="testimonials"
            ref="testiSection"
            class="border-t border-border bg-muted/30 py-12 sm:py-20"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="reveal-up mb-3 text-center"
                    :class="{ visible: testiVisible }"
                >
                    <span
                        class="rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold text-muted-foreground"
                        >Testimonials</span
                    >
                </div>
                <h2
                    class="reveal-up mb-10 text-center text-2xl font-bold text-foreground sm:mb-14 sm:text-3xl"
                    :class="{ visible: testiVisible }"
                    style="transition-delay: 80ms"
                >
                    What Our Customers Say
                </h2>
                <div
                    class="reveal-up"
                    :class="{ visible: testiVisible }"
                    style="transition-delay: 160ms"
                >
                    <div class="relative overflow-hidden">
                        <div
                            class="flex transition-transform duration-500 ease-out"
                            :style="{
                                transform: `translateX(-${currentSlide * 100}%)`,
                            }"
                        >
                            <div
                                v-for="(group, gi) in testimonialGroups"
                                :key="gi"
                                class="flex w-full flex-shrink-0 flex-col gap-3 sm:flex-row sm:gap-4"
                            >
                                <div
                                    v-for="(testi, ti) in group"
                                    :key="ti"
                                    class="flex-1 rounded-xl border border-border bg-card p-4 sm:p-5"
                                >
                                    <div class="mb-3 flex gap-0.5">
                                        <svg
                                            v-for="s in 5"
                                            :key="s"
                                            class="h-3.5 w-3.5 text-yellow-500"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                            />
                                        </svg>
                                    </div>
                                    <p
                                        class="mb-4 text-xs text-muted-foreground italic sm:text-sm"
                                    >
                                        "{{ testi.text }}"
                                    </p>
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white"
                                            :class="testi.avatarBg"
                                        >
                                            {{ testi.name[0] }}
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs font-semibold text-foreground sm:text-sm"
                                            >
                                                {{ testi.name }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ testi.role }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex items-center justify-center gap-3">
                        <button
                            @click="prevSlide"
                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground shadow-sm transition hover:border-primary hover:bg-primary hover:text-primary-foreground"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                        </button>
                        <div class="flex gap-1.5">
                            <button
                                v-for="(_, i) in testimonialGroups"
                                :key="i"
                                @click="currentSlide = i"
                                :class="
                                    currentSlide === i
                                        ? 'w-5 bg-primary'
                                        : 'w-1.5 bg-border'
                                "
                                class="h-1.5 rounded-full transition-all duration-300"
                            ></button>
                        </div>
                        <button
                            @click="nextSlide"
                            class="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground shadow-sm transition hover:border-primary hover:bg-primary hover:text-primary-foreground"
                        >
                            <svg
                                class="h-3.5 w-3.5"
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
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="about"
            ref="tentangSection"
            class="border-t border-border bg-card py-12 sm:py-20"
        >
            <div
                class="mx-auto grid max-w-7xl grid-cols-1 gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8"
            >
                <div class="reveal-left" :class="{ visible: tentangVisible }">
                    <span
                        class="mb-3 inline-block rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                        >About Us</span
                    >
                    <h2
                        class="mb-4 text-2xl font-bold text-foreground sm:text-3xl"
                    >
                        Redefining Air Travel Management
                    </h2>
                    <p
                        class="mb-4 text-sm leading-relaxed text-muted-foreground sm:text-base"
                    >
                        AeroFlight is built to redefine the flight management
                        experience. We believe technology should simplify your
                        mobility, not complicate it.
                    </p>
                    <p
                        class="mb-8 text-sm leading-relaxed text-muted-foreground sm:text-base"
                    >
                        Backed by a modern system architecture, AeroFlight
                        ensures every commercial booking, cargo shipment, or
                        private charter is handled with precision and
                        enterprise-grade data security.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="(val, i) in values"
                            :key="i"
                            class="rounded-lg border border-border bg-muted/50 p-3 sm:p-4"
                        >
                            <div class="mb-1.5 text-lg sm:text-xl">
                                {{ val.icon }}
                            </div>
                            <p
                                class="text-xs font-semibold text-foreground sm:text-sm"
                            >
                                {{ val.title }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ val.desc }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    id="faq"
                    class="reveal-right"
                    :class="{ visible: tentangVisible }"
                >
                    <span
                        class="mb-3 inline-block rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                        >FAQ</span
                    >
                    <h2
                        class="mb-6 text-2xl font-bold text-foreground sm:text-3xl"
                    >
                        Frequently Asked Questions
                    </h2>
                    <div class="space-y-2">
                        <div
                            v-for="(faq, i) in faqs"
                            :key="i"
                            class="overflow-hidden rounded-lg border border-border bg-muted/40 transition-colors"
                            :class="{
                                'border-primary/30 bg-primary/5': openFaq === i,
                            }"
                        >
                            <button
                                @click="openFaq = openFaq === i ? null : i"
                                class="flex w-full items-center justify-between px-4 py-3 text-left text-xs font-semibold text-foreground sm:text-sm"
                            >
                                {{ faq.q }}
                                <svg
                                    class="h-4 w-4 flex-shrink-0 text-muted-foreground transition-transform duration-300"
                                    :class="{
                                        'rotate-180 text-primary':
                                            openFaq === i,
                                    }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>
                            <div
                                class="overflow-hidden transition-all duration-300"
                                :style="
                                    openFaq === i
                                        ? 'max-height:200px;opacity:1'
                                        : 'max-height:0;opacity:0'
                                "
                            >
                                <p
                                    class="px-4 pb-4 text-xs text-muted-foreground sm:text-sm"
                                >
                                    {{ faq.a }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            ref="ctaSection"
            class="border-t border-border bg-muted/30 py-12 sm:py-20"
        >
            <div class="mx-auto max-w-4xl px-4 sm:px-6">
                <div
                    class="reveal-up relative overflow-hidden rounded-2xl border border-border bg-card p-8 text-center shadow-sm sm:p-12"
                    :class="{ visible: ctaVisible }"
                >
                    <div
                        class="pointer-events-none absolute -top-16 left-1/2 h-32 w-64 -translate-x-1/2 rounded-full bg-primary/15 blur-3xl"
                    ></div>
                    <div class="relative z-10">
                        <div class="mb-3 text-4xl">✈️</div>
                        <h2
                            class="mb-3 text-2xl font-bold text-foreground sm:text-3xl"
                        >
                            Ready to Fly with AeroFlight?
                        </h2>
                        <p
                            class="mx-auto mb-7 max-w-md text-sm text-muted-foreground sm:text-base"
                        >
                            Sign up today and experience seamless flight
                            booking, cargo shipping, and charter — all in one
                            platform.
                        </p>
                        <div
                            class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center"
                        >
                            <a
                                href="/register"
                                class="hover:bg-primary-hover w-full rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition sm:w-auto"
                                >Get Started for Free</a
                            >
                            <a
                                href="#services"
                                class="w-full rounded-lg border border-border bg-muted px-6 py-2.5 text-sm font-semibold text-foreground transition hover:bg-accent sm:w-auto"
                                >View All Services</a
                            >
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AeroLayout>
</template>

<!-- eslint-disable vue/block-lang -->
<script>
// MATIKAN LAYOUT GLOBAL BAWAAN BREEZE
export default {
    layout: null,
};
</script>

<!-- eslint-disable vue/block-lang -->
<script setup>
/* eslint-disable import/order */
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AeroLayout from '@/layouts/AeroLayout.vue';
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';

// ── Search ────────────────────────────────────────────
const tripTypes = [
    { value: 'one_way', label: 'One-Way' },
    { value: 'round_trip', label: 'Round-Trip' },
];
const tripType = ref('one_way');

// --- LOGIC HAPUS RECENT SEARCH ---
const clearRecentSearches = () => {
    recentSearches.value = []; // Kosongkan state Vue
    localStorage.removeItem('aero_recent_searches'); // Hapus dari browser storage
};

const displayOrigin = ref('');
const displayDestination = ref('');
const isAirportsLoading = ref(true); // Pakai nama baru biar gak bentrok sama Three.js

const searchForm = ref({
    origin: '',
    destination: '',
    date: '',
    returnDate: '',
});

// State Recent Search ────────────────────────────────────────────
const recentSearches = ref([]);

const originResults = ref([]);
const destinationResults = ref([]);
const allAirports = ref([]);

onMounted(() => {
    // Load Recent Searches dari LocalStorage
    const savedSearches = localStorage.getItem('aero_recent_searches');

    if (savedSearches) {
        recentSearches.value = JSON.parse(savedSearches);
    }
});

const applyRecentSearch = (item) => {
    displayOrigin.value = item.origin;
    displayDestination.value = item.destination;

    tripType.value = item.trip_type;
    searchForm.value.date = item.date;
    searchForm.value.returnDate = item.return_date || '';
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

// --- 2. FETCH DATA BANDARA & LOAD LOCALSTORAGE ---
onMounted(async () => {
    // A. Load Recent Searches
    const savedSearches = localStorage.getItem('aero_recent_searches');

    if (savedSearches) {
        recentSearches.value = JSON.parse(savedSearches);
    }

    // B. Fetch Data Bandara untuk Autocomplete
    try {
        isAirportsLoading.value = true; // <-- Update ke nama baru
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
        console.error('Gagal load bandara', error);
    } finally {
        isAirportsLoading.value = false; // <-- Update ke nama baru
    }
});

// --- 3. LOGIC AUTOCOMPLETE BANDARA ---
const searchAirport = (type) => {
    if (isAirportsLoading.value) {
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

// --- 4. SUBMIT FORM & RECENT SEARCH LOGIC ---
const extractCleanData = (text) => {
    if (text.includes('(') && text.includes(')')) {
        return text.split('(')[1].replace(')', '').trim();
    }

    return text.trim();
};

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

const submitHomeSearch = () => {
    const cleanOrigin = extractCleanData(displayOrigin.value);
    const cleanDestination = extractCleanData(displayDestination.value);

    // Bawa ke halaman search kosong kalau ngga diisi rutenya
    if (!cleanOrigin && !cleanDestination && !searchForm.value.date) {
        router.get('/flights');
        {
            return [];
        }
    }

    const searchParams = {
        origin: cleanOrigin,
        destination: cleanDestination,
        date: searchForm.value.date,
        trip_type: tripType.value,
        return_date:
            tripType.value === 'round_trip' ? searchForm.value.returnDate : '',
    };

    if (searchParams.origin && searchParams.destination) {
        saveToRecent(searchParams);
    }

    router.get('/flights', searchParams);
};

// ── Three.js ──────────────────────────────────────────
const isLoading = ref(false);
const activePlane = ref('commercial');
const canvasContainer = ref(null);

const planesConfig = {
    commercial: {
        label: 'Commercial',
        path: '/models/airbus_a380-800/scene.gltf',
        scale: [0.15, 0.15, 0.15],
        name: 'Airbus A380-800',
        desc: 'Long-haul commercial flights',
    },
    cargo: {
        label: 'Air Cargo',
        path: '/models/antonov_an-225/scene.gltf',
        scale: [0.03, 0.03, 0.03],
        name: 'Antonov An-225',
        desc: 'Heavy cargo logistics',
    },
    charter: {
        label: 'Charter',
        path: '/models/miljet/scene.gltf',
        scale: [15, 15, 15],
        name: 'Private Jet',
        desc: 'Exclusive VIP charter',
    },
};

const currentPlaneInfo = computed(
    () => planesConfig[activePlane.value] ?? null,
);

let scene, camera, renderer, airplaneModel, controls, animationFrameId;
const loader = new GLTFLoader();

const changePlane = (key) => {
    if (activePlane.value === key && airplaneModel) {
        return [];
    }

    activePlane.value = key;
    isLoading.value = true;

    if (airplaneModel) {
        scene.remove(airplaneModel);
        airplaneModel = null;
    }

    loader.load(
        planesConfig[key].path,
        (gltf) => {
            airplaneModel = gltf.scene;
            airplaneModel.scale.set(...planesConfig[key].scale);
            airplaneModel.position.set(0, -1, 0);
            scene.add(airplaneModel);
            isLoading.value = false;
        },
        undefined,
        () => {
            isLoading.value = false;
        },
    );
};

const initThreeJS = () => {
    const container = canvasContainer.value;

    if (!container) {
        return [];
    }

    const w = container.clientWidth,
        h = container.clientHeight;
    scene = new THREE.Scene();
    scene.background = null;
    camera = new THREE.PerspectiveCamera(45, w / h, 0.1, 1000);
    camera.position.set(5, 3, 10);
    renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(w, h);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    container.appendChild(renderer.domElement);
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableZoom = false;
    controls.autoRotate = true;
    controls.autoRotateSpeed = 1.8;
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    scene.add(new THREE.AmbientLight(0xffffff, 0.7));
    const d = new THREE.DirectionalLight(0xffffff, 2);
    d.position.set(10, 10, 10);
    scene.add(d);
    const f = new THREE.DirectionalLight(0xffffff, 0.8);
    f.position.set(-10, 0, -10);
    scene.add(f);
    changePlane('commercial');
    const animate = () => {
        animationFrameId = requestAnimationFrame(animate);

        if (airplaneModel) {
            airplaneModel.position.y = -1 + Math.sin(Date.now() * 0.001) * 0.25;
        }

        controls.update();
        renderer.render(scene, camera);
    };

    animate();
};

const onWindowResize = () => {
    if (!canvasContainer.value || !camera || !renderer) {
        return [];
    }

    const w = canvasContainer.value.clientWidth,
        h = canvasContainer.value.clientHeight;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
};

// ── Data ──────────────────────────────────────────────
const stats = [
    { display: '2.4M+', label: 'Passengers Served' },
    { display: '150+', label: 'Flight Routes' },
    { display: '98.7%', label: 'Satisfaction Rate' },
    { display: '24/7', label: 'Customer Support' },
];

const services = [
    {
        icon: '✈️',
        title: 'Commercial Flights',
        glow: 'bg-blue-500',
        desc: 'Book easily for domestic and international routes. No queues, transparent pricing.',
        features: [
            '100+ airline partners',
            'Best price guarantee',
            'Easy refund & reschedule',
            'Instant e-ticket',
        ],
    },
    {
        icon: '📦',
        title: 'Air Cargo Shipping',
        glow: 'bg-orange-500',
        desc: 'Fast logistics for documents to heavy freight. Track shipments in real-time from your dashboard.',
        features: [
            'Real-time tracking',
            'Shipment insurance',
            'Door-to-door pickup',
            'Express & standard',
        ],
    },
    {
        icon: '🛩️',
        title: 'Charter Aircraft',
        glow: 'bg-purple-500',
        desc: 'From small propeller planes to large jets. Enjoy privacy, flexible schedules, and VIP comfort.',
        features: [
            'Flexible scheduling',
            'Premium fleet',
            'VIP concierge service',
            'Custom routes',
        ],
    },
];

const steps = [
    {
        icon: '🔍',
        title: 'Search & Select',
        desc: 'Enter your route and date, then choose the best flight or service for your needs.',
    },
    {
        icon: '💳',
        title: 'Secure Payment',
        desc: 'Pay with multiple methods. All transactions use enterprise-grade encryption.',
    },
    {
        icon: '🎫',
        title: 'Fly & Track',
        desc: 'Receive instant e-tickets and track your flight or cargo from your dashboard.',
    },
];

const popularRoutes = [
    {
        from: 'Jakarta',
        to: 'Bali',
        airline: 'Garuda Indonesia',
        price: '$55',
        duration: '1h 45m',
        gradient: 'bg-gradient-to-br from-sky-400 to-cyan-300',
        emoji: '🏝️',
    },
    {
        from: 'Jakarta',
        to: 'Surabaya',
        airline: 'Lion Air',
        price: '$29',
        duration: '1h 20m',
        gradient: 'bg-gradient-to-br from-orange-300 to-rose-300',
        emoji: '🌆',
    },
    {
        from: 'Bali',
        to: 'Singapore',
        airline: 'Singapore Air',
        price: '$138',
        duration: '2h 30m',
        gradient: 'bg-gradient-to-br from-emerald-300 to-teal-400',
        emoji: '🦁',
    },
    {
        from: 'Jakarta',
        to: 'Kuala Lumpur',
        airline: 'AirAsia',
        price: '$92',
        duration: '2h 00m',
        gradient: 'bg-gradient-to-br from-purple-300 to-indigo-400',
        emoji: '🗼',
    },
];

const testimonials = [
    {
        text: 'Super fast booking — my e-ticket arrived instantly. No more queuing at the airport!',
        name: 'Rania P.',
        role: 'Travel Blogger, Jakarta',
        avatarBg: 'bg-blue-500',
    },
    {
        text: "AeroFlight's cargo service is invaluable for my business. Real-time tracking brings peace of mind.",
        name: 'Budi S.',
        role: 'Entrepreneur, Surabaya',
        avatarBg: 'bg-emerald-500',
    },
    {
        text: 'Chartered a jet to Singapore for business — the VIP service was absolutely outstanding.',
        name: 'Dewi L.',
        role: 'Marketing Director, Bandung',
        avatarBg: 'bg-purple-500',
    },
    {
        text: 'Competitive prices and a very intuitive interface. Highly recommended for everyone!',
        name: 'Ahmad F.',
        role: 'Student, Medan',
        avatarBg: 'bg-rose-500',
    },
    {
        text: 'Refund was processed quickly when a flight was cancelled. 24/7 support is very responsive.',
        name: 'Siti R.',
        role: 'Teacher, Yogyakarta',
        avatarBg: 'bg-amber-500',
    },
    {
        text: 'The multi-city feature is a game changer for island-hopping. All in one app!',
        name: 'Hendra K.',
        role: 'Photographer, Bali',
        avatarBg: 'bg-cyan-500',
    },
];

const testimonialGroups = computed(() => {
    const g = [];

    for (let i = 0; i < testimonials.length; i += 3) {
        g.push(testimonials.slice(i, i + 3));
    }

    return g;
});

const currentSlide = ref(0);
const nextSlide = () => {
    currentSlide.value =
        (currentSlide.value + 1) % testimonialGroups.value.length;
};
const prevSlide = () => {
    currentSlide.value =
        (currentSlide.value - 1 + testimonialGroups.value.length) %
        testimonialGroups.value.length;
};

const openFaq = ref(null);
const faqs = [
    {
        q: 'How do I charter an aircraft?',
        a: 'Contact our VIP support team via the "Charter Aircraft" menu. Specify aircraft type, route, and schedule to receive a cost estimate within 24 hours.',
    },
    {
        q: 'Can my cargo be tracked in real-time?',
        a: 'Yes. All Aero Cargo Airway Bills integrate with our real-time tracking system. Enter your receipt number on the cargo search page to see the latest status.',
    },
    {
        q: 'What payment methods are accepted?',
        a: 'We accept bank transfers, credit/debit cards (Visa, Mastercard), e-wallets, virtual accounts, and QRIS.',
    },
    {
        q: 'What is the refund policy if a flight is cancelled?',
        a: "Airline cancellations receive a full refund within 3–7 business days. Passenger-initiated cancellations follow each airline's policy.",
    },
    {
        q: 'Are there hidden fees when booking?',
        a: 'No. All costs including taxes and service fees are displayed transparently before you complete payment.',
    },
];

const values = [
    {
        icon: '🔒',
        title: 'Data Security',
        desc: 'End-to-end encryption on every transaction',
    },
    {
        icon: '⚡',
        title: 'Fast Booking',
        desc: 'Ticket confirmation in seconds',
    },
    {
        icon: '🌐',
        title: 'Wide Network',
        desc: '150+ domestic & international routes',
    },
    {
        icon: '🎯',
        title: 'Best Price',
        desc: 'Best price guarantee or full refund',
    },
];

// ── Reveal ────────────────────────────────────────────
const heroVisible = ref(false);
const statsSection = ref(null);
const statsVisible = ref(false);
const layananSection = ref(null);
const layananVisible = ref(false);
const howSection = ref(null);
const howVisible = ref(false);
const routeSection = ref(null);
const routeVisible = ref(false);
const testiSection = ref(null);
const testiVisible = ref(false);
const tentangSection = ref(null);
const tentangVisible = ref(false);
const ctaSection = ref(null);
const ctaVisible = ref(false);

const makeObs = (r, v) =>
    new IntersectionObserver(
        ([e]) => {
            if (e.isIntersecting) {
                v.value = true;
            }
        },
        { threshold: 0.08 },
    );

const observers = [];
let testiInterval = null;

onMounted(() => {
    // 3D & Hero Animations
    nextTick(initThreeJS);
    window.addEventListener('resize', onWindowResize);
    setTimeout(() => {
        heroVisible.value = true;
    }, 60);

    // Scroll Observers
    const pairs = [
        [statsSection, statsVisible],
        [layananSection, layananVisible],
        [howSection, howVisible],
        [routeSection, routeVisible],
        [testiSection, testiVisible],
        [tentangSection, tentangVisible],
        [ctaSection, ctaVisible],
    ];
    nextTick(() => {
        pairs.forEach(([r, v]) => {
            if (r.value) {
                const o = makeObs(r, v);
                o.observe(r.value);
                observers.push(o);
            }
        });
    });

    testiInterval = setInterval(nextSlide, 6000);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onWindowResize);
    cancelAnimationFrame(animationFrameId);

    if (renderer) {
        renderer.dispose();
    }

    observers.forEach((o) => o.disconnect());
    clearInterval(testiInterval);
});
</script>

<style scoped>
/* ─────────────────────────────────────────
   Welcome Page Specific Styles Only
───────────────────────────────────────── */

/* ─── Marquee ─── */
@keyframes marquee {
    0% {
        transform: translateX(0%);
    }
    100% {
        transform: translateX(-50%);
    }
}
.animate-marquee {
    animation: marquee 32s linear infinite;
    width: max-content;
}
.animate-marquee:hover {
    animation-play-state: paused;
}

/* ─── Scroll Reveal ─── */
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

.reveal-right {
    opacity: 0;
    transform: translateX(36px);
    transition:
        opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    transition-delay: 100ms;
}
.reveal-right.visible {
    opacity: 1;
    transform: translateX(0);
}

/* ─── Service Card ─── */
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

/* ─── Vue Transitions ─── */
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(8px);
}
</style>
