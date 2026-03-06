@extends('telegram.layouts.app')

@section('title', '- Исключённые чаты')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Исключённые чаты</h5>
        <small class="text-muted">Чаты, которые не парсятся автоматически</small>
    </div>
    
    <div class="card-body p-0">
        @if($chats->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-archive display-1"></i>
                <p class="mt-3">Нет исключённых чатов</p>
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach($chats as $chat)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="bg-secondary bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 40px; height: 40px;">
                                @if($chat->type == 'private')
                                    <i class="bi bi-person-fill text-secondary"></i>
                                @elseif($chat->type == 'group')
                                    <i class="bi bi-people-fill text-secondary"></i>
                                @else
                                    <i class="bi bi-megaphone-fill text-secondary"></i>
                                @endif
                            </div>
                            <div>
                                <h6 class="mb-0">{{ $chat->title }}</h6>
                                <small class="text-muted">
                                    Исключён: {{ $chat->excluded_at?->diffForHumans() }}
                                    @if($chat->excluded_reason)
                                        • Причина: {{ $chat->excluded_reason }}
                                    @endif
                                </small>
                            </div>
                        </div>
                        
                        <button class="btn btn-primary btn-sm include-chat"
                                data-chat-id="{{ $chat->id }}">
                            <i class="bi bi-arrow-return-left me-1"></i>Вернуть
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    $('.include-chat').click(function() {
        let chatId = $(this).data('chat-id');
        let btn = $(this);
        
        $.ajax({
            url: '/telegram/api/chats/' + chatId + '/include',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function() {
                btn.closest('.list-group-item').fadeOut();
            }
        });
    });
});
</script>
@endsection