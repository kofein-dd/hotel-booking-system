@component('mail::message')
    # Новый предложенный вопрос для FAQ

    Поступил новый вопрос от пользователя для добавления в раздел FAQ.

    ## Информация о вопросе:
    - **Вопрос:** {{ $question->question }}
    - **Описание:** {{ $question->description ?? 'Не указано' }}
    - **Статус:** Ожидает рассмотрения
    - **Дата предложения:** {{ $question->created_at->format('d.m.Y H:i') }}
    - **Голосов:** {{ $votesCount }}

    ## Информация о пользователе:
    @if($suggestedBy)
        - **Имя:** {{ $suggestedBy->name }}
        - **Email:** {{ $suggestedBy->email }}
    @else
        - **Анонимный пользователь**
        - **Email:** {{ $question->email ?? 'Не указан' }}
    @endif

    @component('mail::button', ['url' => $questionUrl])
        Просмотреть вопрос
    @endcomponent

    @component('mail::button', ['url' => $adminUrl, 'color' => 'green'])
        Все предложенные вопросы
    @endcomponent

    **Срок рассмотрения:** до {{ $reviewDeadline }}

@endcomponent
