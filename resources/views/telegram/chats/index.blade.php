@extends('telegram.layouts.app')

@section('title', '- Чаты')

@section('content')
<div class="row g-3">
    <!-- Левая колонка: список чатов -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Чаты</h5>
                <select id="sort-chats" class="form-select form-select-sm w-auto">
                    <option value="default" {{ $currentSort == 'default' ? 'selected' : '' }}>По дате</option>
                    <option value="alphabet" {{ $currentSort == 'alphabet' ? 'selected' : '' }}>По алфавиту</option>
                    <option value="excluded" {{ $currentSort == 'excluded' ? 'selected' : '' }}>Сначала исключённые</option>
                </select>
            </div>
            <div class="card-body p-0">
                <div class="p-2">
                    <input type="text" id="search-chats" class="form-control form-control-sm" 
                           placeholder="Поиск чатов...">
                </div>
                <div class="chat-list" id="chat-list">
                    @forelse($chats as $chat)
                        <div class="list-group-item list-group-item-action p-2 {{ $chat->is_excluded ? 'excluded-chat' : '' }}"
                             data-chat-id="{{ $chat->id }}"
                             data-title="{{ $chat->title }}"
                             data-type="{{ $chat->type }}"
                             onclick="window.location='{{ route('telegram.chat.show', $chat->id) }}'"
                             style="cursor: pointer;">
                            
                            <div class="d-flex align-items-center">
                                <!-- Аватар -->
                                <div class="flex-shrink-0 me-2">
                                    <div class="bg-secondary bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        @if($chat->type == 'private')
                                            <i class="bi bi-person-fill text-secondary"></i>
                                        @elseif($chat->type == 'group')
                                            <i class="bi bi-people-fill text-secondary"></i>
                                        @else
                                            <i class="bi bi-megaphone-fill text-secondary"></i>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Информация -->
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 text-truncate">{{ $chat->title }}</h6>
                                        @if($chat->unread_count > 0)
                                            <span class="badge bg-primary rounded-pill ms-2">
                                                {{ $chat->unread_count }}
                                            </span>
                                        @endif
                                    </div>
                                    <small class="text-muted d-block text-truncate">
                                        @if($chat->last_message)
                                            {{ $chat->last_message }}
                                        @else
                                            Нет сообщений
                                        @endif
                                    </small>
                                    <small class="text-muted">
                                        {{ $chat->last_message_date ? $chat->last_message_date->diffForHumans() : 'никогда' }}
                                        @if($chat->is_excluded)
                                            <span class="badge bg-warning text-dark ms-1">исключён</span>
                                        @endif
                                    </small>
                                </div>
                                
                                <!-- Кнопка исключения -->
                                <div class="flex-shrink-0 ms-2">
                                    <button class="btn btn-sm btn-outline-secondary toggle-exclude"
                                            data-chat-id="{{ $chat->id }}"
                                            data-excluded="{{ $chat->is_excluded ? 'true' : 'false' }}"
                                            onclick="event.stopPropagation()">
                                        <i class="bi {{ $chat->is_excluded ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-chat-dots display-4"></i>
                            <p class="mt-2">Нет чатов</p>
                        </div>
                    @endforelse
                </div>
                <div class="card-footer bg-white">
                    {{ $chats->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
    
    <!-- Правая колонка: информация о выбранном чате -->
    <div class="col-md-8">
        <div class="card shadow-sm bg-light">
            <div class="card-body text-center py-5">
                <i class="bi bi-chat-dots display-1 text-muted"></i>
                <p class="text-muted mt-3">Выберите чат для просмотра сообщений</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    // Сортировка чатов
    $('#sort-chats').change(function() {
        window.location.href = '?sort=' + $(this).val();
    });
    
    // Поиск чатов
    $('#search-chats').on('keyup', function() {
        let search = $(this).val().toLowerCase();
        
        $('.list-group-item').each(function() {
            let title = $(this).data('title').toLowerCase();
            $(this).toggle(title.includes(search));
        });
    });
    
    // Переключение исключения чата
    $('.toggle-exclude').click(function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        let btn = $(this);
        let chatId = btn.data('chat-id');
        let excluded = btn.data('excluded') === 'true';
        
        $.ajax({
            url: '/api/chats/' + chatId + (excluded ? '/include' : '/exclude'),
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                reason: excluded ? null : 'Вручную'
            },
            success: function() {
                location.reload();
            }
        });
    });
});
</script>
@endsection