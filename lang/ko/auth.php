<?php

return [
    'failed' => '로그인 정보가 일치하지 않거나 계정이 비활성화되었습니다.',
    'password' => '입력하신 비밀번호가 올바르지 않습니다.',
    'throttle' => '로그인 시도가 너무 많습니다. :seconds초 후에 다시 시도해 주세요.',
    'login' => [
        'title' => '로그인',
        'heading' => '관리자 로그인',
        'email' => '이메일 주소',
        'email_placeholder' => '이메일을 입력하세요',
        'password' => '비밀번호',
        'password_placeholder' => '비밀번호를 입력하세요',
        'forgot_password' => '비밀번호를 잊으셨나요?',
        'remember' => '로그인 상태 유지',
        'submit' => '로그인',
        'processing' => '로그인 중...',
        'success' => '로그인 성공.',
        'request_failed' => '로그인 요청을 전송할 수 없습니다. 다시 시도해 주세요.',
        'email_not_found' => '이메일이 시스템에 존재하지 않습니다.',
        'inactive' => '이 계정은 잠겨 있거나 활성화되지 않았습니다.',
        'password_incorrect' => '비밀번호가 올바르지 않습니다.',
        'unauthorized' => '이 계정은 관리자 패널에 액세스할 수 있는 권한이 없습니다.',

        'slides' => [
            [
                'title' => '통합 전자상거래 관리',
                'description' => '하나의 관리 영역에서 패키지 상태, 활성화된 기능 및 운영 설정을 관리합니다.',
            ],
            [
                'title' => '모듈 준비 완료 기반',
                'description' => '코어 아키텍처를 변경하지 않고 서비스 패키지별로 기능을 제한합니다.',
            ],
            [
                'title' => '클라이언트 API 준비 완료',
                'description' => 'Laravel 코어는 블레이드 관리자 화면과 스토어프론트 클라이언트를 위한 REST API를 제공합니다.',
            ],
        ],
    ],
    'forgot' => [
        'title' => '비밀번호 찾기',
        'description' => '계정에 연결된 이메일 주소를 입력해 주세요. 비밀번호 재설정 링크를 이메일로 보내드립니다.',
        'submit' => '비밀번호 재설정 링크 전송',
        'processing' => '전송 중...',
        'back_to_login' => '로그인으로 돌아가기',
        'request_failed' => '비밀번호 재설정 요청을 전송할 수 없습니다. 다시 시도해 주세요.',
        'slides' => [
            ['title' => '관리자 액세스 보호'],
            ['title' => '이메일로 비밀번호 재설정'],
            ['title' => '전자상거래 코어 관리 계속'],
        ],
    ],
    'reset' => [
        'title' => '비밀번호 재설정',
        'heading' => '비밀번호 재설정',
        'password' => '새 비밀번호',
        'password_confirmation' => '비밀번호 확인',
        'submit' => '비밀번호 업데이트',
    ],
    'logout' => [
        'success' => '로그아웃 성공.',
    ],
];
