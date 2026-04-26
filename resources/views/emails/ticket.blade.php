<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>E-Ticket AeroFlight - {{ $booking->pnr_code }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #374151;
            margin: 0;
            padding: 0;
        }

        .ticket-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .header {
            background-color: #2563eb;
            color: #ffffff;
            padding: 25px 30px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .header table {
            width: 100%;
            border: none;
        }

        .header td {
            border: none;
            padding: 0;
            color: #ffffff;
            vertical-align: top;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
        }

        .pnr-box {
            text-align: right;
        }

        .pnr-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #bfdbfe;
        }

        .pnr-code {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-top: 2px;
        }

        .content {
            padding: 30px;
        }

        .flight-header-table {
            width: 100%;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 15px;
            padding-bottom: 10px;
            margin-top: 10px;
        }

        .flight-title {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 8px;
        }

        .qr-img {
            width: 70px;
            height: 70px;
            border: 1px solid #cbd5e1;
            padding: 4px;
            border-radius: 6px;
            background-color: #fff;
        }

        .qr-label {
            font-size: 10px;
            font-family: monospace;
            color: #475569;
            margin-top: 4px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .badge-outbound {
            display: inline-block;
            background: #d1fae5;
            color: #047857;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-return {
            display: inline-block;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .facility-badge {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 10px;
            margin-right: 5px;
            margin-top: 5px;
            border: 1px solid #e2e8f0;
            font-weight: bold;
        }

        /* GAYA BARU UNTUK ROUTE UI */
        .route-table {
            width: 100%;
            margin-bottom: 15px;
        }

        .airport-code {
            font-size: 32px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .city-name {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .flight-time {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }

        .flight-date {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        .arrow-line {
            font-size: 20px;
            color: #94a3b8;
            margin: 5px 0;
        }

        .duration-info {
            font-size: 11px;
            font-weight: bold;
            color: #64748b;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            margin-top: 15px;
        }

        .info-table th {
            background-color: #f3f4f6;
            color: #4b5563;
            font-size: 11px;
            text-transform: uppercase;
            padding: 10px;
            text-align: left;
            border: 1px solid #e5e7eb;
        }

        .info-table td {
            padding: 10px;
            font-size: 13px;
            border: 1px solid #e5e7eb;
        }

        .total-row td {
            font-weight: bold;
            font-size: 16px;
            background-color: #eff6ff;
            color: #1d4ed8;
        }

        .footer {
            background-color: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            border-top: 1px dashed #d1d5db;
        }
    </style>
</head>

<body>

    <div class="ticket-container">
        <div class="header">
            <table>
                <tr>
                    <td class="logo">AeroFlight</td>
                    <td class="pnr-box">
                        <div class="pnr-title">Booking Reference (PNR)</div>
                        <div class="pnr-code" style="margin-bottom: {{ isset($child_booking) && $child_booking ? '15px' : '0' }};">{{ $booking->pnr_code }}</div>
                        @if(isset($child_booking) && $child_booking)
                        <div class="pnr-title">Return PNR</div>
                        <div class="pnr-code" style="margin-bottom: 0;">{{ $child_booking->pnr_code }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="content">

            <table class="flight-header-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="middle">
                        <div class="flight-title">Outbound Flight Details</div>
                        <div class="badge-outbound">OUTBOUND: {{ $booking->flight->segments[0]->airlineData->name ?? $booking->flight->segments[0]->airline_code }}</div>
                    </td>
                    <td align="right" valign="middle" width="100">
                        @if($booking->qr_token)
                        <div style="text-align: right;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ $booking->qr_token }}&color=0f172a&bgcolor=ffffff" class="qr-img" alt="QR Code">
                            <div class="qr-label">{{ $booking->pnr_code }}</div>
                        </div>
                        @endif
                    </td>
                </tr>
            </table>

            <table class="route-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="35%" align="left" valign="top">
                        <div class="airport-code">{{ $booking->flight->origin_airport }}</div>
                        <div class="city-name">{{ $booking->flight->origin_city ?? 'CITY INFO' }}</div>
                        <div class="flight-time">{{ \Carbon\Carbon::parse($booking->flight->departure_at)->format('h:i A') }}</div>
                        <div class="flight-date">{{ \Carbon\Carbon::parse($booking->flight->departure_at)->format('d M Y') }}</div>
                    </td>
                    <td width="30%" align="center" valign="top" style="padding-top: 10px;">
                        <div>
                            @if($booking->flight->stop_count == 0)
                            <span style="font-size: 11px; font-weight: bold; color: #10b981;">DIRECT</span>
                            @else
                            <span style="font-size: 11px; font-weight: bold; color: #f59e0b;">{{ $booking->flight->stop_count }} STOP(S)</span>
                            @endif
                        </div>
                        <div class="arrow-line">&rarr;</div>
                        <div class="duration-info">
                            @php
                            $diff = \Carbon\Carbon::parse($booking->flight->departure_at)->diffInMinutes(\Carbon\Carbon::parse($booking->flight->arrival_at));
                            @endphp
                            {{ intdiv($diff, 60) }}h {{ $diff % 60 }}m
                        </div>
                    </td>
                    <td width="35%" align="right" valign="top">
                        <div class="airport-code">{{ $booking->flight->destination_airport }}</div>
                        <div class="city-name">{{ $booking->flight->destination_city ?? 'CITY INFO' }}</div>
                        <div class="flight-time">{{ \Carbon\Carbon::parse($booking->flight->arrival_at)->format('h:i A') }}</div>
                        <div class="flight-date">{{ \Carbon\Carbon::parse($booking->flight->arrival_at)->format('d M Y') }}</div>
                    </td>
                </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fdf8f6; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb;">
                <tr>
                    <td style="font-size: 11px;">
                        <strong>Flight No:</strong> {{ $booking->flight->segments[0]->airline_code }}-{{ $booking->flight->segments[0]->flight_number }} &nbsp;|&nbsp;
                        <strong>Aircraft:</strong> {{ $booking->flight->segments[0]->aircraft->model_name ?? 'TBA' }}<br>

                        <div style="margin-top: 8px;">
                            <strong>Included Facilities:</strong>
                            @php
                            // Ambil fasilitas dari class di segment pertama
                            $outboundClassData = $booking->flight->segments[0]->classes->first();
                            $fac = $outboundClassData ? $outboundClassData->facilities : [];
                            @endphp
                            @if(isset($fac['meal']) && $fac['meal']) <span class="facility-badge">Meal</span> @endif
                            @if(isset($fac['wifi']) && $fac['wifi']) <span class="facility-badge">WiFi</span> @endif
                            @if(isset($fac['entertainment']) && $fac['entertainment']) <span class="facility-badge">Screen</span> @endif
                            @if(isset($fac['power_usb']) && $fac['power_usb']) <span class="facility-badge">USB</span> @endif

                            <strong style="margin-left: 15px;">Policy:</strong>
                            @if($booking->flight->is_refundable) <span class="facility-badge" style="background:#d1fae5;color:#047857;border-color:#047857;">Refundable</span> @endif
                            @if($booking->flight->is_reschedulable) <span class="facility-badge" style="background:#dbeafe;color:#1d4ed8;border-color:#1d4ed8;">Reschedule</span> @endif
                        </div>
                    </td>
                </tr>
            </table>

            <table class="info-table">
                <tr>
                    <th>No.</th>
                    <th>Passenger Name</th>
                    <th>ID / Passport</th>
                    <th>Seat(s)</th>
                    <th>Baggage Info</th>
                </tr>
                @foreach($booking->passengers as $index => $passenger)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td><strong>{{ strtoupper($passenger->title . '. ' . $passenger->first_name . ' ' . $passenger->last_name) }}</strong></td>
                    <td>{{ $passenger->passport_number ?? '-' }}</td>
                    <td style="text-align: center;">
                        <strong>
                            @php
                            // Decode JSON jika formatnya string
                            $seatsAssigned = is_string($passenger->assigned_seats) ? json_decode($passenger->assigned_seats, true) : $passenger->assigned_seats;
                            $seatCodes = $seatsAssigned ? implode(', ', array_values($seatsAssigned)) : 'TBA';
                            @endphp
                            {{ $seatCodes }}
                        </strong>
                    </td>
                    <td>
                        <small>Cabin:</small> {{ $outboundClassData ? $outboundClassData->cabin_baggage_kg : 7 }}KG<br>
                        <small>Checked:</small> {{ ($outboundClassData ? $outboundClassData->free_baggage_kg : 20) + $passenger->extra_baggage_kg }}KG
                    </td>
                </tr>
                @endforeach
            </table>

            @if(isset($child_booking) && $child_booking)
            <table class="flight-header-table" cellpadding="0" cellspacing="0" style="margin-top: 35px;">
                <tr>
                    <td valign="middle">
                        <div class="flight-title">Return Flight Details</div>
                        <div class="badge-return">RETURN: {{ $child_booking->flight->segments[0]->airlineData->name ?? $child_booking->flight->segments[0]->airline_code }}</div>
                    </td>
                    <td align="right" valign="middle" width="100">
                        @if($child_booking->qr_token)
                        <div style="text-align: right;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ $child_booking->qr_token }}&color=0f172a&bgcolor=ffffff" class="qr-img" alt="QR Code">
                            <div class="qr-label">{{ $child_booking->pnr_code }}</div>
                        </div>
                        @endif
                    </td>
                </tr>
            </table>

            <table class="route-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="35%" align="left" valign="top">
                        <div class="airport-code">{{ $child_booking->flight->origin_airport }}</div>
                        <div class="city-name">{{ $child_booking->flight->origin_city ?? 'CITY INFO' }}</div>
                        <div class="flight-time">{{ \Carbon\Carbon::parse($child_booking->flight->departure_at)->format('h:i A') }}</div>
                        <div class="flight-date">{{ \Carbon\Carbon::parse($child_booking->flight->departure_at)->format('d M Y') }}</div>
                    </td>
                    <td width="30%" align="center" valign="top" style="padding-top: 10px;">
                        <div>
                            @if($child_booking->flight->stop_count == 0)
                            <span style="font-size: 11px; font-weight: bold; color: #10b981;">DIRECT</span>
                            @else
                            <span style="font-size: 11px; font-weight: bold; color: #f59e0b;">{{ $child_booking->flight->stop_count }} STOP(S)</span>
                            @endif
                        </div>
                        <div class="arrow-line">&rarr;</div>
                        <div class="duration-info">
                            @php
                            $diffChild = \Carbon\Carbon::parse($child_booking->flight->departure_at)->diffInMinutes(\Carbon\Carbon::parse($child_booking->flight->arrival_at));
                            @endphp
                            {{ intdiv($diffChild, 60) }}h {{ $diffChild % 60 }}m
                        </div>
                    </td>
                    <td width="35%" align="right" valign="top">
                        <div class="airport-code">{{ $child_booking->flight->destination_airport }}</div>
                        <div class="city-name">{{ $child_booking->flight->destination_city ?? 'CITY INFO' }}</div>
                        <div class="flight-time">{{ \Carbon\Carbon::parse($child_booking->flight->arrival_at)->format('h:i A') }}</div>
                        <div class="flight-date">{{ \Carbon\Carbon::parse($child_booking->flight->arrival_at)->format('d M Y') }}</div>
                    </td>
                </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fdf8f6; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb;">
                <tr>
                    <td style="font-size: 11px;">
                        <strong>Flight No:</strong> {{ $child_booking->flight->segments[0]->airline_code }}-{{ $child_booking->flight->segments[0]->flight_number }} &nbsp;|&nbsp;
                        <strong>Aircraft:</strong> {{ $child_booking->flight->segments[0]->aircraft->model_name ?? 'TBA' }}<br>

                        <div style="margin-top: 8px;">
                            <strong>Included Facilities:</strong>
                            @php
                            $returnClassData = $child_booking->flight->segments[0]->classes->first();
                            $fac2 = $returnClassData ? $returnClassData->facilities : [];
                            @endphp
                            @if(isset($fac2['meal']) && $fac2['meal']) <span class="facility-badge">Meal</span> @endif
                            @if(isset($fac2['wifi']) && $fac2['wifi']) <span class="facility-badge">WiFi</span> @endif
                            @if(isset($fac2['entertainment']) && $fac2['entertainment']) <span class="facility-badge">Screen</span> @endif
                            @if(isset($fac2['power_usb']) && $fac2['power_usb']) <span class="facility-badge">USB</span> @endif

                            <strong style="margin-left: 15px;">Policy:</strong>
                            @if($child_booking->flight->is_refundable) <span class="facility-badge" style="background:#d1fae5;color:#047857;border-color:#047857;">Refundable</span> @endif
                            @if($child_booking->flight->is_reschedulable) <span class="facility-badge" style="background:#dbeafe;color:#1d4ed8;border-color:#1d4ed8;">Reschedule</span> @endif
                        </div>
                    </td>
                </tr>
            </table>

            <table class="info-table">
                <tr>
                    <th>No.</th>
                    <th>Passenger Name</th>
                    <th>ID / Passport</th>
                    <th>Seat(s)</th>
                    <th>Baggage Info</th>
                </tr>
                @foreach($child_booking->passengers as $index => $passenger)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td><strong>{{ strtoupper($passenger->title . '. ' . $passenger->first_name . ' ' . $passenger->last_name) }}</strong></td>
                    <td>{{ $passenger->passport_number ?? '-' }}</td>
                    <td style="text-align: center;">
                        <strong>
                            @php
                            $retSeatsAssigned = is_string($passenger->assigned_seats) ? json_decode($passenger->assigned_seats, true) : $passenger->assigned_seats;
                            $retSeatCodes = $retSeatsAssigned ? implode(', ', array_values($retSeatsAssigned)) : 'TBA';
                            @endphp
                            {{ $retSeatCodes }}
                        </strong>
                    </td>
                    <td>
                        <small>Cabin:</small> {{ $returnClassData ? $returnClassData->cabin_baggage_kg : 7 }}KG<br>
                        <small>Checked:</small> {{ ($returnClassData ? $returnClassData->free_baggage_kg : 20) + $passenger->extra_baggage_kg }}KG
                    </td>
                </tr>
                @endforeach
            </table>
            @endif

            <div class="flight-title" style="margin-top: 35px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">Booking & Payment Summary</div>
            <table class="info-table">
                <tr>
                    <td colspan="4" style="text-align: right; font-size: 11px; color: #6b7280; text-transform: uppercase;">
                        Date of Issue / Booking Time
                    </td>
                    <td style="text-align: right; font-weight: bold;">
                        {{ \Carbon\Carbon::parse($booking->created_at)->format('d M Y, H:i') }}
                    </td>
                </tr>

                <tr>
                    <td colspan="4" style="text-align: right; font-size: 12px; color: #4b5563;">
                        Base Flight Ticket(s) & Seat Selection
                    </td>
                    <td style="text-align: right; font-size: 12px; font-weight: bold;">
                        ${{ number_format($booking->total_amount_usd + (isset($child_booking) ? $child_booking->total_amount_usd : 0), 2) }}
                    </td>
                </tr>

                @if($booking->insurance_fee_usd > 0)
                <tr>
                    <td colspan="4" style="text-align: right; font-size: 12px; color: #4b5563;">
                        Travel Protection Insurance
                    </td>
                    <td style="text-align: right; font-size: 12px; font-weight: bold;">
                        + ${{ number_format($booking->insurance_fee_usd, 2) }}
                    </td>
                </tr>
                @endif

                @if($booking->discount_amount_usd > 0)
                <tr>
                    <td colspan="4" style="text-align: right; font-size: 12px; color: #047857;">
                        Promo Discount
                    </td>
                    <td style="text-align: right; font-size: 12px; font-weight: bold; color: #047857;">
                        - ${{ number_format($booking->discount_amount_usd, 2) }}
                    </td>
                </tr>
                @endif

                @if($booking->points_used > 0)
                <tr>
                    <td colspan="4" style="text-align: right; font-size: 12px; color: #d97706;">
                        AeroPoints Applied
                    </td>
                    <td style="text-align: right; font-size: 12px; font-weight: bold; color: #d97706;">
                        - ${{ number_format($booking->points_used, 2) }}
                    </td>
                </tr>
                @endif

                <tr class="total-row">
                    <td colspan="4" style="text-align: right;">TOTAL PAID ({{ strtoupper($booking->status) }})</td>
                    <td style="text-align: right;">
                        ${{ number_format($booking->final_amount_usd, 2) }}
                    </td>
                </tr>
            </table>

        </div>
        <div class="footer">
            <p>Thank you for choosing AeroFlight. Please arrive at the airport at least 2 hours before departure.</p>
            <p>&copy; {{ date('Y') }} AeroFlight. Part of Portfolio Project.</p>
        </div>
    </div>
</body>

</html>