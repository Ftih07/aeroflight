<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Booking Confirmation - AeroFlight</title>
</head>

<body style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 40px 20px;">

    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <tr>
            <td style="background-color: #2563eb; padding: 25px 30px; text-align: center;">
                <span style="color: #ffffff; font-size: 24px; font-weight: bold; letter-spacing: 1px;">AeroFlight</span>
            </td>
        </tr>

        <tr>
            <td style="padding: 35px 30px;">
                <h2 style="margin-top: 0; color: #0f172a; font-size: 22px;">Hello, {{ $booking->user->name ?? 'Valued Passenger' }},</h2>

                @if($booking->status === 'paid')
                <p style="line-height: 1.6; color: #475569; font-size: 15px;">Great news! Your payment has been successfully processed, and your flight booking is now <strong>confirmed</strong>. You will find your E-Ticket attached to this email.</p>
                @else
                <p style="line-height: 1.6; color: #475569; font-size: 15px;">We want to inform you about a recent update regarding your flight booking.</p>
                @endif

                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; margin: 25px 0;">
                    <tr>
                        <td style="padding: 25px;">

                            <div style="text-align: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                                <p style="margin: 0 0 5px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">Booking Reference (PNR)</p>
                                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2563eb; letter-spacing: 2px;">{{ $booking->pnr_code ?? 'N/A' }}</p>
                            </div>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td width="50%" style="padding-bottom: 15px;">
                                        <p style="margin: 0; font-size: 12px; color: #64748b; text-transform: uppercase;">Airline</p>
                                        <p style="margin: 4px 0 0 0; font-size: 15px; font-weight: bold; color: #0f172a;">{{ $booking->flight->airline_name ?? $booking->flight->airline_code }}</p>
                                    </td>
                                    <td width="50%" style="padding-bottom: 15px; text-align: right;">
                                        <p style="margin: 0; font-size: 12px; color: #64748b; text-transform: uppercase;">Flight No.</p>
                                        <p style="margin: 4px 0 0 0; font-size: 15px; font-weight: bold; color: #0f172a;">
                                            {{ str_contains($booking->flight->flight_number, $booking->flight->airline_code) || str_contains($booking->flight->flight_number, '-') ? $booking->flight->flight_number : $booking->flight->airline_code . '-' . $booking->flight->flight_number }}
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%">
                                        <p style="margin: 0; font-size: 12px; color: #64748b; text-transform: uppercase;">Route</p>
                                        <p style="margin: 4px 0 0 0; font-size: 18px; font-weight: bold; color: #0f172a;">{{ $booking->flight->origin_airport }} -> {{ $booking->flight->destination_airport }}</p>
                                    </td>
                                    <td width="50%" style="text-align: right;">
                                        <p style="margin: 0; font-size: 12px; color: #64748b; text-transform: uppercase;">Date</p>
                                        <p style="margin: 4px 0 0 0; font-size: 14px; font-weight: bold; color: #0f172a;">{{ \Carbon\Carbon::parse($booking->flight->departure_at)->format('d M Y') }}</p>
                                    </td>
                                </tr>
                            </table>

                            <table width="100%">
                                <tr>
                                    <td style="background-color: #ffffff; border: 1px solid #cbd5e1; padding: 12px; text-align: center; border-radius: 6px;">
                                        <span style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Current Status</span><br>
                                        <strong style="color: {{ $booking->status === 'paid' ? '#059669' : '#2563eb' }}; font-size: 18px; text-transform: uppercase; display: inline-block; margin-top: 4px;">{{ $booking->status }}</strong>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>
                </table>

                <p style="line-height: 1.6; color: #475569; margin-bottom: 0; font-size: 14px;">If you have any questions about your booking, feel free to contact our support team.<br><br>Safe travels,<br><strong>The AeroFlight Team</strong></p>
            </td>
        </tr>

        <tr>
            <td style="background-color: #f1f5f9; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; font-size: 12px; color: #64748b;">&copy; {{ date('Y') }} AeroFlight. All rights reserved.</p>
                <p style="margin: 5px 0 0 0; font-size: 11px; color: #94a3b8;">This is an automated message, please do not reply to this email.</p>
            </td>
        </tr>
    </table>

</body>

</html>