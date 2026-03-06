<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Telegram Manager @yield('title')</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- jQuery (для AJAX) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        .chat-list {
            height: calc(100vh - 60px);
            overflow-y: auto;
        }
        .message-bubble {
            max-width: 70%;
            word-wrap: break-word;
        }
        .message-bubble.out {
            background-color: #0d6efd;
            color: white;
        }
        .message-bubble.in {
            background-color: #e9ecef;
        }
        .drag-over {
            border: 2px dashed #0d6efd !important;
            background-color: #f8f9fa !important;
        }
        .excluded-chat {
            opacity: 0.6;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('telegram.chats') }}">
                <i class="bi bi-telegram me-2"></i>Telegram Manager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('telegram.chats') ? 'active' : '' }}" 
                           href="{{ route('telegram.chats') }}">
                            <i class="bi bi-chat-dots me-1"></i>Чаты
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('telegram.excluded') ? 'active' : '' }}" 
                           href="{{ route('telegram.excluded') }}">
                            <i class="bi bi-archive me-1"></i>Исключённые
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('telegram.comments.unprocessed') ? 'active' : '' }}" 
                           href="{{ route('telegram.comments.unprocessed') }}">
                            <i class="bi bi-chat-text me-1"></i>Комментарии
                            <span class="badge bg-danger rounded-pill unprocessed-count">0</span>
                        </a>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <!-- КНОПКА ДОБАВЛЕНИЯ ПРОКСИ -->
                    <button class="btn btn-sm btn-success" id="addProxyBtn" title="Добавить прокси">
                        <i class="bi bi-plus-circle me-1"></i>Добавить прокси
                    </button>
                    
                    <!-- КНОПКА СМЕНЫ ПРОКСИ -->
                    <button class="btn btn-sm btn-light" id="changeProxyBtn" title="Сменить прокси">
                        <i class="bi bi-arrow-repeat me-1"></i>Сменить прокси
                    </button>
                    
                    <select id="account-selector" class="form-select form-select-sm bg-white">
                        @foreach($accounts ?? [] as $account)
                            <option value="{{ $account->name }}" {{ session('current_account') == $account->name ? 'selected' : '' }}>
                                {{ $account->name }} ({{ $account->status }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </nav>

    <!-- Модальное окно добавления прокси -->
    <div class="modal fade" id="addProxyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить прокси вручную</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addProxyForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Формат ввода:</label>
                            <div class="alert alert-info py-2 small">
                                <strong>tg://proxy?server=IP&port=PORT&secret=SECRET</strong><br>
                                или<br>
                                <strong>Server: IP Port: PORT Secret: SECRET</strong><br>
                                или<br>
                                <strong>IP:PORT:SECRET</strong>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="proxyInput" class="form-label">Введите прокси</label>
                            <textarea class="form-control" id="proxyInput" rows="3" 
                                placeholder="tg://proxy?server=194.120.230.106&port=433&secret=ee123456..."></textarea>
                            <div class="form-text">Можно ввести несколько прокси, каждый с новой строки</div>
                        </div>
                        
                        <div id="proxyPreview" class="mb-3" style="display: none;">
                            <label class="form-label">Найдено прокси:</label>
                            <div class="list-group" id="proxyList"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="saveProxiesBtn">Сохранить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно смены прокси -->
    <div class="modal fade" id="proxyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Смена прокси</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="proxyStatus" class="mb-3"></div>
                    <div class="text-center">
                        <div class="spinner-border text-primary" id="proxySpinner" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <p class="mt-2" id="proxyMessage">Проверяем доступные прокси...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Основной контент -->
    <main class="container-fluid py-3">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <script>
        $(function() {
            // Обновление счётчика комментариев
            function updateUnreadCount() {
                $.ajax({
                    url: '{{ route("telegram.comments.count") }}',
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        const $badge = $('.unprocessed-count');
                        $badge.text(data.count);
                        if (data.count > 0) {
                            $badge.show();
                        } else {
                            $badge.hide();
                        }
                    },
                    error: function() {
                        console.log('Не удалось получить количество комментариев');
                        $('.unprocessed-count').text('0').show();
                    }
                });
            }
            
            updateUnreadCount();
            setInterval(updateUnreadCount, 30000);
            
            // Переключение аккаунта
            $('#account-selector').change(function() {
                $.post('/api/switch-account', {
                    account: $(this).val(),
                    _token: '{{ csrf_token() }}'
                }).then(function() {
                    location.reload();
                });
            });
            
            // Кнопка смены прокси
            $('#changeProxyBtn').click(function() {
                $('#proxyModal').modal('show');
                changeProxy();
            });
            
            // Обработчик кнопки добавления прокси
            $('#addProxyBtn').click(function() {
                $('#addProxyModal').modal('show');
                $('#proxyInput').val('');
                $('#proxyPreview').hide();
            });
            
            // Предпросмотр прокси при вводе
            $('#proxyInput').on('input', function() {
                const text = $(this).val();
                if (text.trim().length < 10) {
                    $('#proxyPreview').hide();
                    return;
                }
                
                $.ajax({
                    url: '/telegram/api/proxy/parse',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        text: text
                    },
                    success: function(response) {
                        if (response.proxies.length > 0) {
                            $('#proxyList').empty();
                            response.proxies.forEach(function(proxy) {
                                $('#proxyList').append(`
                                    <div class="list-group-item">
                                        <input type="checkbox" class="form-check-input me-2 proxy-checkbox" 
                                               data-server="${proxy.server}" 
                                               data-port="${proxy.port}" 
                                               data-secret="${proxy.secret}"
                                               data-type="${proxy.type}" checked>
                                        <span class="badge ${proxy.type === 'fake_tls' ? 'bg-success' : 'bg-secondary'} me-2">${proxy.type}</span>
                                        ${proxy.server}:${proxy.port}
                                        <small class="text-muted d-block">${proxy.secret.substring(0, 20)}...</small>
                                    </div>
                                `);
                            });
                            $('#proxyPreview').show();
                        } else {
                            $('#proxyList').html('<div class="alert alert-warning">Прокси не найдены</div>');
                            $('#proxyPreview').show();
                        }
                    }
                });
            });
            
            // Сохранение выбранных прокси
            $('#saveProxiesBtn').click(function() {
                const selectedProxies = [];
                $('.proxy-checkbox:checked').each(function() {
                    selectedProxies.push({
                        server: $(this).data('server'),
                        port: $(this).data('port'),
                        secret: $(this).data('secret'),
                        type: $(this).data('type'),
                        source: 'manual'
                    });
                });
                
                if (selectedProxies.length === 0) {
                    alert('Выберите хотя бы один прокси');
                    return;
                }
                
                $.ajax({
                    url: '/telegram/api/proxy/add',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        proxies: selectedProxies
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#addProxyModal').modal('hide');
                            alert(`✅ Добавлено ${response.added} новых прокси`);
                            updateProxyList();
                        } else {
                            alert('Ошибка: ' + response.error);
                        }
                    }
                });
            });
            
            function updateProxyList() {
                $.get('/telegram/api/proxy/list', function(response) {
                    console.log('Всего прокси:', response.data.length);
                });
            }
            
            function changeProxy() {
                $('#proxySpinner').show();
                $('#proxyMessage').text('Ищем быстрый прокси...');
                
                $.ajax({
                    url: '/telegram/api/proxy/change',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#proxyStatus').html(`
                                <div class="alert alert-success">
                                    <strong>Новый прокси:</strong> ${response.proxy.server}:${response.proxy.port}<br>
                                    <strong>Скорость:</strong> ${response.proxy.response_time} сек<br>
                                    <strong>Рейтинг:</strong> ${response.proxy.last_speed_rating}
                                </div>
                            `);
                            $('#proxyMessage').text('Прокси успешно сменён!');
                        } else {
                            $('#proxyStatus').html(`
                                <div class="alert alert-warning">
                                    Не удалось найти рабочий прокси. Используем прямое подключение.
                                </div>
                            `);
                        }
                        $('#proxySpinner').hide();
                    },
                    error: function(xhr) {
                        $('#proxyStatus').html(`
                            <div class="alert alert-danger">
                                Ошибка при смене прокси: ${xhr.responseJSON?.error || 'Неизвестная ошибка'}
                            </div>
                        `);
                        $('#proxySpinner').hide();
                    }
                });
            }
        });
    </script>
    
    @yield('scripts')
</body>
</html>