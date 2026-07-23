<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dòng ngôn ngữ cho Validation
    |--------------------------------------------------------------------------
    |
    | Các dòng dưới đây chứa thông báo lỗi mặc định của validator. Một số quy tắc
    | có nhiều biến thể như quy tắc "size". Bạn có thể tùy chỉnh nội dung tại đây.
    |
    */

    'accepted' => 'Trường :attribute phải được chấp nhận.',
    'accepted_if' => 'Trường :attribute phải được chấp nhận khi :other là :value.',
    'active_url' => 'Trường :attribute phải là một URL hợp lệ.',
    'after' => 'Trường :attribute phải là ngày sau :date.',
    'after_or_equal' => 'Trường :attribute phải là ngày sau hoặc bằng :date.',
    'alpha' => 'Trường :attribute chỉ được chứa chữ cái.',
    'alpha_dash' => 'Trường :attribute chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới.',
    'alpha_num' => 'Trường :attribute chỉ được chứa chữ cái và số.',
    'any_of' => 'Trường :attribute không hợp lệ.',
    'array' => 'Trường :attribute phải là một mảng.',
    'ascii' => 'Trường :attribute chỉ được chứa ký tự và ký hiệu một byte.',
    'before' => 'Trường :attribute phải là ngày trước :date.',
    'before_or_equal' => 'Trường :attribute phải là ngày trước hoặc bằng :date.',
    'between' => [
        'array' => 'Trường :attribute phải có từ :min đến :max phần tử.',
        'file' => 'Trường :attribute phải có dung lượng từ :min đến :max kilobyte.',
        'numeric' => 'Trường :attribute phải có giá trị từ :min đến :max.',
        'string' => 'Trường :attribute phải có độ dài từ :min đến :max ký tự.',
    ],
    'boolean' => 'Trường :attribute phải là true hoặc false.',
    'can' => 'Trường :attribute chứa giá trị không được phép.',
    'confirmed' => 'Xác nhận :attribute không khớp.',
    'contains' => 'Trường :attribute thiếu một giá trị bắt buộc.',
    'current_password' => 'Mật khẩu không chính xác.',
    'date' => 'Trường :attribute phải là một ngày hợp lệ.',
    'date_equals' => 'Trường :attribute phải là ngày bằng :date.',
    'date_format' => 'Trường :attribute phải đúng định dạng :format.',
    'decimal' => 'Trường :attribute phải có :decimal chữ số thập phân.',
    'declined' => 'Trường :attribute phải bị từ chối.',
    'declined_if' => 'Trường :attribute phải bị từ chối khi :other là :value.',
    'different' => 'Trường :attribute và :other phải khác nhau.',
    'digits' => 'Trường :attribute phải có :digits chữ số.',
    'digits_between' => 'Trường :attribute phải có từ :min đến :max chữ số.',
    'dimensions' => 'Trường :attribute có kích thước hình ảnh không hợp lệ.',
    'distinct' => 'Trường :attribute có giá trị bị trùng lặp.',
    'doesnt_contain' => 'Trường :attribute không được chứa bất kỳ giá trị nào sau: :values.',
    'doesnt_end_with' => 'Trường :attribute không được kết thúc bằng một trong: :values.',
    'doesnt_start_with' => 'Trường :attribute không được bắt đầu bằng một trong: :values.',
    'email' => 'Trường :attribute phải là một địa chỉ email hợp lệ.',
    'encoding' => 'Trường :attribute phải được mã hóa theo :encoding.',
    'ends_with' => 'Trường :attribute phải kết thúc bằng một trong: :values.',
    'enum' => 'Giá trị :attribute đã chọn không hợp lệ.',
    'exists' => 'Giá trị :attribute đã chọn không hợp lệ.',
    'extensions' => 'Trường :attribute phải có một trong các phần mở rộng: :values.',
    'file' => 'Trường :attribute phải là một tệp tin.',
    'filled' => 'Trường :attribute phải có giá trị.',
    'gt' => [
        'array' => 'Trường :attribute phải có nhiều hơn :value phần tử.',
        'file' => 'Trường :attribute phải lớn hơn :value kilobyte.',
        'numeric' => 'Trường :attribute phải lớn hơn :value.',
        'string' => 'Trường :attribute phải dài hơn :value ký tự.',
    ],
    'gte' => [
        'array' => 'Trường :attribute phải có :value phần tử trở lên.',
        'file' => 'Trường :attribute phải lớn hơn hoặc bằng :value kilobyte.',
        'numeric' => 'Trường :attribute phải lớn hơn hoặc bằng :value.',
        'string' => 'Trường :attribute phải dài hơn hoặc bằng :value ký tự.',
    ],
    'hex_color' => 'Trường :attribute phải là một mã màu thập lục phân hợp lệ.',
    'image' => 'Trường :attribute phải là một hình ảnh.',
    'in' => 'Giá trị :attribute đã chọn không hợp lệ.',
    'in_array' => 'Trường :attribute phải tồn tại trong :other.',
    'in_array_keys' => 'Trường :attribute phải chứa ít nhất một trong các khóa: :values.',
    'integer' => 'Trường :attribute phải là một số nguyên.',
    'ip' => 'Trường :attribute phải là một địa chỉ IP hợp lệ.',
    'ipv4' => 'Trường :attribute phải là một địa chỉ IPv4 hợp lệ.',
    'ipv6' => 'Trường :attribute phải là một địa chỉ IPv6 hợp lệ.',
    'json' => 'Trường :attribute phải là một chuỗi JSON hợp lệ.',
    'list' => 'Trường :attribute phải là một danh sách.',
    'lowercase' => 'Trường :attribute phải là chữ thường.',
    'lt' => [
        'array' => 'Trường :attribute phải có ít hơn :value phần tử.',
        'file' => 'Trường :attribute phải nhỏ hơn :value kilobyte.',
        'numeric' => 'Trường :attribute phải nhỏ hơn :value.',
        'string' => 'Trường :attribute phải ngắn hơn :value ký tự.',
    ],
    'lte' => [
        'array' => 'Trường :attribute không được có nhiều hơn :value phần tử.',
        'file' => 'Trường :attribute phải nhỏ hơn hoặc bằng :value kilobyte.',
        'numeric' => 'Trường :attribute phải nhỏ hơn hoặc bằng :value.',
        'string' => 'Trường :attribute phải ngắn hơn hoặc bằng :value ký tự.',
    ],
    'mac_address' => 'Trường :attribute phải là một địa chỉ MAC hợp lệ.',
    'max' => [
        'array' => 'Trường :attribute không được có nhiều hơn :max phần tử.',
        'file' => 'Trường :attribute không được lớn hơn :max kilobyte.',
        'numeric' => 'Trường :attribute không được lớn hơn :max.',
        'string' => 'Trường :attribute không được dài hơn :max ký tự.',
    ],
    'max_digits' => 'Trường :attribute không được có nhiều hơn :max chữ số.',
    'mimes' => 'Trường :attribute phải là tệp tin có định dạng: :values.',
    'mimetypes' => 'Trường :attribute phải là tệp tin có định dạng: :values.',
    'min' => [
        'array' => 'Trường :attribute phải có ít nhất :min phần tử.',
        'file' => 'Trường :attribute phải có ít nhất :min kilobyte.',
        'numeric' => 'Trường :attribute phải có giá trị ít nhất :min.',
        'string' => 'Trường :attribute phải có ít nhất :min ký tự.',
    ],
    'min_digits' => 'Trường :attribute phải có ít nhất :min chữ số.',
    'missing' => 'Trường :attribute phải không tồn tại.',
    'missing_if' => 'Trường :attribute phải không tồn tại khi :other là :value.',
    'missing_unless' => 'Trường :attribute phải không tồn tại trừ khi :other là :value.',
    'missing_with' => 'Trường :attribute phải không tồn tại khi :values có mặt.',
    'missing_with_all' => 'Trường :attribute phải không tồn tại khi :values đều có mặt.',
    'multiple_of' => 'Trường :attribute phải là bội số của :value.',
    'not_in' => 'Giá trị :attribute đã chọn không hợp lệ.',
    'not_regex' => 'Định dạng trường :attribute không hợp lệ.',
    'numeric' => 'Trường :attribute phải là một số.',
    'password' => [
        'letters' => 'Trường :attribute phải chứa ít nhất một chữ cái.',
        'mixed' => 'Trường :attribute phải chứa ít nhất một chữ hoa và một chữ thường.',
        'numbers' => 'Trường :attribute phải chứa ít nhất một chữ số.',
        'symbols' => 'Trường :attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => 'Giá trị :attribute đã xuất hiện trong một vụ rò rỉ dữ liệu. Vui lòng chọn giá trị khác.',
    ],
    'present' => 'Trường :attribute phải có mặt.',
    'present_if' => 'Trường :attribute phải có mặt khi :other là :value.',
    'present_unless' => 'Trường :attribute phải có mặt trừ khi :other là :value.',
    'present_with' => 'Trường :attribute phải có mặt khi :values có mặt.',
    'present_with_all' => 'Trường :attribute phải có mặt khi :values đều có mặt.',
    'prohibited' => 'Trường :attribute bị cấm.',
    'prohibited_if' => 'Trường :attribute bị cấm khi :other là :value.',
    'prohibited_if_accepted' => 'Trường :attribute bị cấm khi :other được chấp nhận.',
    'prohibited_if_declined' => 'Trường :attribute bị cấm khi :other bị từ chối.',
    'prohibited_unless' => 'Trường :attribute bị cấm trừ khi :other nằm trong :values.',
    'prohibits' => 'Trường :attribute cấm :other xuất hiện.',
    'regex' => 'Định dạng trường :attribute không hợp lệ.',
    'required' => 'Trường :attribute là bắt buộc.',
    'required_array_keys' => 'Trường :attribute phải chứa các mục cho: :values.',
    'required_if' => 'Trường :attribute là bắt buộc khi :other là :value.',
    'required_if_accepted' => 'Trường :attribute là bắt buộc khi :other được chấp nhận.',
    'required_if_declined' => 'Trường :attribute là bắt buộc khi :other bị từ chối.',
    'required_unless' => 'Trường :attribute là bắt buộc trừ khi :other nằm trong :values.',
    'required_with' => 'Trường :attribute là bắt buộc khi :values có mặt.',
    'required_with_all' => 'Trường :attribute là bắt buộc khi :values đều có mặt.',
    'required_without' => 'Trường :attribute là bắt buộc khi :values không có mặt.',
    'required_without_all' => 'Trường :attribute là bắt buộc khi không có :values nào có mặt.',
    'same' => 'Trường :attribute phải khớp với :other.',
    'size' => [
        'array' => 'Trường :attribute phải chứa :size phần tử.',
        'file' => 'Trường :attribute phải có dung lượng :size kilobyte.',
        'numeric' => 'Trường :attribute phải có giá trị :size.',
        'string' => 'Trường :attribute phải có :size ký tự.',
    ],
    'starts_with' => 'Trường :attribute phải bắt đầu bằng một trong: :values.',
    'string' => 'Trường :attribute phải là một chuỗi ký tự.',
    'timezone' => 'Trường :attribute phải là một múi giờ hợp lệ.',
    'unique' => 'Giá trị :attribute đã tồn tại.',
    'uploaded' => 'Không thể tải lên :attribute.',
    'uppercase' => 'Trường :attribute phải là chữ hoa.',
    'url' => 'Trường :attribute phải là một URL hợp lệ.',
    'ulid' => 'Trường :attribute phải là một ULID hợp lệ.',
    'uuid' => 'Trường :attribute phải là một UUID hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Dòng ngôn ngữ tùy chỉnh cho Validation
    |--------------------------------------------------------------------------
    |
    | Bạn có thể chỉ định thông báo tùy chỉnh cho từng thuộc tính theo quy ước
    | "attribute.rule" để đặt tên cho các dòng.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tên thuộc tính tùy chỉnh
    |--------------------------------------------------------------------------
    |
    | Các dòng dưới đây được dùng để thay thế placeholder thuộc tính bằng tên
    | thân thiện hơn với người đọc.
    |
    */

    'attributes' => [],

];
