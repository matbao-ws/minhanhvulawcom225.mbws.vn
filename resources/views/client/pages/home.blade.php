@extends('client.layouts.app')

@section('title', 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ | Quản trị rủi ro pháp lý cùng bạn - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ')
@section('meta_description', 'Công ty Luật TNHH MTV Minh Anh Vũ (MAV Law) - Quản trị rủi ro pháp lý cùng bạn. Chuyên sâu Tranh tụng, Pháp lý Doanh nghiệp, Dịch vụ giấy phép, Thuế và Di trú.')

@section('content')
  @php
    $homeSections = [
      'hero',
      'about-overview',
      'why-choose-us',
      'practice-areas',
      'latest-news',
      'consultation-form',
      'partners',
    ];
  @endphp
  <x-client::section-list key="home.sections" :sections="$homeSections" />
@endsection
