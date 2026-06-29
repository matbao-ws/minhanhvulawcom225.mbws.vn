<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thử nghiệm Cổng thanh toán VNPAY (Simulated)</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            max-width: 520px;
            width: 100%;
            overflow: hidden;
        }
        .vnpay-header {
            background: linear-gradient(135deg, #005baa 0%, #0076a3 100%);
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .vnpay-logo {
            height: 48px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
        }
        .vnpay-body {
            padding: 40px 30px;
        }
        .order-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.1);
        }
        .order-info-row:last-child {
            border-bottom: none;
        }
        .price-tag {
            font-size: 24px;
            font-weight: 700;
            color: #38bdf8;
        }
        .btn-success-gradient {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 14px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        .btn-success-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        .btn-danger-outline {
            background: transparent;
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #f87171;
            font-weight: 500;
            padding: 14px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        .btn-danger-outline:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .alert-simulation {
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.2);
            color: #7dd3fc;
            border-radius: 12px;
            padding: 15px;
            font-size: 14px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

<div class="glass-card">
    <div class="vnpay-header">
        <h3 class="mb-0 fw-bold text-white">VNPAY SIMULATOR</h3>
        <span class="badge bg-warning text-dark mt-2">Môi trường Giả lập Thử nghiệm</span>
    </div>
    
    <div class="vnpay-body">
        <div class="alert-simulation d-flex align-items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-info-circle-fill flex-shrink-0" viewBox="0 0 16 16">
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
            </svg>
            <div>Trang này mô phỏng luồng thanh toán VNPAY sandbox nội bộ để test kết nối đơn hàng trên Localhost.</div>
        </div>

        <div class="mb-4">
            <div class="order-info-row">
                <span class="text-muted">Mã đơn hàng</span>
                <span class="fw-semibold">{{ $order->order_number }}</span>
            </div>
            <div class="order-info-row">
                <span class="text-muted">Khách hàng</span>
                <span>{{ $order->customer_name }}</span>
            </div>
            <div class="order-info-row">
                <span class="text-muted">Số điện thoại</span>
                <span>{{ $order->customer_phone }}</span>
            </div>
            <div class="order-info-row">
                <span class="text-muted">Số tiền cần thanh toán</span>
                <span class="price-tag">{{ number_format($order->grand_total, 0, ',', '.') }} VND</span>
            </div>
        </div>

        <form action="{{ route('vnpay.mock.submit') }}" method="POST" class="d-flex flex-column gap-3">
            @csrf
            <input type="hidden" name="order_id" value="{{ $order->order_number }}">
            <input type="hidden" name="amount" value="{{ $order->grand_total }}">
            <input type="hidden" name="redirect_url" value="{{ $redirectUrl }}">
            
            <button type="submit" name="status" value="success" class="btn btn-success-gradient w-100">
                Xác nhận thanh toán (Thành công)
            </button>
            
            <button type="submit" name="status" value="cancel" class="btn btn-danger-outline w-100">
                Hủy giao dịch (Thất bại / Cancel)
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
