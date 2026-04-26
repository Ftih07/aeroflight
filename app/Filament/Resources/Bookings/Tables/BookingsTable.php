<?php

namespace App\Filament\Resources\Bookings\Tables;


use Filament\Actions\Action as ActionsAction;
use Filament\Actions\BulkActionGroup as ActionsBulkActionGroup;
use Filament\Actions\DeleteBulkAction as ActionsDeleteBulkAction;
use Filament\Actions\EditAction as ActionsEditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

use Stripe\Stripe;
use Stripe\Refund;
use Stripe\Checkout\Session as StripeSession;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pnr_code')->searchable()->label('PNR'),
                TextColumn::make('flight.flight_number')->label('Flight'),
                TextColumn::make('total_amount_usd')->money('USD')->sortable(),

                // --- TAMBAHKAN KOLOM INI ---
                TextColumn::make('Refunded')
                    ->label('Refunded Amount')
                    ->getStateUsing(function ($record) {
                        $refundTx = $record->transactions->where('type', 'refund')->first();
                        return $refundTx ? '$' . number_format($refundTx->amount, 2) : '-';
                    })
                    ->color('danger'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'used' => 'gray',
                        'refund_requested' => 'danger',
                        'refunded' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                // Tombol Edit pindah ke sini (paling atas atau bawah bebas)
                ActionsEditAction::make(),

                // 1. TOMBOL CHECK-IN (MARK AS USED)
                ActionsAction::make('mark_used')
                    ->label('Check-In')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'paid')
                    ->action(function ($record) {
                        $record->update(['status' => 'used']);
                        Notification::make()->title('Ticket marked as used!')->success()->send();
                    }),


                // 2. TOMBOL SAKTI REFUND STRIPE (DENGAN INPUT PERSENTASE)
                ActionsAction::make('process_refund')
                    ->label('Approve Refund')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\TextInput::make('refund_percentage')
                            ->label('Refund Percentage (%)')
                            ->numeric()
                            ->default(100)
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->helperText('Masukkan persentase dana (1-100) yang akan dikembalikan dari sisa uang setelah dipotong Stripe Fee.'),
                    ])
                    ->modalHeading('Process Stripe Refund')
                    ->visible(fn($record) => $record->status === 'refund_requested' && !empty($record->stripe_payment_id))
                    ->action(function (array $data, $record) {
                        try {
                            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

                            // LANGSUNG CEK PAYMENT ID (Lebih aman dari retrieve session)
                            if (empty($record->stripe_payment_id)) {
                                throw new \Exception("Payment ID tidak ditemukan. Transaksi ini mungkin tidak diselesaikan melalui Stripe.");
                            }

                            // UBAH DI SINI: Gunakan final_amount_usd karena itu uang real yang masuk ke Stripe
                            // Rumus fee standar Stripe: 2.9% + $0.30
                            $stripeFeeUsd = ($record->final_amount_usd * 0.029) + 0.30;

                            // Uang maksimal yang bisa dikembalikan tanpa bikin perusahaan rugi
                            $maxRefundUsd = $record->final_amount_usd - $stripeFeeUsd;

                            if ($maxRefundUsd <= 0) {
                                throw new \Exception("Nominal pembayaran terlalu kecil untuk di-refund setelah dipotong fee Stripe.");
                            }

                            // Hitung nominal akhir berdasarkan persentase yang diketik Admin
                            $percentage = $data['refund_percentage'] / 100;
                            $finalRefundUsd = $maxRefundUsd * $percentage;

                            // Convert ke Cents untuk Stripe
                            $refundAmountCents = (int) round($finalRefundUsd * 100);

                            // Eksekusi ke Stripe
                            \Stripe\Refund::create([
                                'payment_intent' => $record->stripe_payment_id,
                                'amount' => $refundAmountCents,
                            ]);

                            // --- PENCATATAN KE TABEL TRANSACTIONS ---

                            // 1. Catat Biaya Stripe
                            $record->transactions()->create([
                                'type' => 'stripe_fee',
                                'amount' => $stripeFeeUsd,
                                'description' => 'Stripe processing fee deducted from refund'
                            ]);

                            // 2. Catat Uang yang dikembalikan ke user
                            $record->transactions()->create([
                                'type' => 'refund',
                                'amount' => $finalRefundUsd,
                                'description' => 'Refunded to customer (' . $data['refund_percentage'] . '%)'
                            ]);

                            // 3. Catat Uang yang ditahan perusahaan (Cancellation Fee)
                            $cancellationFee = $maxRefundUsd - $finalRefundUsd;
                            if ($cancellationFee > 0) {
                                $record->transactions()->create([
                                    'type' => 'cancellation_fee',
                                    'amount' => $cancellationFee,
                                    'description' => 'Cancellation fee kept by company (' . (100 - $data['refund_percentage']) . '%)'
                                ]);
                            }

                            // Update status booking
                            $record->update(['status' => 'refunded']);

                            // Kosongkan Kursi
                            foreach ($record->passengers as $passenger) {
                                $passenger->update(['assigned_seats' => null]);
                            }

                            // Optional: Kembalikan Poin User kalau mau
                            if ($record->points_used > 0) {
                                $user = \App\Models\User::find($record->user_id);
                                if ($user) {
                                    $user->increment('loyalty_points', $record->points_used);
                                }
                            }

                            // Tarik balik Poin yang didapat dari transaksi ini
                            if ($record->points_earned > 0) {
                                $user = \App\Models\User::find($record->user_id);
                                if ($user) {
                                    // Cegah poin user jadi minus kalau dia udah pake poinnya buat hal lain
                                    $newPoin = max(0, $user->loyalty_points - $record->points_earned);
                                    $user->update(['loyalty_points' => $newPoin]);
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Refund Successful!')
                                ->body('Returned $' . number_format($finalRefundUsd, 2) . ' (' . $data['refund_percentage'] . '%) to customer.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Refund Failed: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),



                // 3. TOMBOL CANCEL MANUAL
                ActionsAction::make('cancel_booking')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn($record) => in_array($record->status, ['pending', 'paid']))
                    ->action(function ($record) {

                        // 1. Update status jadi cancelled
                        $record->update(['status' => 'cancelled']);

                        // 2. KOSONGKAN KURSI!
                        foreach ($record->passengers as $passenger) {
                            \App\Models\Seat::where('id', $passenger->seat_id)
                                ->update(['is_available' => true]);
                        }

                        Notification::make()->title('Booking Cancelled & Seats Freed!')->warning()->send();
                    }),

                // 4. TOMBOL EXPIRED (Logika Waktu)
                ActionsAction::make('mark_expired')
                    ->label('Mark Expired')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->requiresConfirmation()
                    // INI LOGIKANYA: Tombol HANYA muncul kalau jadwal terbang sudah lewat hari ini, 
                    // dan tiketnya nganggur belum dipakai (masih pending atau paid).
                    ->visible(
                        fn($record) =>
                        in_array($record->status, ['pending', 'paid']) &&
                            \Carbon\Carbon::now()->greaterThan($record->flight->departure_at)
                    )
                    ->action(function ($record) {
                        $record->update(['status' => 'expired']);
                        Notification::make()->title('Booking marked as expired!')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            // ->recordActions([...]) NYA DIHAPUS SAJA
            ->toolbarActions([
                ActionsBulkActionGroup::make([
                    ActionsDeleteBulkAction::make(),
                ]),
            ]);
    }
}
