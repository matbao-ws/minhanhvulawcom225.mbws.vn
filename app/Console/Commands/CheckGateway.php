<?php

namespace App\Console\Commands;

use App\Services\AiGateway\GatewayClient;
use App\Services\MailSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Can this website talk to the MatBao gateway (mail relay, AI, core updates)? Says which part
 * fails — not configured, DNS, TLS/connection, rejected token — without printing the token.
 * deploy.py runs it after every deploy.
 */
class CheckGateway extends Command
{
    protected $signature = 'gateway:check';

    protected $description = 'Check the connection to the MatBao gateway (mail relay, AI, core updates)';

    public function handle(MailSettings $mail): int
    {
        $url = (string) config('ai_gateway.url');
        $this->components->twoColumnDetail('Gửi mail bằng', match ($mail->source()) {
            MailSettings::SOURCE_ADMIN => 'SMTP nhập trong Admin',
            MailSettings::SOURCE_REMOTE => 'relay Mắt Bão (gateway)',
            default => '.env (MAIL_MAILER='.config('mail.default').')',
        });
        $this->components->twoColumnDetail('Hàng đợi', (string) config('queue.default'));

        if ($url === '' || blank(config('ai_gateway.token'))) {
            $this->components->error('Chưa cấu hình MATBAO_AI_URL / MATBAO_AI_TOKEN.');

            return self::FAILURE;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $this->components->twoColumnDetail('Gateway', $url);
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = gethostbyname($host);
            $this->components->twoColumnDetail('DNS '.$host, $ip !== $host ? $ip : 'KHÔNG phân giải được');
        }

        try {
            $response = Http::withToken((string) config('ai_gateway.token'))->acceptJson()
                ->connectTimeout(5)->timeout(15)->get($url.'/v1/project');
        } catch (Throwable $e) {
            // Connection refused, timeout, TLS: the host cannot reach the gateway at all.
            $this->components->error('Không kết nối được gateway: '.mb_strimwidth($e->getMessage(), 0, 300, '…'));

            return self::FAILURE;
        }

        if ($response->successful() && $response->json('ok') === true) {
            $this->components->info('Kết nối gateway OK (token hợp lệ, project '.$response->json('project.tool_domain').').');
            GatewayClient::forgetProject();

            return self::SUCCESS;
        }

        $reason = (string) ($response->json('reason') ?? 'HTTP '.$response->status());
        $this->components->error(match ($reason) {
            'unauthorized' => 'Gateway từ chối token (sai hoặc đã bị thu hồi). Lấy token mới: python deploy.py --domain <site> --gateway-token',
            'project_suspended' => 'Project của site đang bị tạm ngưng trên gateway.',
            default => "Gateway trả lỗi: {$reason}",
        });

        return self::FAILURE;
    }
}
