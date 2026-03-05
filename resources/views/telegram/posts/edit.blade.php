@extends('telegram.layouts.app')

@section('title', '- Редактирование поста')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Редактирование поста #{{ $post->message_id }}</h5>
            </div>
            
            <form action="{{ route('telegram.post.update', [$post->chat_id, $post->message_id]) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Текст поста</label>
                        <textarea name="content" rows="6" 
                                  class="form-control">{{ $post->content }}</textarea>
                    </div>
                    
                    @if($post->media)
                        <div class="mb-3">
                            <label class="form-label">Текущие медиа</label>
                            <div class="row g-2">
                                @foreach(json_decode($post->media) as $media)
                                    <div class="col-3 position-relative">
                                        <img src="{{ $media->url ?? '' }}" class="img-fluid rounded-3">
                                        <button class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('telegram.chat.show', $post->chat_id) }}" class="btn btn-outline-secondary">
                        Отмена
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection