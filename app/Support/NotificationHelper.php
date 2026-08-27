<?php

namespace App\Support;

use App\Jobs\SendStoreOrderNotification;
use App\Models\Order;
use App\Models\ProjectSetting;
use App\Services\MailSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationHelper
{
    /**
     * Dispatch notifications for new orders (Zalo Bot & SMTP Email).
     */
    public static function sendNewOrderNotification(Order $order): void
    {
        SendStoreOrderNotification::dispatch($order->id)->afterCommit();
    }

    /**
     * Deliver a store-owner notification from the queue worker.
     */
    public static function deliverNewOrderNotification(Order $order): void
    {
        try {
            $settingsRecord = ProjectSetting::query()->where('setting_key', 'notification_settings')->first();
            $settings = $settingsRecord ? $settingsRecord->setting_value : [];

            if (empty($settings)) {
                return;
            }

            // 1. Zalo Personal Bot Notification (bot.zapps.me)
            if (data_get($settings, 'zalo_personal.enabled')) {
                $botToken = data_get($settings, 'zalo_personal.bot_token');
                $botToken = preg_replace('/^zbot:/i', '', trim($botToken));
                $chatId = data_get($settings, 'zalo_personal.chat_id');

                if ($botToken && $chatId) {
                    $text = "🔔 **Đơn hàng mới!**\n";
                    $text .= "• Mã đơn: #{$order->order_number}\n";
                    $text .= "• Khách hàng: {$order->customer_name}\n";
                    $text .= "• Số điện thoại: {$order->customer_phone}\n";
                    $text .= "• Tổng cộng: " . number_format($order->grand_total, 0, ',', '.') . " ₫\n";
                    $text .= "• Địa chỉ: {$order->shipping_address}\n";

                    $response = Http::post("https://bot-api.zaloplatforms.com/bot{$botToken}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $text,
                        'parse_mode' => 'markdown',
                    ]);

                    if (!$response->successful()) {
                        Log::error("Zalo Bot send message failed: " . $response->body());
                    }
                }
            }

            // 2. SMTP Owner Email Notification
            //
            // The transport itself is no longer configured here: MailSettings applies
            // the admin configuration — or the .env fallback — when the mail manager
            // is resolved. The toggle below stays a notification switch, not a
            // transport switch, so turning it off silences this mail without
            // affecting how the rest of the application sends.
            if (data_get($settings, 'smtp.enabled')) {
                $ownerEmail = app(MailSettings::class)->ownerEmail();
                if ($ownerEmail) {
                    // Send email to the owner from the queue worker.
                    Mail::to($ownerEmail)->locale($order->locale ?: 'vi')->send(new \App\Mail\OrderStatusMail($order));
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to send order notification: " . $e->getMessage());
        }
    }
}
