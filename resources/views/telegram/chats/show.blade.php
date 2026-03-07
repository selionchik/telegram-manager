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
                @if ($chat->isChannel())
                    <a href="{{ route('telegram.post.create', $chat->id) }}" class="btn btn-success btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>Новый пост
                    </a>
                @endif

                <button class="btn btn-sm {{ $chat->is_excluded ? 'btn-warning' : 'btn-outline-secondary' }} toggle-exclude"
                    data-chat-id="{{ $chat->id }}" data-excluded="{{ $chat->is_excluded ? 'true' : 'false' }}">
                    <i class="bi {{ $chat->is_excluded ? 'bi-eye-slash' : 'bi-eye' }} me-1"></i>
                    {{ $chat->is_excluded ? 'Включить' : 'Исключить' }}
                </button>
            </div>
        </div>

        <!-- Сообщения -->
        <div class="card-body bg-light" style="height: calc(100vh - 250px); overflow-y: auto;" id="messages-container">
            <div class="d-flex flex-column gap-3">
                @foreach ($messages as $msg)
                    <div class="d-flex {{ $msg->out ? 'justify-content-end' : 'justify-content-start' }}"
                        id="message-{{ $msg->id }}">
                        <div class="message-bubble p-3 rounded-3 {{ $msg->out ? 'bg-primary text-white' : 'bg-white' }}"
                            style="max-width: 70%;">

                            @if ($msg->reply_to_msg_id)
                                <small class="d-block {{ $msg->out ? 'text-white-50' : 'text-muted' }} mb-1">
                                    <i class="bi bi-reply"></i> Ответ на #{{ $msg->reply_to_msg_id }}
                                </small>
                            @endif

                            <p class="mb-1">{{ $msg->text }}</p>
                            {{-- @dump($msg->media_type ) --}}
                            <!-- Медиа с плейсхолдером -->
                            @if ($msg->has_media)
                                <div class="mt-2 media-container" data-message-id="{{ $msg->message_id }}"
                                    data-chat-id="{{ $chat->id }}"
                                    data-downloaded="{{ $msg->display_url ? 'true' : 'false' }}">

                                    @if ($msg->display_url)
                                        <!-- Уже скачано -->
                                        @if ($msg->media_type === 'video')
                                            <video src="{{ $msg->display_url }}" controls class="img-fluid rounded-3"
                                                style="max-height: 200px;">
                                                Ваш браузер не поддерживает видео.
                                            </video>
                                        @elseif($msg->media_type === 'audio')
                                            <audio src="{{ $msg->display_url }}" controls class="w-100">
                                                Ваш браузер не поддерживает аудио.
                                            </audio>
                                        @else
                                            <img src="{{ $msg->display_url }}" class="img-fluid rounded-3 loaded-image"
                                                style="max-height: 200px; cursor: pointer;" alt="Изображение"
                                                onclick="openImage('{{ $msg->display_url }}')">
                                        @endif
                                    @else
                                        <!-- Плейсхолдер с иконкой в зависимости от типа -->
                                        <div class="image-placeholder bg-light rounded-3 d-flex flex-column align-items-center justify-content-center"
                                            style="height: 150px; width: 200px; cursor: pointer;"
                                            onclick="downloadImage({{ $chat->id }}, {{ $msg->message_id }}, this)">

                                            <i class="bi {{ $msg->media_icon }} display-4 text-muted"></i>
                                            <span class="text-muted small mt-1">{{ $msg->media_type_text }}</span>

                                            @if ($msg->file_size)
                                                <span class="text-muted small">{{ $msg->file_size }}</span>
                                            @endif

                                            <div class="spinner-border text-primary mt-2 d-none" role="status">
                                                <span class="visually-hidden">Загрузка...</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <!-- КОММЕНТАРИИ К ПОСТУ (только для каналов) -->
                            @if ($chat->type === 'channel')
                                <div class="comments-section mt-3 border-top pt-2">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-chat-text me-1"></i>
                                            Комментарии
                                            @if (isset($commentCounts[$msg->message_id]) && $commentCounts[$msg->message_id] > 0)
                                                <span class="badge bg-secondary rounded-pill ms-1">
                                                    {{ $commentCounts[$msg->message_id] }}
                                                </span>
                                            @endif
                                        </small>
                                        <button class="btn btn-sm btn-link text-decoration-none toggle-comments"
                                            data-post-id="{{ $msg->message_id }}"
                                            data-url="{{ route('telegram.post.comments', ['chatId' => $chat->id, 'postId' => $msg->message_id]) }}">
                                            {{ isset($commentCounts[$msg->message_id]) && $commentCounts[$msg->message_id] > 0 ? 'Обновить' : 'Показать' }}
                                        </button>
                                    </div>

                                    <div class="comments-list d-none" id="comments-{{ $msg->message_id }}">
                                        @if (isset($commentCounts[$msg->message_id]) && $commentCounts[$msg->message_id] > 0)
                                            <!-- Если уже загружены, показываем сразу -->
                                            @foreach ($loadedComments[$msg->message_id] ?? [] as $comment)
                                                <div class="comment-item mb-2 p-2 bg-light rounded">
                                                    <div class="d-flex justify-content-between">
                                                        <strong>{{ $comment->user_name ?? 'Пользователь' }}</strong>
                                                        <small
                                                            class="text-muted">{{ $comment->date->format('H:i') }}</small>
                                                    </div>
                                                    <p class="mb-0 small">{{ $comment->text }}</p>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <!-- Футер сообщения -->
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <small class="{{ $msg->out ? 'text-white-50' : 'text-muted' }}">
                                    {{ $msg->date->format('H:i') }}
                                </small>
                                <div class="d-flex gap-2">
                                    <button
                                        class="btn btn-sm {{ $msg->out ? 'btn-light' : 'btn-outline-secondary' }} reply-btn"
                                        data-msg-id="{{ $msg->message_id }}">
                                        <i class="bi bi-reply"></i>
                                    </button>
                                    @if ($msg->out)
                                        <button
                                            class="btn btn-sm {{ $msg->out ? 'btn-light' : 'btn-outline-secondary' }} edit-btn"
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
                <input type="text" id="message-input" class="form-control" placeholder="Написать сообщение...">
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
                    url: '{{ route('telegram.chat.send', $chat->id) }}',
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

                $.get('/telegram/api/chat/{{ $chat->id }}/messages?after=' + lastMsgId, function(
                    messages) {
                    if (messages.length > 0) {
                        location.reload();
                    }
                });
            }, 60000);

            // Загрузка комментариев при клике
            $('.toggle-comments').click(function() {
                const btn = $(this);
                const postId = btn.data('post-id');
                const url = btn.data('url');
                const commentsList = $('#comments-' + postId);

                if (btn.text().trim() === 'Показать') {
                    btn.text('Скрыть');
                    commentsList.removeClass('d-none');

                    // Всегда загружаем свежие комментарии
                    commentsList.html(
                        '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Загрузка...</span></div></div>'
                    );

                    $.get(url, function(data) {
                        commentsList.empty();

                        if (data.comments.length === 0) {
                            commentsList.html(
                                '<div class="text-muted text-center py-2">Нет комментариев</div>'
                            );
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
                        commentsList.html(
                            '<div class="text-danger text-center py-2">Ошибка загрузки комментариев</div>'
                        );
                    });

                } else {
                    btn.text('Показать');
                    commentsList.addClass('d-none');
                    // Не очищаем список
                }
            });
        });

        // Функция скачивания изображения (исправлена - передаём chatId и messageId)
        // Функция скачивания изображения
        // function downloadImage(chatId, messageId, element) {
        //     const container = $(element).closest('.media-container');
        //     const placeholder = $(element);
        //     const spinner = placeholder.find('.spinner-border');

        //     // Скрываем иконку и показываем спиннер
        //     placeholder.find('i, span').hide();
        //     spinner.removeClass('d-none');

        //     // Создаем контейнер для прогресса, если его нет
        //     let progressContainer = container.find('.download-progress');
        //     if (progressContainer.length === 0) {
        //         progressContainer = $('<div class="download-progress mt-2"></div>');
        //         container.append(progressContainer);
        //     }
        //     progressContainer.html('<div class="progress"><div class="progress-bar" style="width:0%">0%</div></div>');
        //     const progressBar = progressContainer.find('.progress-bar');

        //     // URL для запроса на скачивание (к вашему контроллеру)
        //     const downloadUrl = '{{ route('telegram.download', ['chatId' => ':chatId', 'messageId' => ':messageId']) }}'
        //         .replace(':chatId', chatId)
        //         .replace(':messageId', messageId);

        //     // 1. Запускаем скачивание в фоне (fetch без ожидания ответа)
        //     fetch(downloadUrl, {
        //         method: 'POST',
        //         headers: {
        //             'X-CSRF-TOKEN': '{{ csrf_token() }}'
        //         }
        //         // Не ждем тело ответа, нам нужен только запуск процесса
        //     }).catch(error => console.error('Download fetch error:', error));

        //     // 2. Запускаем опрос прогресса
        //     // Опрашиваем прогресс
        //     const progressUrl =
        //         '{{ route('telegram.download.progress', ['chatId' => ':chatId', 'messageId' => ':messageId']) }}'
        //         .replace(':chatId', chatId)
        //         .replace(':messageId', messageId);

        //     const progressInterval = setInterval(() => {
        //         $.get(progressUrl, function(data) {
        //             if (data.progress !== undefined) {
        //                 progressBar.css('width', data.progress + '%').text(data.progress + '%');
        //             }

        //             if (data.done) {
        //                 clearInterval(progressInterval);
        //                 progressContainer.remove();
        //                 spinner.addClass('d-none');

        //                 const imgHtml =
        //                     `<img src="${data.url}" class="img-fluid rounded-3 loaded-image" style="max-height: 200px; cursor: pointer;" alt="Изображение" onclick="openImage('${data.url}')">`;
        //                 container.html(imgHtml);
        //             }
        //         }).fail(function() {
        //             console.log('Progress poll failed, retrying...');
        //         });
        //     }, 800); // Опрашиваем каждые 800 мс
        // }
        // function downloadImage(chatId, messageId, element) {
        //     const container = $(element).closest('.media-container');
        //     const placeholder = $(element);
        //     const spinner = placeholder.find('.spinner-border');

        //     // Скрываем иконку и показываем спиннер
        //     placeholder.find('i, span').hide();
        //     spinner.removeClass('d-none');

        //     // Создаем контейнер для прогресса, если его нет
        //     let progressContainer = container.find('.download-progress');
        //     if (progressContainer.length === 0) {
        //         progressContainer = $('<div class="download-progress mt-2"></div>');
        //         container.append(progressContainer);
        //     }
        //     progressContainer.html('<div class="progress"><div class="progress-bar" style="width:0%">0%</div></div>');
        //     const progressBar = progressContainer.find('.progress-bar');

        //     // URL для запроса на скачивание
        //     const downloadUrl = '{{ route('telegram.download', ['chatId' => ':chatId', 'messageId' => ':messageId']) }}'
        //         .replace(':chatId', chatId)
        //         .replace(':messageId', messageId);

        //     console.log(`🚀 [${chatId}/${messageId}] Запуск скачивания: ${downloadUrl}`);

        //     // 1. Запускаем скачивание в фоне
        //     fetch(downloadUrl, {
        //         method: 'POST',
        //         headers: {
        //             'X-CSRF-TOKEN': '{{ csrf_token() }}'
        //         }
        //     })
        //     .then(response => {
        //         console.log(`📥 [${chatId}/${messageId}] Ответ получен, статус: ${response.status}`);

        //         // Получаем reader для чтения чанков
        //         const reader = response.body.getReader();
        //         const contentLength = response.headers.get('Content-Length');
        //         const total = contentLength ? parseInt(contentLength, 10) : 0;

        //         console.log(`📊 [${chatId}/${messageId}] Content-Length: ${total} байт`);

        //         let receivedLength = 0;
        //         let chunkCount = 0;

        //         function readChunk() {
        //             reader.read().then(({ done, value }) => {
        //                 if (done) {
        //                     console.log(`✅ [${chatId}/${messageId}] Загрузка завершена, всего чанков: ${chunkCount}, получено байт: ${receivedLength}`);
        //                     return;
        //                 }

        //                 chunkCount++;
        //                 receivedLength += value.length;

        //                 const percent = total > 0 ? Math.round((receivedLength / total) * 100) : 0;
        //                 console.log(`📦 [${chatId}/${messageId}] Чанк #${chunkCount}: +${value.length} байт, всего ${receivedLength}/${total} (${percent}%)`);

        //                 readChunk();
        //             }).catch(error => {
        //                 console.error(`❌ [${chatId}/${messageId}] Ошибка чтения чанка:`, error);
        //             });
        //         }

        //         readChunk();
        //     })
        //     .catch(error => console.error('❌ Download fetch error:', error));

        //     // 2. Запускаем опрос прогресса
        //     const progressUrl = '{{ route('telegram.download.progress', ['chatId' => ':chatId', 'messageId' => ':messageId']) }}'
        //         .replace(':chatId', chatId)
        //         .replace(':messageId', messageId);

        //     console.log(`🔄 [${chatId}/${messageId}] Начинаем опрос прогресса: ${progressUrl}`);

        //     const progressInterval = setInterval(() => {
        //         $.get(progressUrl, function(data) {
        //             if (data.progress !== undefined) {
        //                 console.log(`📈 [${chatId}/${messageId}] Прогресс от сервера: ${data.progress}%`);
        //                 progressBar.css('width', data.progress + '%').text(data.progress + '%');
        //             }

        //             if (data.done) {
        //                 console.log(`🎉 [${chatId}/${messageId}] Загрузка завершена, URL: ${data.url}`);
        //                 clearInterval(progressInterval);
        //                 progressContainer.remove();
        //                 spinner.addClass('d-none');

        //                 const imgHtml = `<img src="${data.url}" class="img-fluid rounded-3 loaded-image" style="max-height: 200px; cursor: pointer;" alt="Изображение" onclick="openImage('${data.url}')">`;
        //                 container.html(imgHtml);
        //             }
        //         }).fail(function(xhr, status, error) {
        //             console.log(`⚠️ [${chatId}/${messageId}] Ошибка опроса прогресса:`, { status, error });
        //         });
        //     }, 800);
        // }

        // //рабочий костыль
        // function downloadImage(chatId, messageId, element) {
        //     const container = $(element).closest('.media-container');
        //     const placeholder = $(element);
        //     const spinner = placeholder.find('.spinner-border');

        //     placeholder.find('i, span').hide();
        //     spinner.removeClass('d-none');

        //     // Создаем контейнер для прогресса
        //     let progressContainer = container.find('.download-progress');
        //     if (progressContainer.length === 0) {
        //         progressContainer = $('<div class="download-progress mt-2"></div>');
        //         container.append(progressContainer);
        //     }
        //     progressContainer.html('<div class="progress"><div class="progress-bar" style="width:0%">0%</div></div>');
        //     const progressBar = progressContainer.find('.progress-bar');

        //     const sizeUrl = '{{ route('telegram.download.size', ['chatId' => ':chatId', 'messageId' => ':messageId']) }}'
        //         .replace(':chatId', chatId)
        //         .replace(':messageId', messageId);

        //     const downloadUrl = '{{ route('telegram.download', ['chatId' => ':chatId', 'messageId' => ':messageId']) }}'
        //         .replace(':chatId', chatId)
        //         .replace(':messageId', messageId);

        //     const progressUrl =
        //         '{{ route('telegram.download.progress', ['chatId' => ':chatId', 'messageId' => ':messageId']) }}'
        //         .replace(':chatId', chatId)
        //         .replace(':messageId', messageId);

        //     console.log(`🚀 [${chatId}/${messageId}] Запрашиваем размер...`);

        //     // Сначала узнаём размер
        //     $.get(sizeUrl, function(sizeData) {
        //         const totalSize = sizeData.size;
        //         const chunkSize = sizeData.chunkSize;
        //         console.log(`📊 [${chatId}/${messageId}] Размер файла: ${totalSize} байт`);

        //         // Запускаем скачивание
        //         console.log(`📥 [${chatId}/${messageId}] Запуск скачивания`);
        //         fetch(downloadUrl, {
        //             method: 'POST',
        //             headers: {
        //                 'X-CSRF-TOKEN': '{{ csrf_token() }}'
        //             }
        //         }).catch(error => console.error('Download error:', error));

        //         let step=1;
        //         // Опрашиваем прогресс
        //         const progressInterval = setInterval(() => {
        //             $.get(progressUrl, function(data) {
        //                 // let percent = Math.round((chunkSize / totalSize) * 100 * step);
        //                     // progressBar.css('width', percent + '%').text(percent + '%');
        //                     // step++;
        //                 if (data.progress !== undefined) {
        //                     progressBar.css('width', data.progress + '%').text(data.progress + '%');
        //                     console.log(`📈 [${chatId}/${messageId}] Прогресс: ${data.progress}%`);
        //                 }

        //                 if (data.done) {
        //                     clearInterval(progressInterval);
        //                     progressContainer.remove();
        //                     spinner.addClass('d-none');

        //                     const imgHtml =
        //                         `<img src="${data.url}" class="img-fluid rounded-3 loaded-image" style="max-height: 200px; cursor: pointer;" alt="Изображение" onclick="openImage('${data.url}')">`;
        //                     container.html(imgHtml);
        //                     console.log(`🎉 [${chatId}/${messageId}] Готово`);
        //                 }
        //             }).fail(() => console.log('Progress poll failed'));
        //         }, 500);

        //     }).fail(function() {
        //         console.error('❌ Не удалось получить размер файла');
        //         // fallback — запускаем без размера
        //         fetch(downloadUrl, {
        //                 method: 'POST',
        //                 headers: {
        //                     'X-CSRF-TOKEN': '{{ csrf_token() }}'
        //                 }
        //             })
        //             .catch(error => console.error('Download error:', error));

        //         // тот же опрос прогресса
        //         const progressInterval = setInterval(() => {
        //             $.get(progressUrl, function(data) {
        //                 if (data.progress !== undefined) {
        //                     progressBar.css('width', data.progress + '%').text(data.progress + '%');
        //                 }
        //                 if (data.done) {
        //                     clearInterval(progressInterval);
        //                     progressContainer.remove();
        //                     spinner.addClass('d-none');
        //                     container.html(
        //                         `<img src="${data.url}" class="img-fluid rounded-3 loaded-image" style="max-height: 200px; cursor: pointer;" alt="Изображение" onclick="openImage('${data.url}')">`
        //                         );
        //                 }
        //             }).fail(() => {});
        //         }, 500);
        //     });
        // }
