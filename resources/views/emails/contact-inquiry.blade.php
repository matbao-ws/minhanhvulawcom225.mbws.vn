<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Yêu Cầu Báo Giá Mới</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #0e2747;
            padding: 30px;
            text-align: center;
            border-bottom: 3px solid #EDBF6D;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            letter-spacing: 1px;
        }
        .header p {
            color: #EDBF6D;
            margin: 5px 0 0 0;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .content {
            padding: 30px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .info-table th, .info-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-table th {
            width: 35%;
            color: #475569;
            font-weight: bold;
            background-color: #f8fafc;
        }
        .info-table td {
            color: #0f172a;
        }
        .message-box {
            background-color: #f8fafc;
            border-left: 4px solid #EDBF6D;
            padding: 15px;
            border-radius: 0 4px 4px 0;
            color: #334155;
            font-style: italic;
            white-space: pre-line;
        }
        .footer {
            background-color: #f1f5f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>A&O CORPORATION</h1>
            <p>Thông Báo Yêu Cầu Báo Giá Mới</p>
        </div>
        <div class="content">
            <h2 style="color: #0e2747; margin-top: 0; font-size: 18px;">Thông tin khách hàng liên hệ:</h2>
            <table class="info-table">
                <tr>
                    <th>Họ và Tên</th>
                    <td>{{ $inquiry['name'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Số Điện Thoại</th>
                    <td>{{ $inquiry['phone'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>{{ $inquiry['email'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Thời gian gửi</th>
                    <td>{{ now()->format('d/m/Y H:i:s') }}</td>
                </tr>
            </table>

            <h2 style="color: #0e2747; font-size: 18px;">Nội dung yêu cầu chi tiết:</h2>
            <div class="message-box">
                {{ $inquiry['message'] ?? 'Không có nội dung tin nhắn.' }}
            </div>
        </div>
        <div class="footer">
            Hệ thống quản lý website A&O Corporation<br>
            Email này được gửi tự động từ hệ thống abcpromallvn770.mbws.vn.
        </div>
    </div>
</body>
</html>
