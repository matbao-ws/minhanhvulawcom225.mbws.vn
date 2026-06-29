<?php

namespace App\Support;

use App\Models\Order;
use App\Models\ProjectSetting;
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
            if (data_get($settings, 'smtp.enabled')) {
                $ownerEmail = data_get($settings, 'smtp.owner_email');
                if ($ownerEmail) {
                    // Set SMTP mail configurations dynamically
                    config([
                        'mail.mailers.smtp.host' => data_get($settings, 'smtp.host', 'smtp.gmail.com'),
                        'mail.mailers.smtp.port' => (int) data_get($settings, 'smtp.port', 465),
                        'mail.mailers.smtp.encryption' => data_get($settings, 'smtp.encryption', 'ssl'),
                        'mail.mailers.smtp.username' => data_get($settings, 'smtp.username'),
                        'mail.mailers.smtp.password' => data_get($settings, 'smtp.password'),
                        'mail.from.address' => data_get($settings, 'smtp.from_email'),
                        'mail.from.name' => data_get($settings, 'smtp.from_name', 'Cửa hàng'),
                    ]);

                    // Send email to the owner
                    Mail::to($ownerEmail)->send(new \App\Mail\OrderStatusMail($order));
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to send order notification: " . $e->getMessage());
        }
    }
}
