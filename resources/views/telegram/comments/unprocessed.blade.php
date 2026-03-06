@extends('telegram.layouts.app')

@section('title', '- Необработанные комментарии')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Необработанные комментарии</h5>
    </div>
    
    <div class="card-body p-0">
        @if($comments->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-check-circle display-1 text-success"></i>
                <p class="mt-3">Все комментарии обработаны</p>
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach($comments as $comment)
                    <div class="list-group-item" id="comment-{{ $comment->id }}">
                        <div class="d-flex justify-content-between">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <strong>{{ $comment->user_name ?? 'Пользователь' }}</strong>
                                    <span class="badge bg-secondary">#{{ $comment->user_id }}</span>
                                    <small class="text-muted">{{ $comment->date->diffForHumans() }}</small>
                                </div>
                                
                                <p class="mb-2">{{ $comment->text }}</p>
                                
                                <small class="text-muted">
                                    <i class="bi bi-chat me-1"></i>
                                    В чате <a href="{{ route('telegram.chat.show', $comment->chat_id) }}" 
                                             class="text-decoration-none">
                                        {{ $comment->chat_title }}
                                    </a>
                                    @if($comment->post_id)
                                        → к посту #{{ $comment->post_id }}
                                    @endif
                                </small>
                            </div>
                            
                            <button class="btn btn-success btn-sm ms-3 mark-processed"
                                    data-comment-id="{{ $comment->id }}">
                                <i class="bi bi-check-lg me-1"></i>Обработано
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="card-footer bg-white">
                {{ $comments->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    $('.mark-processed').click(function() {
        let commentId = $(this).data('comment-id');
        let btn = $(this);
        
        $.ajax({
            url: '/telegram/api/comments/' + commentId + '/processed',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function() {
                $('#comment-' + commentId).fadeOut();
            }
        });
    });
});
</script>
@endsection