//sse
// function downloadImage(chatId, messageId, element) {
//     const container = $(element).closest('.media-container');
//     const placeholder = $(element);
//     const spinner = placeholder.find('.spinner-border');
    
//     placeholder.find('i, span').hide();
//     spinner.removeClass('d-none');
    
//     // Создаем контейнер для прогресса
//     let progressContainer = container.find('.download-progress');
//     if (progressContainer.length === 0) {
//         progressContainer = $('<div class="download-progress mt-2"></div>');
//         container.append(progressContainer);
//     }
//     progressContainer.html('<div class="progress"><div class="progress-bar" style="width:0%">0%</div></div>');
//     const progressBar = progressContainer.find('.progress-bar');
    
//     const sseUrl = '{{ route("telegram.download.sse", ["chatId" => ":chatId", "messageId" => ":messageId"]) }}'
//         .replace(':chatId', chatId)
//         .replace(':messageId', messageId);
    
//     console.log(`🚀 [${chatId}/${messageId}] Подключаемся к SSE`);
    
//     // Подключаемся к SSE (этот запрос сам запустит скачивание)
//     const eventSource = new EventSource(sseUrl);
    
//     eventSource.addEventListener('progress', function(e) {
//         const data = JSON.parse(e.data);
//         progressBar.css('width', data.percent + '%').text(data.percent + '%');
//         console.log(`📈 [${chatId}/${messageId}] Прогресс: ${data.percent}% (${data.downloaded}/${data.total})`);
//     });
    
//     eventSource.addEventListener('complete', function(e) {
//         const data = JSON.parse(e.data);
//         console.log(`🎉 [${chatId}/${messageId}] Готово`);
        
//         eventSource.close();
//         progressContainer.remove();
//         spinner.addClass('d-none');
        
//         const imgHtml = `<img src="${data.url}" class="img-fluid rounded-3 loaded-image" style="max-height: 200px; cursor: pointer;" alt="Изображение" onclick="openImage('${data.url}')">`;
//         container.html(imgHtml);
//     });
    
//     eventSource.addEventListener('error', function(e) {
//         const data = JSON.parse(e.data);
//         console.error('❌ SSE error:', data.error);
//         spinner.addClass('d-none');
//         placeholder.find('i, span').show();
//         alert('Ошибка: ' + data.error);
//         eventSource.close();
//     });
    
//     eventSource.onerror = function(e) {
//         console.error('SSE connection error:', e);
//         // Браузер сам переподключится 
//     };
// }

function downloadImage(chatId, messageId, element) {
    const container = $(element).closest('.media-container');
    const placeholder = $(element);
    const spinner = placeholder.find('.spinner-border');
    
    placeholder.find('i, span').hide();
    spinner.removeClass('d-none');
    
    // Создаем контейнер для прогресса
    let progressContainer = container.find('.download-progress');
    if (progressContainer.length === 0) {
        progressContainer = $('<div class="download-progress mt-2"></div>');
        container.append(progressContainer);
    }
    progressContainer.html('<div class="progress"><div class="progress-bar" style="width:0%">0%</div></div>');
    const progressBar = progressContainer.find('.progress-bar');
    
    const sizeUrl = '{{ route("telegram.download.size", ["chatId" => ":chatId", "messageId" => ":messageId"]) }}'
        .replace(':chatId', chatId)
        .replace(':messageId', messageId);
    
    const sseUrl = '{{ route("telegram.download.sse", ["chatId" => ":chatId", "messageId" => ":messageId"]) }}'
        .replace(':chatId', chatId)
        .replace(':messageId', messageId);
    
    // Запрашиваем размер
    $.get(sizeUrl, function(sizeData) {
        const totalSize = sizeData.size;
        
        // Расчет ожидаемого времени загрузки (примерно 1 МБ/сек)
        const estimatedTimeMs = Math.min(30000, Math.max(2000, totalSize / 1000)); // от 2 до 30 секунд
        const steps = Math.floor(estimatedTimeMs / 300); // ~3 шага в секунду
        const stepPercent = Math.min(5, Math.floor(90 / steps) || 1);
        
        console.log(`🚀 [${chatId}/${messageId}] Размер: ${totalSize} байт, ожидаемое время: ${Math.round(estimatedTimeMs/1000)}с, шаг: ${stepPercent}%`);
        
        let simulatedPercent = 0;
        let realDataStarted = false;
        let finalUrl = null;
        
        // Имитация с реалистичной скоростью
        const simulationInterval = setInterval(() => {
            if (!realDataStarted && simulatedPercent < 90) {
                simulatedPercent = Math.min(simulatedPercent + stepPercent, 90);
                progressBar.css('width', simulatedPercent + '%').text(Math.round(simulatedPercent) + '%');
                console.log(`📊 [${chatId}/${messageId}] Имитация: ${Math.round(simulatedPercent)}%`);
            }
        }, 300); // Каждые 300 мс
        
        // SSE соединение
        const eventSource = new EventSource(sseUrl);
        
        eventSource.addEventListener('progress', function(e) {
            const data = JSON.parse(e.data);
            
            if (data.percent > 0 && !realDataStarted) {
                realDataStarted = true;
                clearInterval(simulationInterval);
                console.log(`✅ [${chatId}/${messageId}] Начало реальной загрузки на ${data.percent}%`);
            }
            
            if (realDataStarted) {
                progressBar.css('width', data.percent + '%').text(data.percent + '%');
            }
        });
        
        eventSource.addEventListener('complete', function(e) {
            const data = JSON.parse(e.data);
            finalUrl = data.url;
            
            console.log(`🎉 [${chatId}/${messageId}] Готово`);
            
            eventSource.close();
            clearInterval(simulationInterval);
            progressContainer.remove();
            spinner.addClass('d-none');
            
            if (finalUrl) {
                container.html(`<img src="${finalUrl}" class="img-fluid rounded-3 loaded-image" style="max-height: 200px; cursor: pointer;" alt="Изображение" onclick="openImage('${finalUrl}')">`);
            } else {
                placeholder.find('i, span').show();
            }
        });
        
        eventSource.onerror = function() {
            // Продолжаем имитацию
        };
        
    }).fail(function() {
        console.error('Size request failed');
        fetch(sseUrl).catch(console.error);
    });
}
// Вспомогательная функция для форматирования байтов
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
        // Функция открытия изображения
        function openImage(url) {
            window.open(url, '_blank');
        }
    </script>
@endsection
