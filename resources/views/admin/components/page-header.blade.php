<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">
            @if(isset($icon))
                <i class="{{ $icon }} me-2"></i>
            @endif
            {{ $title }}
        </h1>
        @if(isset($subtitle))
            <p class="text-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>

    <div>
        @if(isset($buttons))
            {{ $buttons }}
        @elseif(isset($createRoute))
            <a href="{{ route($createRoute) }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Добавить
            </a>
        @endif
    </div>
</div>
