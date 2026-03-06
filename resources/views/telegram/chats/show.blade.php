@extends('telegram.layouts.app')

@section('title', '- ' . $chat->title)

@section('content')
<div class="card shadow-sm">
    <!-- Шапка чата -->
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <a href="{{ route('telegram.chats') }}" class="btn btn-outline-secondary btn-sm me-2">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h5 class="mb-0">{{ $chat->title }}</h5>
                <small class="text-muted">
                    {{ $chat->type }} • {{ $chat->participants_count ?? 0 }} участников
                </small>
            </div>
        </div>
        
        <div class="d-flex gap-2">
            @if($chat->isChannel())
                <a href="{{ route('telegram.post.create', $chat->id) }}" 
                   class="btn btn-success btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Новый пост
                </a>
            @endif
            
            <button class="btn btn-sm {{ $chat->is_excluded ? 'btn-warning' : 'btn-outline-secondary' }} toggle-exclude"
                    data-chat-id="{{ $chat->id }}"
                    data-excluded="{{ $chat->is_excluded ? 'true' : 'false' }}">
                <i class="bi {{ $chat->is_excluded ? 'bi-eye-slash' : 'bi-eye' }} me-1"></i>
                {{ $chat->is_excluded ? 'Включить' : 'Исключить' }}
            </button>
        </div>
    </div>
    
    <!-- Сообщения -->
    <div class="card-body bg-light" style="height: calc(100vh - 250px); overflow-y: auto;" id="messages-container">
        <div class="d-flex flex-column gap-3">
            @foreach($messages as $msg)
                <div class="d-flex {{ $msg->out ? 'justify-content-end' : 'justify-content-start' }}" 
                     id="message-{{ $msg->id }}">
                    <div class="message-bubble p-3 rounded-3 {{ $msg->out ? 'bg-primary text-white' : 'bg-white' }}"
                         style="max-width: 70%;">
                        
                        @if($msg->reply_to_msg_id)
                            <small class="d-block {{ $msg->out ? 'text-white-50' : 'text-muted' }} mb-1">
                                <i class="bi bi-reply"></i> Ответ на #{{ $msg->reply_to_msg_id }}
                            </small>
                        @endif
                        
                        <p class="mb-1">{{ $msg->text }}</p>
                        
                        <!-- Медиа с плейсхолдером -->
                        @if($msg->has_media)
                            <div class="mt-2 media-container" 
                                 data-message-id="{{ $msg->message_id }}"
                                 data-chat-id="{{ $chat->id }}"
                                 data-downloaded="{{ $msg->display_url ? 'true' : 'false' }}">
                                
                                @if($msg->display_url)
                                    <!-- Уже скачано -->
                                    @if($msg->media_type === 'video')
                                        <video src="{{ $msg->display_url }}" 
                                               controls
                                               class="img-fluid rounded-3" 
                                               style="max-height: 200px;">
                                            Ваш браузер не поддерживает видео.
                                        </video>
                                    @elseif($msg->media_type === 'audio')
                                        <audio src="{{ $msg->display_url }}" 
                                               controls
                                               class="w-100">
                                            Ваш браузер не поддерживает аудио.
                                        </audio>
                                    @else
                                        <img src="{{ $msg->display_url }}" 
                                             class="img-fluid rounded-3 loaded-image" 
                                             style="max-height: 200px; cursor: pointer;"
                                             alt="Изображение"
                                             onclick="openImage('{{ $msg->display_url }}')">
                                    @endif
                                @else
                                    <!-- Плейсхолдер с иконкой в зависимости от типа -->
                                    <div class="image-placeholder bg-light rounded-3 d-flex flex-column align-items-center justify-content-center"
                                         style="height: 150px; width: 200px; cursor: pointer;"
                                         onclick="downloadImage({{ $chat->id }}, {{ $msg->message_id }}, this)">
                                        
                                        <i class="bi {{ $msg->media_icon }} display-4 text-muted"></i>
                                        <span class="text-muted small mt-1">{{ $msg->media_type_text }}</span>
                                        
                                        @if($msg->file_size)
                                            <span class="text-muted small">{{ $msg->file_size }}</span>
                                        @endif
                                        
                                        <div class="spinner-border text-primary mt-2 d-none" role="status">
                                            <span class="visually-hidden">Загрузка...</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- КОММЕНТАРИИ К ПОСТУ -->
                        @if($chat->isChannel() && $msg->replies_count > 0)
                            <div class="comments-section mt-3 border-top pt-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-chat-text me-1"></i>
                                        {{ $msg->replies_count }} {{ trans_choice('комментарий|комментария|комментариев', $msg->replies_count) }}
                                    </small>
                                    <button class="btn btn-sm btn-link text-decoration-none toggle-comments" 
                                            data-post-id="{{ $msg->message_id }}">
                                        Показать
                                    </button>
                                </div>
                                
                                <div class="comments-list d-none" id="comments-{{ $msg->message_id }}">
                                    <div class="text-center py-2">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Загрузка...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        
                        <!-- Футер сообщения -->
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="{{ $msg->out ? 'text-white-50' : 'text-muted' }}">
                                {{ $msg->date->format('H:i') }}
                            </small>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm {{ $msg->out ? 'btn-light' : 'btn-outline-secondary' }} reply-btn"
                                        data-msg-id="{{ $msg->message_id }}">
                                    <i class="bi bi-reply"></i>
                                </button>
                                @if($msg->out)
                                    <button class="btn btn-sm {{ $msg->out ? 'btn-light' : 'btn-outline-secondary' }} edit-btn"
                                            data-msg-id="{{ $msg->message_id }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- Форма отправки -->
    <div class="card-footer bg-white">
        <form id="send-message-form" class="d-flex gap-2">
            @csrf
            <input type="text" id="message-input" 
                   class="form-control"
                   placeholder="Написать сообщение...">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i>
            </button>
        </form>
        
        <!-- Форма ответа (скрыта) -->
        <div id="reply-container" class="alert alert-secondary mt-2 mb-0 p-2 d-none">
            <div class="d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-reply me-1"></i> 
                    Ответ на сообщение #<span id="reply-to-id"></span>
                </span>
                <button type="button" class="btn-close btn-sm cancel-reply"></button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    let currentReplyTo = null;
    
    // Скролл вниз
    let container = $('#messages-container');
    container.scrollTop(container[0].scrollHeight);
    
    // Переключение исключения чата
    $('.toggle-exclude').click(function() {
        let btn = $(this);
        let chatId = {{ $chat->id }};
        let excluded = btn.data('excluded') === 'true';
        
        $.ajax({
            url: '/telegram/api/chats/' + chatId + (excluded ? '/include' : '/exclude'),
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
    
    // Отправка сообщения
    $('#send-message-form').submit(function(e) {
        e.preventDefault();
        
        let message = $('#message-input').val().trim();
        if (!message) return;
        
        $.ajax({
            url: '{{ route("telegram.chat.send", $chat->id) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                message: message,
                reply_to: currentReplyTo
            },
            success: function() {
                $('#message-input').val('');
                cancelReply();
                setTimeout(() => location.reload(), 1000);
            }
        });
    });
    
    // Ответ на сообщение
    $('.reply-btn').click(function() {
        currentReplyTo = $(this).data('msg-id');
        $('#reply-to-id').text(currentReplyTo);
        $('#reply-container').removeClass('d-none');
        $('#message-input').focus();
    });
    
    // Отмена ответа
    $('.cancel-reply').click(cancelReply);
    
    function cancelReply() {
        currentReplyTo = null;
        $('#reply-container').addClass('d-none');
    }
    
    // Обновление новых сообщений
    setInterval(function() {
        let lastMsgId = $('.message-bubble').last().data('msg-id') || 0;
        
        $.get('/telegram/api/chat/{{ $chat->id }}/messages?after=' + lastMsgId, function(messages) {
            if (messages.length > 0) {
                location.reload();
            }
        });
    }, 60000);

    // Загрузка комментариев при клике
    $('.toggle-comments').click(function() {
        const btn = $(this);
        const postId = btn.data('post-id');
        const commentsList = $('#comments-' + postId);
        
        if (btn.text() === 'Показать') {
            btn.text('Скрыть');
            commentsList.removeClass('d-none');
            
            $.get('/telegram/api/posts/' + postId + '/comments', function(data) {
                commentsList.empty();
                
                if (data.comments.length === 0) {
                    commentsList.html('<div class="text-muted text-center py-2">Нет комментариев</div>');
                    return;
                }
                
                data.comments.forEach(function(comment) {
                    commentsList.append(`
                        <div class="comment-item mb-2 p-2 bg-light rounded">
                            <div class="d-flex justify-content-between">
                                <strong>${comment.user_name || 'Пользователь'}</strong>
                                <small class="text-muted">${new Date(comment.date).toLocaleTimeString()}</small>
                            </div>
                            <p class="mb-0 small">${comment.text}</p>
                        </div>
                    `);
                });
            }).fail(function() {
                commentsList.html('<div class="text-danger text-center py-2">Ошибка загрузки комментариев</div>');
            });
        } else {
            btn.text('Показать');
            commentsList.addClass('d-none');
        }
    });
});

// Функция скачивания изображения (исправлена - передаём chatId и messageId)
function downloadImage(chatId, messageId, element) {
    const container = $(element).closest('.media-container');
    const placeholder = $(element);
    const spinner = placeholder.find('.spinner-border');
    
    placeholder.find('i, span').hide();
    spinner.removeClass('d-none');
    
    // Добавляем текстовое уведомление
    const statusText = $('<div class="text-muted small mt-1">Подключение к серверу...</div>');
    placeholder.append(statusText);
    
    const progressContainer = $('<div class="progress mt-2" style="height: 20px; display: none;">' +
        '<div class="progress-bar progress-bar-striped progress-bar-animated" ' +
        'role="progressbar" style="width: 0%">0%</div></div>');
    
    const startTime = Date.now();
    const waitingTimer = setInterval(() => {
        const elapsed = Math.round((Date.now() - startTime) / 1000);
        if (elapsed < 30) {
            statusText.text(`Ожидание ответа (${elapsed} сек)...`);
        } else {
            statusText.text(`Соединение устанавливается... (${elapsed} сек)`).addClass('text-warning');
        }
    }, 1000);
    
    // ИСПРАВЛЕННЫЙ URL с chatId и messageId
    const downloadUrl = '{{ route("telegram.download", ["chatId" => ":chatId", "messageId" => ":messageId"]) }}'
        .replace(':chatId', chatId)
        .replace(':messageId', messageId);
    
    console.log('Downloading from:', downloadUrl);
    
    $.ajax({
        url: downloadUrl,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        dataType: 'json',
        success: function(data) {
            clearInterval(waitingTimer);
            statusText.remove();
            spinner.addClass('d-none');
            
            if (data.success) {
                if (data.cached) {
                    // Файл уже скачан
                    if (data.type === 'video') {
                        container.html(`
                            <video src="${data.url}" controls 
                                class="img-fluid rounded-3" 
                                style="max-height: 200px;">
                                Ваш браузер не поддерживает видео.
                            </video>
                        `);
                    } else if (data.type === 'audio') {
                        container.html(`
                            <audio src="${data.url}" controls 
                                class="w-100">
                                Ваш браузер не поддерживает аудио.
                            </audio>
                        `);
                    } else {
                        container.html(
                            '<img src="' + data.url + '" ' +
                            'class="img-fluid rounded-3 loaded-image" ' +
                            'style="max-height: 200px; cursor: pointer;" ' +
                            'alt="Изображение" ' +
                            'onclick="openImage(\'' + data.url + '\')">'
                        );
                    }
                } else {
                    // Новый файл - показываем прогресс
                    placeholder.after(progressContainer);
                    progressContainer.show();
                    
                    let percent = 0;
                    const interval = setInterval(() => {
                        percent += Math.random() * 15;
                        if (percent >= 100) {
                            percent = 100;
                            clearInterval(interval);
                            
                            setTimeout(() => {
                                progressContainer.remove();
                                if (data.type === 'video') {
                                    container.html(`
                                        <video src="${data.url}" controls 
                                            class="img-fluid rounded-3" 
                                            style="max-height: 200px;">
                                            Ваш браузер не поддерживает видео.
                                        </video>
                                    `);
                                } else if (data.type === 'audio') {
                                    container.html(`
                                        <audio src="${data.url}" controls 
                                            class="w-100">
                                            Ваш браузер не поддерживает аудио.
                                        </audio>
                                    `);
                                } else {
                                    container.html(
                                        '<img src="' + data.url + '" ' +
                                        'class="img-fluid rounded-3 loaded-image" ' +
                                        'style="max-height: 200px; cursor: pointer;" ' +
                                        'alt="Изображение" ' +
                                        'onclick="openImage(\'' + data.url + '\')">'
                                    );
                                }
                            }, 500);
                        }
                        progressContainer.find('.progress-bar')
                            .css('width', percent + '%')
                            .text(Math.round(percent) + '%');
                    }, 100);
                }
            } else {
                placeholder.find('i, span').show();
                spinner.addClass('d-none');
                progressContainer.remove();
                alert('Ошибка загрузки: ' + (data.error || 'Неизвестная ошибка'));
            }
        },
        error: function(xhr, status, error) {
            clearInterval(waitingTimer);
            statusText.remove();
            
            console.error('Download error:', error);
            placeholder.find('i, span').show();
            spinner.addClass('d-none');
            progressContainer.remove();
            
            const elapsed = Math.round((Date.now() - startTime) / 1000);
            alert(`Ошибка соединения через ${elapsed} сек: ` + error);
        }
    });
}

// Функция открытия изображения
function openImage(url) {
    window.open(url, '_blank');
}
</script>
@endsection