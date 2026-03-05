@extends('telegram.layouts.app')

@section('title', '- Новый пост')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Создание поста в канале "{{ $chat->title }}"</h5>
            </div>
            
            <form action="{{ route('telegram.post.store', $chat->id) }}" method="POST" enctype="multipart/form-data" id="post-form">
                @csrf
                
                <div class="card-body">
                    <!-- Текст поста -->
                    <div class="mb-3">
                        <label class="form-label">Текст поста *</label>
                        <textarea name="content" rows="6" 
                                  class="form-control" 
                                  placeholder="Введите текст поста..."
                                  required>{{ old('content') }}</textarea>
                    </div>
                    
                    <!-- Drag-n-drop загрузка фото -->
                    <div class="mb-3">
                        <label class="form-label">Медиа (фото, видео)</label>
                        <div id="drop-zone" class="border-2 border-dashed border-secondary rounded-3 p-5 text-center">
                            <i class="bi bi-cloud-upload display-4 text-muted"></i>
                            <p class="text-muted mt-2">Перетащите файлы сюда или кликните для выбора</p>
                            <input type="file" name="media[]" multiple accept="image/*,video/*" class="d-none" id="file-input">
                        </div>
                        
                        <!-- Превью загруженных файлов -->
                        <div id="preview-container" class="row g-2 mt-2"></div>
                    </div>
                    
                    <!-- Дополнительные настройки -->
                    <div class="bg-light p-3 rounded-3">
                        <h6 class="mb-3">Дополнительно</h6>
                        
                        <div class="form-check mb-2">
                            <input type="checkbox" name="disable_notification" class="form-check-input" id="disableNotification">
                            <label class="form-check-label" for="disableNotification">
                                Отключить уведомления
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" name="pin" class="form-check-input" id="pinPost">
                            <label class="form-check-label" for="pinPost">
                                Закрепить пост
                            </label>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Отложенная публикация</label>
                                <input type="datetime-local" name="scheduled_at" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('telegram.chat.show', $chat->id) }}" class="btn btn-outline-secondary">
                        Отмена
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Опубликовать
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    let $dropZone = $('#drop-zone');
    let $fileInput = $('#file-input');
    let $preview = $('#preview-container');
    
    // Клик по зоне для выбора файлов
    $dropZone.click(function() {
        $fileInput.click();
    });
    
    // Drag & drop
    $dropZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('drag-over');
    });
    
    $dropZone.on('dragleave', function(e) {
        $(this).removeClass('drag-over');
    });
    
    $dropZone.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
        
        let files = e.originalEvent.dataTransfer.files;
        handleFiles(files);
    });
    
    // Выбор файлов через диалог
    $fileInput.on('change', function() {
        handleFiles(this.files);
    });
    
    function handleFiles(files) {
        $.each(files, function(i, file) {
            if (file.type.startsWith('image/')) {
                let reader = new FileReader();
                reader.onload = function(e) {
                    $preview.append(`
                        <div class="col-3 position-relative">
                            <img src="${e.target.result}" class="img-fluid rounded-3">
                            <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 remove-file">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Удаление файла из превью
    $preview.on('click', '.remove-file', function() {
        $(this).closest('.col-3').remove();
    });
});
</script>
@endsection